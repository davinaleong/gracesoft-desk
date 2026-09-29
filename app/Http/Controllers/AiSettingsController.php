<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAiSettingsRequest;
use App\Models\AiRequest;
use App\Models\SystemSetting;
use App\Services\Ai\AiSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

class AiSettingsController extends Controller
{
    public function edit(AiSettings $settings): View
    {
        return view('settings.ai', [
            'settings' => $settings,
            'recentRequests' => AiRequest::query()->with('project')->latest('requested_at')->limit(20)->get(),
        ]);
    }

    public function update(UpdateAiSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $values = [
            'ai_enabled' => (bool) $validated['ai_enabled'],
            'ai_provider' => $validated['ai_provider'] ?? null,
            'ai_model' => $validated['ai_model'] ?? null,
            'ai_base_url' => $validated['ai_base_url'] ?? null,
            'ai_send_file_paths' => (bool) $validated['ai_send_file_paths'],
            'ai_log_retention_days' => (int) $validated['ai_log_retention_days'],
        ];

        // The key is write-only: a blank field keeps the stored key, and it is never sent back to the form.
        if ($request->boolean('clear_api_key')) {
            $values['ai_api_key'] = null;
        } elseif (filled($validated['ai_api_key'] ?? null)) {
            $values['ai_api_key'] = Crypt::encryptString((string) $validated['ai_api_key']);
        }

        SystemSetting::upsertValues($values);

        return redirect()
            ->route('settings.ai.edit')
            ->with('status', 'ai-settings-updated');
    }
}
