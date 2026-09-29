<?php

namespace App\Http\Requests;

/**
 * Shared validation for the repeatable manual-line rows on the invoice forms.
 */
class ManualInvoiceLines
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'manual_lines' => ['array', 'max:50'],
            'manual_lines.*.description' => ['required', 'string', 'max:500'],
            'manual_lines.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:99999999', 'decimal:0,2'],
            'manual_lines.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
        ];
    }

    /**
     * Drops rows the user left completely empty.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function withoutBlankRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, fn ($row): bool => is_array($row)
            && collect($row)->only(['description', 'quantity', 'unit_price'])->filter(fn ($value) => filled($value))->isNotEmpty()));
    }
}
