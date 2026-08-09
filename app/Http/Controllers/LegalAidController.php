<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLegalAidRequest;
use App\Http\Requests\UploadLegalAidReceiptRequest;
use App\Mail\LegalAidAdminNotificationMail;
use App\Mail\LegalAidConfirmationMail;
use App\Mail\LegalAidReceiptNotificationMail;
use App\Mail\LegalAidRejectionMail;
use App\Mail\LegalAidTicketMail;
use App\Models\LegalAidRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class LegalAidController
{
    public function index(): View
    {
        return view('legal-aid');
    }

    public function store(StoreLegalAidRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $legalAidRequest = LegalAidRequest::create(array_merge($validated, [
            'ticket_number' => $this->generateTicketNumber(),
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
            'locale' => $request->getLocale(),
        ]));

        $paymentUrl = (string) config('legal_aid.payment_url');
        $paymentLink = route('legal-aid.payment', $legalAidRequest->ticket_number);

        Mail::to($legalAidRequest->email)
            ->locale($legalAidRequest->locale)
            ->queue(new LegalAidTicketMail($legalAidRequest, $paymentUrl, $paymentLink));

        Mail::to(config('legal_aid.contact_email'))
            ->queue(new LegalAidAdminNotificationMail($legalAidRequest, $paymentUrl, $paymentLink));

        return back()->with('ticket', '#'.$legalAidRequest->ticket_number);
    }

    public function payment(string $ticket): View
    {
        $legalAidRequest = LegalAidRequest::where('ticket_number', $ticket)->firstOrFail();

        return view('legal-aid-payment', [
            'request' => $legalAidRequest,
            'paymentUrl' => (string) config('legal_aid.payment_url'),
        ]);
    }

    public function uploadReceipt(UploadLegalAidReceiptRequest $request, string $ticket): RedirectResponse
    {
        $legalAidRequest = LegalAidRequest::where('ticket_number', $ticket)->firstOrFail();

        if ($legalAidRequest->isPaid()) {
            return back()->with('error', __('legal_aid.payment_already_paid'));
        }

        $receiptPath = $request->validated('receipt')->store('receipts', 'public');

        $legalAidRequest->update([
            'receipt_path' => $receiptPath,
        ]);

        Mail::to(config('legal_aid.contact_email'))
            ->queue(new LegalAidReceiptNotificationMail($legalAidRequest));

        return back()->with('status', __('legal_aid.receipt_uploaded'));
    }

    public function adminIndex(): View
    {
        return view('admin.legal-aid', [
            'requests' => LegalAidRequest::latest()->get(),
            'paymentUrl' => (string) config('legal_aid.payment_url'),
        ]);
    }

    public function show(LegalAidRequest $legalAidRequest): View
    {
        return view('admin.legal-aid-show', [
            'request' => $legalAidRequest,
            'paymentUrl' => (string) config('legal_aid.payment_url'),
        ]);
    }

    public function confirm(LegalAidRequest $legalAidRequest): RedirectResponse
    {
        if ($legalAidRequest->isPaid()) {
            return back()->with('error', __('legal_aid.already_confirmed'));
        }

        if (! $legalAidRequest->receipt_path) {
            return back()->with('error', __('legal_aid.cannot_confirm_without_receipt'));
        }

        $legalAidRequest->update([
            'status' => LegalAidRequest::STATUS_CONFIRMED,
            'paid_at' => now(),
            'confirmed_at' => now(),
        ]);

        Mail::to($legalAidRequest->email)
            ->locale($legalAidRequest->locale)
            ->queue(new LegalAidConfirmationMail($legalAidRequest));

        return back()->with('success', __('legal_aid.confirmed_ok', ['ticket' => $legalAidRequest->ticketLabel]));
    }

    public function resendPaymentLink(LegalAidRequest $legalAidRequest): RedirectResponse
    {
        if ($legalAidRequest->isPaid()) {
            return back()->with('error', __('legal_aid.resend_not_allowed'));
        }

        if ($legalAidRequest->status === LegalAidRequest::STATUS_REJECTED) {
            if ($legalAidRequest->receipt_path) {
                Storage::disk('public')->delete($legalAidRequest->receipt_path);
            }

            $legalAidRequest->update([
                'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
                'receipt_path' => null,
            ]);
        }

        $paymentUrl = (string) config('legal_aid.payment_url');
        $paymentLink = route('legal-aid.payment', $legalAidRequest->ticket_number);

        Mail::to($legalAidRequest->email)
            ->locale($legalAidRequest->locale)
            ->queue(new LegalAidTicketMail($legalAidRequest, $paymentUrl, $paymentLink));

        return back()->with('success', __('legal_aid.resend_ok', ['email' => $legalAidRequest->email]));
    }

    public function reject(LegalAidRequest $legalAidRequest): RedirectResponse
    {
        if ($legalAidRequest->isPaid()) {
            return back()->with('error', __('legal_aid.already_confirmed'));
        }

        if (! $legalAidRequest->receipt_path) {
            return back()->with('error', __('legal_aid.cannot_reject_without_receipt'));
        }

        Storage::disk('public')->delete($legalAidRequest->receipt_path);

        $legalAidRequest->update([
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
            'receipt_path' => null,
        ]);

        $paymentLink = route('legal-aid.payment', $legalAidRequest->ticket_number);

        Mail::to($legalAidRequest->email)
            ->locale($legalAidRequest->locale)
            ->queue(new LegalAidRejectionMail($legalAidRequest, $paymentLink));

        return back()->with('success', __('legal_aid.rejected_ok', ['ticket' => $legalAidRequest->ticketLabel]));
    }

    private function generateTicketNumber(): string
    {
        do {
            $number = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        } while (LegalAidRequest::where('ticket_number', $number)->exists());

        return $number;
    }
}
