<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Models\Document;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(): View
    {
        $clients = Client::query()
            ->withCount('projects')
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('clients.index', [
            'clients' => $clients,
        ]);
    }

    public function create(): View
    {
        return view('clients.create', [
            'defaultCurrency' => (string) (SystemSetting::query()->where('key', 'default_currency')->value('value') ?: 'SGD'),
        ]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $client = Client::query()->create($request->validated());

        return redirect()
            ->route('clients.show', $client)
            ->with('status', 'client-created');
    }

    public function show(Client $client): View
    {
        $client->load([
            'projects' => fn ($q) => $q->orderBy('name'),
            'documents',
        ]);

        $transactions = $client->transactions()
            ->with('project')
            ->latest('transaction_date')
            ->limit(25)
            ->get();

        return view('clients.show', [
            'client' => $client,
            'transactions' => $transactions,
            'invoices' => $client->invoices()->latest('id')->limit(25)->get(),
            'unlinkedDocuments' => Document::query()->whereNull('documentable_id')->orderBy('name')->get(),
        ]);
    }

    public function edit(Client $client): View
    {
        return view('clients.edit', [
            'client' => $client,
            'defaultCurrency' => $client->currency,
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        return redirect()
            ->route('clients.show', $client)
            ->with('status', 'client-updated');
    }
}
