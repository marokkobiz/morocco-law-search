<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLegalAidRequest;
use App\Http\Requests\UploadLegalAidReceiptRequest;
use App\Mail\LegalAidAdminNotificationMail;
use App\Mail\LegalAidBookingConfirmationMail;
use App\Mail\LegalAidConfirmationMail;
use App\Mail\LegalAidReceiptNotificationMail;
use App\Mail\LegalAidRejectionMail;
use App\Mail\LegalAidTicketMail;
use App\Models\LegalAidConfirmation;
use App\Models\LegalAidRequest;
use App\Models\Service;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class LegalAidController
{
    public function index(): View
    {
        return view('legal-aid', [
            'services' => Service::orderBy('price')->get(),
        ]);
    }

    public function store(StoreLegalAidRequest $request): Response|RedirectResponse
    {
        $validated = $request->validated();

        $validated['service_ids'] = array_values(array_unique((array) $validated['service_ids']));
        $validated['locale'] = app()->getLocale();

        $confirmation = LegalAidConfirmation::create([
            'token' => $this->generateConfirmationToken(),
            'email' => $validated['email'],
            'payload' => $validated,
            'expires_at' => now()->addHours((int) config('legal_aid.booking_confirmation_hours', 24)),
        ]);

        Mail::to($confirmation->email)
            ->locale(app()->getLocale())
            ->queue(new LegalAidBookingConfirmationMail($confirmation));

        return back()
            ->with('confirmation_sent', $confirmation->email);
    }

    public function confirmBooking(string $token): Response|RedirectResponse
    {
        $confirmation = LegalAidConfirmation::where('token', $token)->first();

        if (! $confirmation || $confirmation->isConfirmed()) {
            return redirect()->route('legal-aid')
                ->with('error', __('legal_aid.confirm_invalid'));
        }

        if ($confirmation->isExpired()) {
            return redirect()->route('legal-aid')
                ->with('error', __('legal_aid.confirm_expired'));
        }

        $payload = $confirmation->payload;
        $locale = (string) ($payload['locale'] ?? 'en');

        $serviceIds = array_values(array_unique((array) ($payload['service_ids'] ?? [])));
        $services = Service::whereIn('id', $serviceIds)->get();

        if ($services->isEmpty()) {
            return redirect()->route('legal-aid')
                ->with('error', __('legal_aid.confirm_invalid'));
        }

        $allowedModes = $services
            ->map->consultationModes
            ->reject(fn (array $modes) => $modes === [])
            ->reduce(
                fn (?array $carry, array $modes) => $carry === null ? $modes : array_values(array_intersect($carry, $modes)),
                null
            ) ?? [];

        $consultationMode = isset($payload['consultation_mode']) && in_array($payload['consultation_mode'], $allowedModes, true)
            ? $payload['consultation_mode']
            : null;

        $paymentMethod = (string) ($payload['payment_method'] ?? LegalAidRequest::PAYMENT_METHOD_GOOGLE_PAY);
        $paymentMethod = in_array($paymentMethod, [LegalAidRequest::PAYMENT_METHOD_GOOGLE_PAY, LegalAidRequest::PAYMENT_METHOD_BANK], true)
            ? $paymentMethod
            : LegalAidRequest::PAYMENT_METHOD_GOOGLE_PAY;

        app()->setLocale($locale);

        $basePrice = $services->sum('price');

        $legalAidRequest = null;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $legalAidRequest = LegalAidRequest::create([
                    'ticket_number' => $this->generateTicketNumber(),
                    'full_name' => (string) $payload['full_name'],
                    'email' => (string) $payload['email'],
                    'phone' => (string) $payload['phone'],
                    'whatsapp' => $payload['whatsapp'] ?? null,
                    'case_description' => (string) $payload['case_description'],
                    'service_id' => $services->first()->id,
                    'base_price' => $basePrice,
                    'status' => (float) $basePrice === 0.0
                        ? LegalAidRequest::STATUS_PENDING
                        : LegalAidRequest::STATUS_PENDING_PAYMENT,
                    'consultation_mode' => $consultationMode,
                    'payment_method' => $paymentMethod,
                    'locale' => $locale,
                ]);

                break;
            } catch (QueryException $e) {
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }

        if (! $legalAidRequest) {
            throw new RuntimeException('Unable to allocate a unique ticket number.');
        }

        $legalAidRequest->services()->attach($services->pluck('id'));
        $legalAidRequest->load('services');

        $this->generateTicketPdf($legalAidRequest);

        $confirmation->update(['confirmed_at' => now()]);

        [$paymentUrl, $paymentLink] = $this->paymentUrls($legalAidRequest);

        Mail::to($legalAidRequest->email)
            ->locale($locale)
            ->queue(new LegalAidTicketMail($legalAidRequest, $paymentUrl, $paymentLink));

        Mail::to(config('legal_aid.contact_email'))
            ->queue(new LegalAidAdminNotificationMail($legalAidRequest, $paymentUrl, $paymentLink));

        return redirect()->route('legal-aid.confirmed', ['ticket' => $legalAidRequest->ticket_number]);
    }

    public function confirmed(string $ticket): View
    {
        $legalAidRequest = LegalAidRequest::where('ticket_number', $ticket)->firstOrFail();

        app()->setLocale($legalAidRequest->locale ?: app()->getLocale());

        return view('legal-aid-confirmed', [
            'request' => $legalAidRequest,
        ]);
    }

    public function downloadTicketPdf(string $ticket): Response
    {
        $legalAidRequest = LegalAidRequest::where('ticket_number', $ticket)->firstOrFail();

        abort_unless(
            $legalAidRequest->ticket_pdf_path && Storage::disk('public')->exists($legalAidRequest->ticket_pdf_path),
            404
        );

        return Storage::disk('public')->download(
            $legalAidRequest->ticket_pdf_path,
            'legal-aid-ticket-'.$legalAidRequest->ticket_number.'.pdf'
        );
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
            'requests' => LegalAidRequest::with(['services', 'service'])->latest()->get(),
            'paymentUrl' => (string) config('legal_aid.payment_url'),
        ]);
    }

    public function show(LegalAidRequest $legalAidRequest): View
    {
        $legalAidRequest->load(['services', 'service']);

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

        if (! $legalAidRequest->receipt_path && ! $legalAidRequest->isFree()) {
            return back()->with('error', __('legal_aid.cannot_confirm_without_receipt'));
        }

        $legalAidRequest->update([
            'status' => LegalAidRequest::STATUS_CONFIRMED,
            'paid_at' => $legalAidRequest->isFree() ? null : now(),
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

        [$paymentUrl, $paymentLink] = $this->paymentUrls($legalAidRequest);

        Mail::to($legalAidRequest->email)
            ->locale($legalAidRequest->locale)
            ->queue(new LegalAidTicketMail($legalAidRequest, $paymentUrl, $paymentLink));

        $message = $legalAidRequest->isFree()
            ? __('legal_aid.resend_ok_free', ['email' => $legalAidRequest->email])
            : __('legal_aid.resend_ok', ['email' => $legalAidRequest->email]);

        return back()->with('success', $message);
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

    private function generateTicketPdf(LegalAidRequest $legalAidRequest): void
    {
        try {
            $locale = $legalAidRequest->locale ?: app()->getLocale();
            app()->setLocale($locale);

            $pdf = Pdf::loadView('pdf.legal-aid-ticket', [
                'request' => $legalAidRequest,
                'locale' => $locale,
            ]);

            $pdfPath = 'tickets/legal-aid-ticket-'.$legalAidRequest->ticket_number.'.pdf';

            Storage::disk('public')->put($pdfPath, $pdf->output());

            $legalAidRequest->update(['ticket_pdf_path' => $pdfPath]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function generateConfirmationToken(): string
    {
        do {
            $token = Str::random(40);
        } while (LegalAidConfirmation::where('token', $token)->exists());

        return $token;
    }

    /**
     * @return array{0: string, 1: string} [paymentUrl, paymentLink]
     */
    private function paymentUrls(LegalAidRequest $legalAidRequest): array
    {
        if ($legalAidRequest->isFree()) {
            return ['', ''];
        }

        if ($legalAidRequest->payment_method === LegalAidRequest::PAYMENT_METHOD_BANK) {
            return ['', route('legal-aid.payment', $legalAidRequest->ticket_number)];
        }

        return [(string) config('legal_aid.payment_url'), route('legal-aid.payment', $legalAidRequest->ticket_number)];
    }

    private function generateTicketNumber(): string
    {
        do {
            $number = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        } while (LegalAidRequest::where('ticket_number', $number)->exists());

        return $number;
    }
}
