<?php

namespace App\Services;

use App\Models\LegalAidRequest;
use App\Models\Order;
use App\Support\AdvisorNotifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderCaseService
{
    /**
     * Create a LegalAidRequest advisor case from a paid Order.
     * Idempotent: if a case already exists for this order_id, returns it without duplication.
     * The case becomes visible to advisors (status=paid) so they can contact the customer.
     */
    public static function createCaseFromOrder(Order $order): ?LegalAidRequest
    {
        if (! $order->isPaid()) {
            return null;
        }

        // If already linked, return existing
        $existing = LegalAidRequest::where('order_id', $order->id)->first();
        if ($existing) {
            return $existing;
        }

        // Also fallback: check ticket_number collision pre-existing without order_id (legacy)
        // If ticket_number already taken, we cannot reuse — generate a new one.

        // Ensure order has items loaded with services
        $order->loadMissing(['items.service']);

        $serviceIds = $order->items->pluck('service_id')->filter()->values();
        $primaryServiceId = $serviceIds->first();

        // Generate unique ticket_number: prefer order CIN/ticket_number if unique, else random
        $ticketNumber = self::generateUniqueTicketNumber($order);

        // Try to infer consultation_mode from first service, default to office
        $consultationMode = null;
        if ($primaryServiceId) {
            $service = $order->items->first()?->service;
            if ($service) {
                $modes = $service->consultationModes;
                $consultationMode = $modes[0] ?? 'office';
            }
        }
        $consultationMode = $consultationMode ?: 'office';

        // Wrap in transaction, handle race condition on order_id unique
        try {
            return DB::transaction(function () use ($order, $ticketNumber, $primaryServiceId, $serviceIds, $consultationMode): LegalAidRequest {
                // Double-check inside transaction for race
                $existingInner = LegalAidRequest::where('order_id', $order->id)->lockForUpdate()->first();
                if ($existingInner) {
                    return $existingInner;
                }

                $legalAidRequest = LegalAidRequest::create([
                    'ticket_number' => $ticketNumber,
                    'full_name' => $order->full_name ?: 'Customer '.$order->cin,
                    'email' => $order->email,
                    'phone' => $order->phone ?: null,
                    'whatsapp' => $order->whatsapp ?: null,
                    'case_description' => $order->case_description ?: 'Shop order #'.$order->id.' – '.$order->items->map(fn($i)=> $i->service?->name_en ?? 'Service '.$i->service_id)->implode(', '),
                    'service_id' => $primaryServiceId,
                    'order_id' => $order->id,
                    'base_price' => $order->total_amount,
                    'status' => LegalAidRequest::STATUS_PAID,
                    'payment_method' => LegalAidRequest::PAYMENT_METHOD_STRIPE,
                    'consultation_mode' => $consultationMode,
                    'call_time' => $order->call_time,
                    'locale' => $order->locale ?: app()->getLocale(),
                    'paid_at' => $order->paid_at ?: now(),
                    'case_status' => LegalAidRequest::CASE_OPEN,
                ]);

                if ($serviceIds->isNotEmpty()) {
                    $legalAidRequest->services()->sync($serviceIds->all());
                }

                // Notify advisors so they can contact the customer
                try {
                    AdvisorNotifier::caseReady($legalAidRequest);
                } catch (\Throwable $e) {
                    report($e);
                }

                return $legalAidRequest;
            });
        } catch (QueryException $e) {
            // Unique violation on order_id or ticket_number — fetch existing
            if ((string) $e->getCode() === '23000') {
                $existingAfterRace = LegalAidRequest::where('order_id', $order->id)->first();
                if ($existingAfterRace) {
                    return $existingAfterRace;
                }
                // ticket_number collision — retry with random
                return self::createCaseFromOrderWithRandomTicket($order, $serviceIds, $primaryServiceId, $consultationMode);
            }
            throw $e;
        }
    }

    private static function createCaseFromOrderWithRandomTicket(Order $order, $serviceIds, $primaryServiceId, $consultationMode): ?LegalAidRequest
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $ticketNumber = self::generateRandomTicketNumber();
                $legalAidRequest = LegalAidRequest::create([
                    'ticket_number' => $ticketNumber,
                    'full_name' => $order->full_name ?: 'Customer '.$order->cin,
                    'email' => $order->email,
                    'phone' => $order->phone ?: null,
                    'whatsapp' => $order->whatsapp ?: null,
                    'case_description' => $order->case_description ?: 'Shop order #'.$order->id,
                    'service_id' => $primaryServiceId,
                    'order_id' => $order->id,
                    'base_price' => $order->total_amount,
                    'status' => LegalAidRequest::STATUS_PAID,
                    'payment_method' => LegalAidRequest::PAYMENT_METHOD_STRIPE,
                    'consultation_mode' => $consultationMode,
                    'call_time' => $order->call_time,
                    'locale' => $order->locale ?: app()->getLocale(),
                    'paid_at' => $order->paid_at ?: now(),
                    'case_status' => LegalAidRequest::CASE_OPEN,
                ]);
                if ($serviceIds->isNotEmpty()) {
                    $legalAidRequest->services()->sync($serviceIds->all());
                }
                try {
                    AdvisorNotifier::caseReady($legalAidRequest);
                } catch (\Throwable $e) {
                    report($e);
                }
                return $legalAidRequest;
            } catch (QueryException $e) {
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }
        return null;
    }

    private static function generateUniqueTicketNumber(Order $order): string
    {
        $candidate = $order->ticket_number && $order->ticket_number !== 'PENDING' ? $order->ticket_number : ($order->cin && $order->cin !== 'PENDING' ? $order->cin : null);
        if ($candidate) {
            $candidate = substr(strtoupper(trim((string) $candidate)), 0, 20);
            // If already exists without order_id, we need a suffix to avoid collision
            if (! LegalAidRequest::where('ticket_number', $candidate)->exists()) {
                return $candidate;
            }
            // Try candidate with suffix -1, -2
            for ($i = 1; $i <= 9; $i++) {
                $withSuffix = substr($candidate, 0, 18).'-'.$i;
                if (! LegalAidRequest::where('ticket_number', $withSuffix)->exists()) {
                    return $withSuffix;
                }
            }
        }
        return self::generateRandomTicketNumber();
    }

    private static function generateRandomTicketNumber(): string
    {
        do {
            $number = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        } while (LegalAidRequest::where('ticket_number', $number)->exists());
        return $number;
    }
}
