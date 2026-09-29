<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Hands out gap-free invoice numbers (INV-YYYY-00001) per calendar year.
 *
 * Must be called inside the database transaction that issues the invoice:
 * the sequence row is locked, and a rollback returns the number unused.
 */
class InvoiceNumberAllocator
{
    public function next(int $year): string
    {
        $sequence = DB::table('invoice_number_sequences')
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            DB::table('invoice_number_sequences')->insert([
                'year' => $year,
                'last_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->format($year, 1);
        }

        $number = (int) $sequence->last_number + 1;

        DB::table('invoice_number_sequences')
            ->where('year', $year)
            ->update(['last_number' => $number, 'updated_at' => now()]);

        return $this->format($year, $number);
    }

    private function format(int $year, int $number): string
    {
        return sprintf('INV-%d-%05d', $year, $number);
    }
}
