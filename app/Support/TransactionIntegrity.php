<?php

namespace App\Support;

class TransactionIntegrity
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, string>>
     */
    public static function validate(array $payload): array
    {
        $errors = [];

        $type = strtolower((string) ($payload['type'] ?? ''));
        $direction = strtolower((string) ($payload['direction'] ?? ''));
        $amount = (float) ($payload['amount'] ?? 0);
        $gstAmount = (float) ($payload['gst_amount'] ?? 0);

        if ($type === 'income' && $direction !== 'in') {
            $errors['direction'][] = 'Income transactions must use direction "in".';
        }

        if ($type === 'expense' && $direction !== 'out') {
            $errors['direction'][] = 'Expense transactions must use direction "out".';
        }

        if ($gstAmount > $amount) {
            $errors['gst_amount'][] = 'GST amount cannot exceed transaction amount.';
        }

        return $errors;
    }
}
