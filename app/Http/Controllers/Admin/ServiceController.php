<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequest;
use App\Models\Service;
use App\Services\StripeProductSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __construct(private StripeProductSyncService $stripeSync) {}

    public function index(): View
    {
        return view('admin.services.index', [
            'services' => Service::orderBy('price')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.services.form', [
            'service' => new Service,
        ]);
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $service = Service::create($request->validated());

        $this->stripeSync->sync($service);

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
        $validated = $request->validated();
        $priceChanged = isset($validated['price']) && (float) $validated['price'] !== (float) $service->price;

        $service->update($validated);

        // Sync to Stripe - if price changed, archive old price and create new one
        $this->stripeSync->sync($service->fresh());

        return redirect()
            ->route('admin.services.index')
            ->with('success', 'Service updated successfully.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $this->stripeSync->archive($service);
        $service->delete();

        return back()->with('success', 'Service deleted successfully.');
    }
}
