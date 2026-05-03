<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $transactions = Transaction::query()
            ->with(['account', 'category', 'paymentMethod', 'project'])
            ->latest('transaction_date')
            ->paginate(20);

        return view('transactions.index', [
            'transactions' => $transactions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('transactions.create', [
            'accounts' => Account::query()->orderBy('name')->get(),
            'categories' => TransactionCategory::query()->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::query()->orderBy('name')->get(),
            'projects' => Project::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $payload['net_amount'] = $this->calculateNetAmount($payload);

        $transaction = Transaction::query()->create($payload);

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('status', 'transaction-created');
    }

    /**
     * Display the specified resource.
     */
    public function show(Transaction $transaction): View
    {
        $transaction->load(['account', 'category', 'paymentMethod', 'project']);

        return view('transactions.show', [
            'transaction' => $transaction,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Transaction $transaction): View
    {
        return view('transactions.edit', [
            'transaction' => $transaction,
            'accounts' => Account::query()->orderBy('name')->get(),
            'categories' => TransactionCategory::query()->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::query()->orderBy('name')->get(),
            'projects' => Project::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $payload = $request->validated();
        $payload['net_amount'] = $this->calculateNetAmount($payload);

        $transaction->update($payload);

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('status', 'transaction-updated');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function calculateNetAmount(array $payload): float
    {
        return max(0, ((float) $payload['amount']) - ((float) $payload['gst_amount']));
    }
}
