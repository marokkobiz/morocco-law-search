<?php

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Mail\LegalAidAdvisorClaimedMail;
use App\Models\LegalAidRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class CaseController extends Controller
{
    public function index(Request $request): View
    {
        $query = LegalAidRequest::query()
            ->with(['services', 'service', 'advisor', 'caseNotes'])
            ->visibleToAdvisors();

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('whatsapp', 'like', "%{$search}%");
            });
        }

        if (in_array($request->query('case_status'), [LegalAidRequest::CASE_OPEN, LegalAidRequest::CASE_CLOSED], true)) {
            $query->caseStatus($request->query('case_status'));
        }

        if (in_array($request->query('payment_status'), ['free', 'paid', 'confirmed'], true)) {
            match ($request->query('payment_status')) {
                'free' => $query->where('base_price', 0),
                'paid' => $query->where('status', LegalAidRequest::STATUS_PAID),
                'confirmed' => $query->where('status', LegalAidRequest::STATUS_CONFIRMED)
                    ->where(fn ($q) => $q->whereNull('base_price')->orWhere('base_price', '>', 0)),
                default => null,
            };
        }

        if ($advisorFilter = $request->query('advisor')) {
            if ($advisorFilter === 'unclaimed') {
                $query->whereNull('advisor_id');
            } elseif ((int) $advisorFilter) {
                $query->where('advisor_id', (int) $advisorFilter);
            }
        }

        if ($serviceId = (int) $request->query('service')) {
            $query->whereHas('services', fn ($q) => $q->whereKey($serviceId));
        }

        return view('advisor.cases.index', [
            'requests' => $query->orderBy('case_status')
                ->orderBy('created_at')
                ->paginate(4)
                ->withQueryString(),
            'advisors' => User::where('role', 'advisor')->orderBy('name')->get(),
            'services' => Service::orderBy('name_en')->get(),
            'filters' => [
                'search' => $request->query('search', ''),
                'case_status' => $request->query('case_status', ''),
                'payment_status' => $request->query('payment_status', ''),
                'advisor' => $request->query('advisor', ''),
                'service' => $request->query('service', ''),
            ],
        ]);
    }

    public function show(LegalAidRequest $legalAidRequest): View
    {
        abort_unless($legalAidRequest->isVisibleToAdvisors(), 404);

        $legalAidRequest->load(['services', 'service', 'advisor', 'caseNotes.user']);

        return view('advisor.cases.show', [
            'request' => $legalAidRequest,
        ]);
    }

    public function toggleService(LegalAidRequest $legalAidRequest, Service $service): RedirectResponse
    {
        abort_unless($legalAidRequest->isVisibleToAdvisors(), 404);

        $pivot = $legalAidRequest->services()->whereKey($service->id)->first();

        $markComplete = ! $pivot || $pivot->pivot->completed_at === null;

        if ($pivot) {
            $legalAidRequest->services()->updateExistingPivot($service->id, [
                'completed_at' => $markComplete ? now() : null,
            ]);
        } else {
            $legalAidRequest->services()->attach($service->id, [
                'completed_at' => now(),
            ]);
        }

        $legalAidRequest->touchCase();

        return back()->with('success', $markComplete
            ? 'Task "'.$service->name.'" marked as done for '.$legalAidRequest->ticketLabel.'.'
            : 'Task "'.$service->name.'" marked as missing for '.$legalAidRequest->ticketLabel.'.');
    }

    public function markFirstContact(LegalAidRequest $legalAidRequest): RedirectResponse
    {
        abort_unless($legalAidRequest->isVisibleToAdvisors(), 404);

        if ($legalAidRequest->advisor_id && $legalAidRequest->advisor_id !== auth()->id()) {
            return back()->with('error', 'This case is already claimed by another advisor.');
        }

        $wasFirstClaim = $legalAidRequest->advisor_id === null || $legalAidRequest->first_contact_at === null;

        $legalAidRequest->update([
            'advisor_id' => auth()->id(),
            'first_contact_at' => $legalAidRequest->first_contact_at ?? now(),
        ]);

        $legalAidRequest->touchCase();

        if ($wasFirstClaim) {
            $legalAidRequest->load('advisor');
            try {
                Mail::to($legalAidRequest->email)
                    ->locale($legalAidRequest->locale ?: app()->getLocale())
                    ->queue(new LegalAidAdvisorClaimedMail($legalAidRequest));
            } catch (\Throwable $e) {
                report($e);
                try {
                    Mail::to($legalAidRequest->email)
                        ->locale($legalAidRequest->locale ?: app()->getLocale())
                        ->send(new LegalAidAdvisorClaimedMail($legalAidRequest));
                } catch (\Throwable $inner) {
                    report($inner);
                }
            }
        }

        return back()->with('success', 'You are now the first contact for '.$legalAidRequest->ticketLabel.'. Customer has been notified by email.');
    }

    public function close(LegalAidRequest $legalAidRequest): RedirectResponse
    {
        abort_unless($legalAidRequest->isVisibleToAdvisors(), 404);

        $legalAidRequest->update([
            'case_status' => LegalAidRequest::CASE_CLOSED,
            'closed_at' => now(),
        ]);

        $legalAidRequest->touchCase();

        return back()->with('success', 'Case '.$legalAidRequest->ticketLabel.' has been closed.');
    }

    public function reopen(LegalAidRequest $legalAidRequest): RedirectResponse
    {
        abort_unless($legalAidRequest->isVisibleToAdvisors(), 404);

        $legalAidRequest->update([
            'case_status' => LegalAidRequest::CASE_OPEN,
            'closed_at' => null,
        ]);

        $legalAidRequest->touchCase();

        return back()->with('success', 'Case '.$legalAidRequest->ticketLabel.' has been reopened.');
    }

    public function storeNote(Request $request, LegalAidRequest $legalAidRequest): RedirectResponse
    {
        abort_unless($legalAidRequest->isVisibleToAdvisors(), 404);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:5000'],
        ]);

        $legalAidRequest->caseNotes()->create([
            'user_id' => auth()->id(),
            'note' => $validated['note'],
        ]);

        $legalAidRequest->touchCase();

        return back()->with('success', 'Note added to '.$legalAidRequest->ticketLabel.'.');
    }
}
