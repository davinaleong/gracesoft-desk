<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Collection;

/**
 * Read-only view of the system settings that shape an invoice.
 */
class InvoiceSettings
{
    public const DEFAULT_GST_RATE = '9.00';

    public const DEFAULT_PAYMENT_TERMS_DAYS = 30;

    /** @var Collection<string, string|null>|null */
    private ?Collection $values = null;

    public function companyName(): string
    {
        return (string) ($this->get('company_name') ?: config('app.name'));
    }

    public function companyEmail(): ?string
    {
        return $this->get('company_email') ?: null;
    }

    public function companyAddress(): ?string
    {
        return $this->get('company_address') ?: null;
    }

    public function gstRegistrationNumber(): ?string
    {
        $value = trim((string) $this->get('gst_registration_number'));

        return $value === '' ? null : $value;
    }

    /**
     * The GST rate to charge: zero unless the business is GST-registered.
     */
    public function gstRate(): string
    {
        if ($this->gstRegistrationNumber() === null) {
            return '0.00';
        }

        return number_format((float) ($this->get('gst_rate') ?? self::DEFAULT_GST_RATE), 2, '.', '');
    }

    public function paymentTermsDays(): int
    {
        return (int) ($this->get('payment_terms_days') ?? self::DEFAULT_PAYMENT_TERMS_DAYS);
    }

    public function footer(): ?string
    {
        return $this->get('invoice_footer') ?: null;
    }

    public function currency(): string
    {
        return (string) ($this->get('default_currency') ?: 'SGD');
    }

    private function get(string $key): ?string
    {
        $this->values ??= SystemSetting::getMappedValues();

        $value = $this->values->get($key);

        return $value === null ? null : (string) $value;
    }
}
