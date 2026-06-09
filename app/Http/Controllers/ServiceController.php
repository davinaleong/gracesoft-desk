<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Service;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        $services = Service::query()
            ->with('vendor')
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->when(request('vendor_uuid'), fn ($q, $uuid) => $q->whereHas('vendor', fn ($vq) => $vq->where('uuid', $uuid)))
            ->when(request('category'), fn ($q, $cat) => $q->where('category', $cat))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $vendors = Vendor::query()->orderBy('name')->get();

        return view('services.index', [
            'services' => $services,
            'vendors' => $vendors,
        ]);
    }

    public function create(): View
    {
        $vendors = Vendor::query()->active()->orderBy('name')->get();

        return view('services.create', [
            'vendors' => $vendors,
        ]);
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $vendor = Vendor::query()->where('uuid', $payload['vendor_uuid'])->firstOrFail();
        $payload['vendor_id'] = $vendor->id;
        unset($payload['vendor_uuid']);

        $service = Service::query()->create($payload);

        return redirect()
            ->route('services.show', $service)
            ->with('status', 'service-created');
    }

    public function show(Service $service): View
    {
        $service->load('vendor');

        return view('services.show', [
            'service' => $service,
        ]);
    }

    public function edit(Service $service): View
    {
        $service->load('vendor');
        $vendors = Vendor::query()->active()->orderBy('name')->get();

        return view('services.edit', [
            'service' => $service,
            'vendors' => $vendors,
        ]);
    }

    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $payload = $request->validated();
        $vendor = Vendor::query()->where('uuid', $payload['vendor_uuid'])->firstOrFail();
        $payload['vendor_id'] = $vendor->id;
        unset($payload['vendor_uuid']);

        $service->update($payload);

        return redirect()
            ->route('services.show', $service)
            ->with('status', 'service-updated');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return redirect()
            ->route('services.index')
            ->with('status', 'service-deleted');
    }
}
