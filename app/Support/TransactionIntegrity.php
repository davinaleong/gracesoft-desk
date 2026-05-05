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
            $errors['direction'][] = 'For income transactions, please choose the "Money In" direction.';
        }

        if ($type === 'expense' && $direction !== 'out') {
            $errors['direction'][] = 'For expense transactions, please choose the "Money Out" direction.';
        }

        if ($gstAmount > $amount) {
            $errors['gst_amount'][] = 'GST cannot be higher than the transaction amount.';
        }

        return $errors;
    }
}
