<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Milestone;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public const GROUP_PER_ENTRY = 'entry';

    public const GROUP_BY_STAGE = 'stage';

    public function __construct(
        private BillableRateResolver $rateResolver,
        private InvoiceSettings $settings,
        private InvoiceNumberAllocator $numberAllocator,
    ) {}

    /**
     * Billable, not-yet-invoiced, non-deleted time entries on a client's projects.
     *
     * @return Builder<TimeEntry>
     */
    public function unbilledEntriesFor(Client $client): Builder
    {
        return TimeEntry::query()
            ->billable()
            ->unbilled()
            ->whereHas('project', fn (Builder $q) => $q->where('client_id', $client->id)
                ->where(fn (Builder $q) => $q->where('billing_model', Project::BILLING_HOURLY)->orWhereNull('billing_model')))
            ->with(['project', 'stage'])
            ->orderBy('entry_date')
            ->orderBy('id');
    }

    /**
     * Pending milestones on the client's fixed-fee projects.
     *
     * @return Builder<Milestone>
     */
    public function unbilledMilestonesFor(Client $client): Builder
    {
        return Milestone::query()
            ->where('status', Milestone::STATUS_PENDING)
            ->whereNull('invoice_line_id')
            ->whereHas('project', fn (Builder $q) => $q->where('client_id', $client->id))
            ->with('project')
            ->orderBy('due_date')
            ->orderBy('sort_order');
    }

    /**
     * @param  array<int, string>  $timeEntryUuids
     * @param  array<int, array{description: string, quantity: string|float, unit_price: string|float}>  $manualLines
     * @param  array<int, string>  $milestoneUuids
     */
    public function createDraft(
        Client $client,
        array $timeEntryUuids,
        string $grouping,
        array $manualLines,
        ?string $notes,
        ?User $user,
        array $milestoneUuids = [],
    ): Invoice {
        return DB::transaction(function () use ($client, $timeEntryUuids, $grouping, $manualLines, $notes, $user, $milestoneUuids): Invoice {
            $entries = $this->lockSelectableEntries($client, $timeEntryUuids);
            $milestones = $this->lockSelectableMilestones($client, $milestoneUuids);

            if ($entries->isEmpty() && $manualLines === [] && $milestones->isEmpty()) {
                throw ValidationException::withMessages([
                    'time_entry_uuids' => __('Choose at least one time entry or add a manual line.'),
                ]);
            }

            $invoice = Invoice::query()->create([
                'client_id' => $client->id,
                'status' => Invoice::STATUS_DRAFT,
                'currency' => $client->currency ?: $this->settings->currency(),
                'payment_terms_days' => $this->settings->paymentTermsDays(),
                'gst_rate' => $this->settings->gstRate(),
                'notes' => $notes,
                'created_by' => $user?->id,
            ]);

            $this->addMilestoneLines($invoice, $milestones);
            $this->addTimeLines($invoice, $entries, $grouping);
            $this->addManualLines($invoice, $manualLines);
            $this->recalculate($invoice);

            return $invoice;
        });
    }

    /**
     * @param  array<int, array{description: string, quantity: string|float, unit_price: string|float}>  $manualLines
     * @param  array<int, string>  $removeLineUuids
     */
    public function updateDraft(Invoice $invoice, array $manualLines, array $removeLineUuids, ?string $notes): Invoice
    {
        return DB::transaction(function () use ($invoice, $manualLines, $removeLineUuids, $notes): Invoice {
            $invoice = $this->lockDraft($invoice);

            $toRemove = $invoice->lines()
                ->where(fn (Builder $q) => $q->whereIn('uuid', $removeLineUuids)->orWhere('type', InvoiceLine::TYPE_MANUAL))
                ->get();

            foreach ($toRemove as $line) {
                $this->releaseLine($line);
                $line->delete();
            }

            $this->addManualLines($invoice, $manualLines);
            $invoice->update(['notes' => $notes]);
            $this->recalculate($invoice);

            return $invoice;
        });
    }

    public function deleteDraft(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $invoice = $this->lockDraft($invoice);

            foreach ($invoice->lines()->get() as $line) {
                $this->releaseLine($line);
                $line->delete();
            }

            $invoice->delete();
        });
    }

    /**
     * Issue a draft: it gets the next number for the year, dates, and a GST snapshot.
     */
    public function issue(Invoice $invoice, ?CarbonInterface $issueDate = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $issueDate): Invoice {
            $invoice = $this->lockDraft($invoice);

            if (! $invoice->lines()->exists()) {
                throw ValidationException::withMessages([
                    'invoice' => __('An invoice needs at least one line before it can be issued.'),
                ]);
            }

            $issueDate ??= now();

            $invoice->forceFill([
                'gst_rate' => $this->settings->gstRate(),
                'gst_registration_number' => $this->settings->gstRegistrationNumber(),
                'footer' => $this->settings->footer(),
                'payment_terms_days' => $this->settings->paymentTermsDays(),
            ]);
            $this->recalculate($invoice);

            $invoice->update([
                'invoice_number' => $this->numberAllocator->next((int) $issueDate->format('Y')),
                'status' => Invoice::STATUS_ISSUED,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $issueDate->copy()->addDays($invoice->payment_terms_days)->toDateString(),
                'issued_at' => now(),
            ]);

            return $invoice;
        });
    }

    /**
     * Record full payment. Creates exactly one income transaction however often it is called.
     *
     * @param  array{account_uuid: string, payment_date: string, payment_method_uuid?: string|null, transaction_category_uuid?: string|null, reference?: string|null}  $data
     */
    public function recordPayment(Invoice $invoice, array $data): Transaction
    {
        return DB::transaction(function () use ($invoice, $data): Transaction {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $existing = Transaction::query()->where('invoice_id', $invoice->id)->first();

            if ($existing !== null) {
                return $existing;
            }

            if (! $invoice->isIssued()) {
                throw ValidationException::withMessages([
                    'invoice' => __('Only issued invoices can be marked as paid.'),
                ]);
            }

            $projectIds = $invoice->lines()->reorder()->whereNotNull('project_id')->distinct()->pluck('project_id');

            $transaction = Transaction::query()->create([
                'account_id' => Account::query()->where('uuid', $data['account_uuid'])->value('id'),
                'payment_method_id' => filled($data['payment_method_uuid'] ?? null)
                    ? PaymentMethod::query()->where('uuid', $data['payment_method_uuid'])->value('id')
                    : null,
                'transaction_category_id' => filled($data['transaction_category_uuid'] ?? null)
                    ? TransactionCategory::query()->where('uuid', $data['transaction_category_uuid'])->value('id')
                    : null,
                'project_id' => $projectIds->count() === 1 ? $projectIds->first() : null,
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'type' => 'income',
                'direction' => 'in',
                'status' => 'completed',
                'transaction_date' => $data['payment_date'],
                'reference' => filled($data['reference'] ?? null) ? $data['reference'] : $invoice->invoice_number,
                'description' => __('Payment for :number', ['number' => $invoice->invoice_number]),
                'amount' => $invoice->total,
                'gst_amount' => $invoice->gst_amount,
                'net_amount' => Money::fromCents(Money::toCents($invoice->total) - Money::toCents($invoice->gst_amount)),
            ]);

            $invoice->update([
                'status' => Invoice::STATUS_PAID,
                'paid_at' => now(),
            ]);

            Milestone::query()
                ->whereIn('invoice_line_id', $invoice->lines()->reorder()->select('id'))
                ->update(['status' => Milestone::STATUS_PAID]);

            return $transaction;
        });
    }

    /**
     * Void a draft or issued invoice. Its time entries become billable again; its number is kept.
     */
    public function void(Invoice $invoice, string $reason): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason): Invoice {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_ISSUED], true)) {
                throw ValidationException::withMessages([
                    'void_reason' => __('Only draft or issued invoices can be voided.'),
                ]);
            }

            foreach ($invoice->lines()->get() as $line) {
                $this->releaseLine($line);
            }

            $invoice->update([
                'status' => Invoice::STATUS_VOID,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            return $invoice;
        });
    }

    /**
     * Recompute line GST and invoice totals in integer cents so lines always sum to the total.
     */
    public function recalculate(Invoice $invoice): void
    {
        $subtotalCents = 0;
        $gstCents = 0;

        foreach ($invoice->lines()->get() as $line) {
            $amountCents = Money::toCents($line->amount);
            $lineGstCents = Money::percentOf($amountCents, $invoice->gst_rate);

            if (Money::toCents($line->gst_amount) !== $lineGstCents) {
                $line->update(['gst_amount' => Money::fromCents($lineGstCents)]);
            }

            $subtotalCents += $amountCents;
            $gstCents += $lineGstCents;
        }

        $invoice->forceFill([
            'subtotal' => Money::fromCents($subtotalCents),
            'gst_amount' => Money::fromCents($gstCents),
            'total' => Money::fromCents($subtotalCents + $gstCents),
        ])->save();
    }

    /**
     * @param  array<int, string>  $uuids
     * @return Collection<int, TimeEntry>
     */
    private function lockSelectableEntries(Client $client, array $uuids): Collection
    {
        $uuids = array_values(array_unique($uuids));

        if ($uuids === []) {
            return new Collection;
        }

        $entries = $this->unbilledEntriesFor($client)
            ->whereIn('uuid', $uuids)
            ->lockForUpdate()
            ->get();

        if ($entries->count() !== count($uuids)) {
            throw ValidationException::withMessages([
                'time_entry_uuids' => __('Some selected time entries are no longer available to invoice. Refresh and try again.'),
            ]);
        }

        return $entries;
    }

    /**
     * @param  array<int, string>  $uuids
     * @return Collection<int, Milestone>
     */
    private function lockSelectableMilestones(Client $client, array $uuids): Collection
    {
        $uuids = array_values(array_unique($uuids));

        if ($uuids === []) {
            return new Collection;
        }

        $milestones = $this->unbilledMilestonesFor($client)->whereIn('uuid', $uuids)->lockForUpdate()->get();

        if ($milestones->count() !== count($uuids)) {
            throw ValidationException::withMessages([
                'milestone_uuids' => __('Some selected milestones were already invoiced. Refresh and try again.'),
            ]);
        }

        return $milestones;
    }

    /**
     * @param  Collection<int, Milestone>  $milestones
     */
    private function addMilestoneLines(Invoice $invoice, Collection $milestones): void
    {
        $sortOrder = (int) $invoice->lines()->reorder()->max('sort_order');

        foreach ($milestones as $milestone) {
            $line = $invoice->lines()->create([
                'project_id' => $milestone->project_id,
                'type' => InvoiceLine::TYPE_MILESTONE,
                'description' => Str::limit(sprintf('%s — %s', $milestone->project?->name ?? __('Project'), $milestone->name), 500, ''),
                'quantity' => '1.00',
                'unit_price' => $milestone->amount,
                'amount' => $milestone->amount,
                'sort_order' => ++$sortOrder,
            ]);

            $milestone->update(['invoice_line_id' => $line->id, 'status' => Milestone::STATUS_INVOICED]);
        }
    }

    /**
     * @param  Collection<int, TimeEntry>  $entries
     */
    private function addTimeLines(Invoice $invoice, Collection $entries, string $grouping): void
    {
        $sortOrder = (int) $invoice->lines()->reorder()->max('sort_order');

        $groups = $grouping === self::GROUP_BY_STAGE
            ? $entries->groupBy(fn (TimeEntry $entry): string => $entry->project_id.'|'.($entry->project_stage_id ?? 0))
            : $entries->map(fn (TimeEntry $entry): Collection => new Collection([$entry]));

        foreach ($groups as $group) {
            /** @var TimeEntry $first */
            $first = $group->first();
            $minutes = (int) $group->sum('duration_minutes');
            $amountCents = $group->sum(fn (TimeEntry $entry): int => Money::toCents($entry->billable_amount));

            $line = $invoice->lines()->create([
                'project_id' => $first->project_id,
                'project_stage_id' => $first->project_stage_id,
                'type' => InvoiceLine::TYPE_TIME,
                'description' => $this->describeTimeLine($group, $grouping),
                'quantity' => number_format($minutes / 60, 2, '.', ''),
                'unit_price' => number_format($this->rateResolver->forProject($first->project), 2, '.', ''),
                'amount' => Money::fromCents($amountCents),
                'sort_order' => ++$sortOrder,
            ]);

            foreach ($group as $entry) {
                $entry->forceFill(['invoice_line_id' => $line->id])->save();
            }
        }
    }

    /**
     * @param  array<int, array{description: string, quantity: string|float, unit_price: string|float}>  $manualLines
     */
    private function addManualLines(Invoice $invoice, array $manualLines): void
    {
        $sortOrder = (int) $invoice->lines()->reorder()->max('sort_order');

        foreach ($manualLines as $manualLine) {
            $unitPriceCents = Money::toCents($manualLine['unit_price']);

            $invoice->lines()->create([
                'type' => InvoiceLine::TYPE_MANUAL,
                'description' => $manualLine['description'],
                'quantity' => number_format((float) $manualLine['quantity'], 2, '.', ''),
                'unit_price' => Money::fromCents($unitPriceCents),
                'amount' => Money::fromCents(Money::multiply($manualLine['quantity'], $unitPriceCents)),
                'sort_order' => ++$sortOrder,
            ]);
        }
    }

    /**
     * @param  Collection<int, TimeEntry>  $group
     */
    private function describeTimeLine(Collection $group, string $grouping): string
    {
        /** @var TimeEntry $first */
        $first = $group->first();
        $project = $first->project?->name ?? __('Project');
        $stage = $first->stage?->name;

        if ($grouping === self::GROUP_BY_STAGE) {
            return Str::limit(sprintf(
                '%s — %s (%d %s)',
                $project,
                $stage ?? __('General'),
                $group->count(),
                Str::plural('entry', $group->count()),
            ), 500, '');
        }

        return Str::limit(trim(sprintf(
            '%s — %s%s: %s',
            $project,
            $stage ? $stage.', ' : '',
            $first->entry_date?->format('d M Y'),
            $first->notes ?? '',
        ), ' :'), 500, '');
    }

    private function releaseLine(InvoiceLine $line): void
    {
        Milestone::query()->where('invoice_line_id', $line->id)->update([
            'invoice_line_id' => null,
            'status' => Milestone::STATUS_PENDING,
        ]);

        foreach ($line->timeEntries()->withTrashed()->get() as $entry) {
            $entry->forceFill(['invoice_line_id' => null])->save();
        }
    }

    private function lockDraft(Invoice $invoice): Invoice
    {
        /** @var Invoice $locked */
        $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

        if (! $locked->isDraft()) {
            throw ValidationException::withMessages([
                'invoice' => __('Issued invoices can\'t be edited.'),
            ]);
        }

        return $locked;
    }
}
