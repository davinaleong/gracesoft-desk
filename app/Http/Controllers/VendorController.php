<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVendorRequest;
use App\Http\Requests\UpdateVendorRequest;
use App\Models\Category;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function index(): View
    {
        $vendors = Vendor::query()
            ->with('category')
            ->withCount('services')
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('vendors.index', [
            'vendors' => $vendors,
        ]);
    }

    public function create(): View
    {
        $categories = Category::query()->ofType('vendor')->active()->orderBy('name')->get();

        return view('vendors.create', ['categories' => $categories]);
    }

    public function store(StoreVendorRequest $request): RedirectResponse
    {
        $vendor = Vendor::query()->create($request->validated());

        return redirect()
            ->route('vendors.show', $vendor)
            ->with('status', 'vendor-created');
    }

    public function show(Vendor $vendor): View
    {
        $vendor->load('services.category', 'category');

        return view('vendors.show', [
            'vendor' => $vendor,
        ]);
    }

    public function edit(Vendor $vendor): View
    {
        $categories = Category::query()
            ->ofType('vendor')
            ->where(fn ($q) => $q->active()->orWhere('id', $vendor->category_id))
            ->orderBy('name')
            ->get();

        return view('vendors.edit', [
            'vendor' => $vendor,
            'categories' => $categories,
        ]);
    }

    public function update(UpdateVendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $vendor->update($request->validated());

        return redirect()
            ->route('vendors.show', $vendor)
            ->with('status', 'vendor-updated');
    }

    public function destroy(Vendor $vendor): RedirectResponse
    {
        $activeServices = $vendor->services()->where('status', 'active')->count();

        if ($activeServices > 0) {
            return redirect()
                ->route('vendors.show', $vendor)
                ->with('error', 'Cannot delete a vendor with active services. Please cancel all services first.');
        }

        $vendor->delete();

        return redirect()
            ->route('vendors.index')
            ->with('status', 'vendor-deleted');
    }
}
