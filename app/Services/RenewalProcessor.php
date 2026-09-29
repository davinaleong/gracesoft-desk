<?php

namespace App\Services;

use App\Mail\RenewalReminderMail;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Support\RenewalSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Daily renewal housekeeping: pending expenses on renewal dates, date advancement and reminders.
 * Paused and cancelled services are never touched.
 */
class RenewalProcessor
{
    /**
     * Create pending expenses for every renewal date that has arrived and move each service to its next date.
     *
     * @return int transactions created
     */
    public function processDue(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $created = 0;

        $due = Service::query()
            ->where('status', 'active')
            ->whereNotNull('billing_cycle')
            ->whereNotNull('next_renewal_date')
            ->whereDate('next_renewal_date', '<=', $today->toDateString())
            ->with('vendor')
            ->get();

        foreach ($due as $service) {
            $created += DB::transaction(function () use ($service, $today): int {
                /** @var Service $service */
                $service = Service::query()->whereKey($service->id)->lockForUpdate()->firstOrFail();
                $count = 0;
                $renewal = CarbonImmutable::instance($service->next_renewal_date);

                // Catch up on any renewals missed while the scheduler wasn't running.
                while ($renewal->lessThanOrEqualTo($today)) {
                    if ($service->auto_create_expense && $service->account_id !== null) {
                        $count += $this->createExpense($service, $renewal) ? 1 : 0;
                    }

                    $renewal = RenewalSchedule::next($renewal, (string) $service->billing_cycle, $service->renewal_anchor_day);
                }

                $service->update(['next_renewal_date' => $renewal->toDateString()]);

                return $count;
            });
        }

        return $created;
    }

    /**
     * Send one reminder per renewal, the configured number of days ahead.
     *
     * @return int reminders sent
     */
    public function sendReminders(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $recipients = User::query()->pluck('email')->filter()->all();

        if ($recipients === []) {
            return 0;
        }

        $sent = 0;

        $candidates = Service::query()
            ->where('status', 'active')
            ->whereNotNull('billing_cycle')
            ->whereNotNull('next_renewal_date')
            ->whereDate('next_renewal_date', '>=', $today->toDateString())
            ->with('vendor')
            ->get();

        foreach ($candidates as $service) {
            $renewal = CarbonImmutable::instance($service->next_renewal_date);

            if ($renewal->greaterThan($today->addDays($service->reminder_days_before))) {
                continue;
            }

            if ($service->last_reminded_for?->isSameDay($renewal)) {
                continue;
            }

            try {
                Mail::to($recipients)->send(new RenewalReminderMail($service));
                $service->update(['last_reminded_for' => $renewal->toDateString()]);
                $sent++;
            } catch (Throwable $exception) {
                Log::warning('Renewal reminder failed', ['service' => $service->uuid, 'error' => $exception->getMessage()]);
            }
        }

        return $sent;
    }

    private function createExpense(Service $service, CarbonImmutable $renewal): bool
    {
        $exists = Transaction::withTrashed()
            ->where('service_id', $service->id)
            ->whereDate('service_renewal_date', $renewal->toDateString())
            ->exists();

        if ($exists) {
            return false;
        }

        Transaction::query()->create([
            'account_id' => $service->account_id,
            'transaction_category_id' => $service->transaction_category_id,
            'payment_method_id' => $service->payment_method_id,
            'service_id' => $service->id,
            'service_renewal_date' => $renewal->toDateString(),
            'type' => 'expense',
            'direction' => 'out',
            'status' => 'pending',
            'transaction_date' => $renewal->toDateString(),
            'reference' => $service->service_code,
            'description' => __(':vendor :service renewal', ['vendor' => $service->vendor?->name, 'service' => $service->name]),
            'amount' => $service->expected_amount ?? 0,
            'gst_amount' => 0,
        ]);

        return true;
    }
}
