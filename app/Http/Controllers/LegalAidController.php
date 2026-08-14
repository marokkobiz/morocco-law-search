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
use App\Models\Service;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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

        $service = Service::findOrFail($validated['service_id']);

        $validated['consultation_mode'] = $service->allowsMode($validated['consultation_mode'] ?? null)
            ? $validated['consultation_mode']
            : null;

        $legalAidRequest = null;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $legalAidRequest = LegalAidRequest::create(array_merge($validated, [
                    'service_id' => $service->id,
                    'base_price' => $service->price,
                    'ticket_number' => $this->generateTicketNumber(),
                    'status' => (float) $service->price === 0.0
                        ? LegalAidRequest::STATUS_PENDING
                        : LegalAidRequest::STATUS_PENDING_PAYMENT,
                    'locale' => $request->getLocale(),
                ]));

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

        $this->generateTicketPdf($legalAidRequest);

        $paymentUrl = $legalAidRequest->isFree()
            ? ''
            : (string) config('legal_aid.payment_url');
        $paymentLink = $legalAidRequest->isFree()
            ? ''
            : route('legal-aid.payment', $legalAidRequest->ticket_number);

        Mail::to($legalAidRequest->email)
            ->locale($legalAidRequest->locale)
            ->queue(new LegalAidTicketMail($legalAidRequest, $paymentUrl, $paymentLink));

        Mail::to(config('legal_aid.contact_email'))
            ->queue(new LegalAidAdminNotificationMail($legalAidRequest, $paymentUrl, $paymentLink));

        return back()
            ->with('ticket', '#'.$legalAidRequest->ticket_number)
            ->with('ticket_number', $legalAidRequest->ticket_number);
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

        $paymentUrl = $legalAidRequest->isFree()
            ? ''
            : (string) config('legal_aid.payment_url');
        $paymentLink = $legalAidRequest->isFree()
            ? ''
            : route('legal-aid.payment', $legalAidRequest->ticket_number);

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
            $pdf = Pdf::loadView('pdf.legal-aid-ticket', ['request' => $legalAidRequest]);

            $pdfPath = 'tickets/legal-aid-ticket-'.$legalAidRequest->ticket_number.'.pdf';

            Storage::disk('public')->put($pdfPath, $pdf->output());

            $legalAidRequest->update(['ticket_pdf_path' => $pdfPath]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function generateTicketNumber(): string
    {
        do {
            $number = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        } while (LegalAidRequest::where('ticket_number', $number)->exists());

        return $number;
    }
}
