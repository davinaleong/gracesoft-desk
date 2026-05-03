<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSystemSettingsRequest;
use App\Models\SystemSetting;
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
                'default_hourly_rate' => $settings->get('default_hourly_rate', '0.00'),
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
