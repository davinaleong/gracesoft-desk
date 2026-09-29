<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSystemSettingsRequest;
use App\Models\SystemSetting;
use App\Services\InvoiceSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SystemSettingsController extends Controller
{
    public function edit(): View
    {
        $settings = SystemSetting::getMappedValues();

        return view('settings.system', [
            'settings' => [
                'company_name' => $settings->get('company_name', config('app.name')),
                'company_email' => $settings->get('company_email', ''),
                'default_currency' => $settings->get('default_currency', 'SGD'),
                'timezone' => $settings->get('timezone', config('app.timezone', 'UTC')),
                'locale' => $settings->get('locale', config('app.locale', 'en')),
                'default_hourly_rate' => $settings->get('default_hourly_rate', '0.00'),
                'company_address' => $settings->get('company_address', ''),
                'gst_registration_number' => $settings->get('gst_registration_number', ''),
                'gst_rate' => $settings->get('gst_rate', InvoiceSettings::DEFAULT_GST_RATE),
                'payment_terms_days' => $settings->get('payment_terms_days', InvoiceSettings::DEFAULT_PAYMENT_TERMS_DAYS),
                'invoice_footer' => $settings->get('invoice_footer', ''),
                'archive_mode' => in_array(strtolower((string) $settings->get('archive_mode', '0')), ['1', 'true', 'yes', 'on'], true),
            ],
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        SystemSetting::upsertValues($request->validated());

        return redirect()
            ->route('settings.system.edit')
            ->with('status', 'system-settings-updated');
    }
}
