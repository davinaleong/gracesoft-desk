<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('desk:recalculate-transaction-codes')]
#[Description('One-time pass: regenerate all transaction codes using the standard TRX-{date}-{random} format')]
class DeskRecalculateTransactionCodesCommand extends Command
{
    public function handle(): int
    {
        $count = Transaction::withTrashed()->count();

        if ($count === 0) {
            $this->info('No transactions found. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info("Recalculating transaction codes for {$count} transaction(s)...");

        Transaction::withTrashed()->each(function (Transaction $transaction): void {
            $date = Carbon::parse($transaction->getRawOriginal('transaction_date'));

            do {
                $candidate = sprintf('TRX-%s-%s', $date->format('Ymd'), strtoupper(Str::random(6)));
            } while (
                Transaction::withTrashed()
                    ->where('transaction_code', $candidate)
                    ->where('id', '!=', $transaction->id)
                    ->exists()
            );

            DB::table('transactions')
                ->where('id', $transaction->id)
                ->update(['transaction_code' => $candidate]);
        });

        $this->info('Transaction codes recalculated successfully.');

        return self::SUCCESS;
    }
}
