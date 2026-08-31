<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequest;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        return view('admin.services.index', [
            'services' => Service::ordered()->get(),
        ]);
    }

    public function create(): View
    {
        $nextOrder = (Service::max('sort_order') ?? 0) + 1;
        $service = new Service(['sort_order' => $nextOrder]);

        return view('admin.services.form', [
            'service' => $service,
        ]);
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        // Stripe sync is handled by ServiceObserver::saved (single source of truth).
        // Previously we also called StripeProductSyncService::sync() here, which
        // ran a second sync on the stale $service instance (before the observer's
        // saveQuietly persisted stripe_product_id/price_id), causing a duplicate
        // Stripe Product/Price to be created on every new service.
        Service::create($request->validated());

        return redirect()
            ->route('admin.services.index')
            ->with('success', 'Service created successfully.');
    }

    public function edit(Service $service): View
    {
        return view('admin.services.form', [
            'service' => $service,
        ]);
    }

    public function update(StoreServiceRequest $request, Service $service): RedirectResponse
    {
        // Observer handles Stripe sync; no manual sync needed here (would duplicate).
        $service->update($request->validated());

        return redirect()
            ->route('admin.services.index')
            ->with('success', 'Service updated successfully.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        // Stripe archive is handled by ServiceObserver::deleted. Just delete.
        $service->delete();

        return back()->with('success', 'Service deleted successfully.');
    }

    public function reorder(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:services,id'],
        ]);

        foreach ($validated['order'] as $index => $id) {
            Service::where('id', $id)->update(['sort_order' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }
}
