<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Project;
use App\Models\RetainerPeriod;
use App\Models\TimeEntry;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Drafts a retainer's monthly invoice: the fixed monthly amount plus any overage.
 *
 * Hours used first draw down hours carried over from last month, then this month's included hours.
 * With rollover on, only this month's unused included hours carry forward, and for one month only.
 */
class RetainerBillingService
{
    public function __construct(
        private InvoiceService $invoices,
        private InvoiceSettings $settings,
    ) {}

    /**
     * The month to bill by default: last month, in the system timezone.
     */
    public function defaultPeriod(): string
    {
        return CarbonImmutable::now((string) config('app.timezone'))->startOfMonth()->subMonth()->format('Y-m');
    }

    /**
     * @return array{included: float, rollover_in: float, used: float, overage: float, rollover_out: float}
     */
    public function calculate(Project $project, string $period): array
    {
        $included = (float) $project->retainer_included_hours;
        $rolloverIn = 0.0;

        if ($project->retainer_rollover) {
            $previous = CarbonImmutable::createFromFormat('!Y-m', $period)->subMonth()->format('Y-m');
            $rolloverIn = (float) RetainerPeriod::query()
                ->where('project_id', $project->id)
                ->where('period', $previous)
                ->value('rollover_out_hours');
        }

        $used = round($this->monthEntries($project, $period)->sum('duration_minutes') / 60, 2);

        $usedAfterRollover = max(0.0, $used - $rolloverIn);
        $overage = round(max(0.0, $usedAfterRollover - $included), 2);
        $rolloverOut = $project->retainer_rollover ? round(max(0.0, $included - $usedAfterRollover), 2) : 0.0;

        return [
            'included' => $included,
            'rollover_in' => $rolloverIn,
            'used' => $used,
            'overage' => $overage,
            'rollover_out' => $rolloverOut,
        ];
    }

    /**
     * Creates the month's draft once. Returns null when it already exists or the project can't be billed.
     */
    public function draftFor(Project $project, string $period): ?Invoice
    {
        if ($project->billing_model !== Project::BILLING_RETAINER || $project->client_id === null) {
            return null;
        }

        return DB::transaction(function () use ($project, $period): ?Invoice {
            $exists = RetainerPeriod::query()
                ->where('project_id', $project->id)
                ->where('period', $period)
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                return null;
            }

            $numbers = $this->calculate($project, $period);
            $label = CarbonImmutable::createFromFormat('!Y-m', $period)->format('M Y');
            $client = $project->client;

            $invoice = Invoice::query()->create([
                'client_id' => $client->id,
                'status' => Invoice::STATUS_DRAFT,
                'currency' => $client->currency ?: $this->settings->currency(),
                'payment_terms_days' => $this->settings->paymentTermsDays(),
                'gst_rate' => $this->settings->gstRate(),
                'notes' => __('Retainer for :month.', ['month' => $label]),
            ]);

            $retainerLine = $invoice->lines()->create([
                'project_id' => $project->id,
                'type' => InvoiceLine::TYPE_RETAINER,
                'description' => __(':project retainer — :month (:hours h included, :used h used)', [
                    'project' => $project->name,
                    'month' => $label,
                    'hours' => number_format($numbers['included'] + $numbers['rollover_in'], 2),
                    'used' => number_format($numbers['used'], 2),
                ]),
                'quantity' => '1.00',
                'unit_price' => $project->retainer_monthly_amount,
                'amount' => $project->retainer_monthly_amount,
                'sort_order' => 1,
            ]);

            if ($numbers['overage'] > 0 && (float) $project->retainer_overage_rate > 0) {
                $rateCents = Money::toCents($project->retainer_overage_rate);

                $invoice->lines()->create([
                    'project_id' => $project->id,
                    'type' => InvoiceLine::TYPE_RETAINER,
                    'description' => __(':project overage — :month', ['project' => $project->name, 'month' => $label]),
                    'quantity' => number_format($numbers['overage'], 2, '.', ''),
                    'unit_price' => Money::fromCents($rateCents),
                    'amount' => Money::fromCents(Money::multiply($numbers['overage'], $rateCents)),
                    'sort_order' => 2,
                ]);
            }

            // The month's time is covered by the retainer, so it is locked like any invoiced time.
            foreach ($this->monthEntries($project, $period)->whereNull('invoice_line_id')->get() as $entry) {
                $entry->forceFill(['invoice_line_id' => $retainerLine->id])->save();
            }

            $this->invoices->recalculate($invoice);

            RetainerPeriod::query()->create([
                'project_id' => $project->id,
                'period' => $period,
                'invoice_id' => $invoice->id,
                'included_hours' => $numbers['included'],
                'rollover_in_hours' => $numbers['rollover_in'],
                'hours_used' => $numbers['used'],
                'overage_hours' => $numbers['overage'],
                'rollover_out_hours' => $numbers['rollover_out'],
            ]);

            return $invoice;
        });
    }

    /**
     * Entry dates are calendar dates in the system timezone, so the month is a plain date range.
     *
     * @return Builder<TimeEntry>
     */
    private function monthEntries(Project $project, string $period): Builder
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $period);

        return TimeEntry::query()
            ->where('project_id', $project->id)
            ->whereDate('entry_date', '>=', $start->toDateString())
            ->whereDate('entry_date', '<=', $start->endOfMonth()->toDateString());
    }
}
