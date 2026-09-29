<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Service;
use App\Models\TransactionCategory;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        $services = Service::query()
            ->with('vendor', 'category')
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->when(request('vendor_uuid'), fn ($q, $uuid) => $q->whereHas('vendor', fn ($vq) => $vq->where('uuid', $uuid)))
            ->when(request('category_id'), fn ($q, $categoryId) => $q->where('category_id', $categoryId))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $vendors = Vendor::query()->orderBy('name')->get();
        $categories = Category::query()->ofType('service')->orderBy('name')->get();

        return view('services.index', [
            'services' => $services,
            'vendors' => $vendors,
            'categories' => $categories,
        ]);
    }

    public function create(): View
    {
        $vendors = Vendor::query()->active()->orderBy('name')->get();
        $categories = Category::query()->ofType('service')->active()->orderBy('name')->get();

        return view('services.create', [
            'vendors' => $vendors,
            'categories' => $categories,
            ...$this->renewalOptions(),
        ]);
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $service = Service::query()->create($this->resolvePayload($request->validated()));

        return redirect()
            ->route('services.show', $service)
            ->with('status', 'service-created');
    }

    public function show(Service $service): View
    {
        $service->load('vendor', 'category', 'account', 'paymentMethod', 'transactionCategory');

        return view('services.show', [
            'service' => $service,
        ]);
    }

    public function edit(Service $service): View
    {
        $service->load('vendor');
        $vendors = Vendor::query()->active()->orderBy('name')->get();
        $categories = Category::query()
            ->ofType('service')
            ->where(fn ($q) => $q->active()->orWhere('id', $service->category_id))
            ->orderBy('name')
            ->get();

        return view('services.edit', [
            'service' => $service,
            'vendors' => $vendors,
            'categories' => $categories,
            ...$this->renewalOptions(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function renewalOptions(): array
    {
        return [
            'accounts' => Account::query()->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::query()->orderBy('name')->get(),
            'expenseCategories' => TransactionCategory::query()->orderBy('name')->get(),
        ];
    }

    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($this->resolvePayload($request->validated()));

        return redirect()
            ->route('services.show', $service)
            ->with('status', 'service-updated');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function resolvePayload(array $payload): array
    {
        $payload['vendor_id'] = Vendor::query()->where('uuid', $payload['vendor_uuid'])->firstOrFail()->id;

        foreach (['account' => Account::class, 'payment_method' => PaymentMethod::class, 'transaction_category' => TransactionCategory::class] as $key => $model) {
            if (array_key_exists($key.'_uuid', $payload)) {
                $payload[$key.'_id'] = filled($payload[$key.'_uuid']) ? $model::query()->where('uuid', $payload[$key.'_uuid'])->value('id') : null;
            }

            unset($payload[$key.'_uuid']);
        }

        // The day the renewal falls on is remembered, so the 31st survives short months.
        if (array_key_exists('next_renewal_date', $payload)) {
            $payload['renewal_anchor_day'] = filled($payload['next_renewal_date']) ? Carbon::parse($payload['next_renewal_date'])->day : null;
        }

        unset($payload['vendor_uuid']);

        return $payload;
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return redirect()
            ->route('services.index')
            ->with('status', 'service-deleted');
    }
}
