<?php

namespace App\Providers;

use App\Contracts\CommitSummarizer;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\TimeEntry;
use App\Models\Transaction;
use App\Observers\AuditableObserver;
use App\Services\Ai\AiSettings;
use App\Services\AnthropicCommitSummarizer;
use App\Services\BudgetMonitor;
use App\Services\NullCommitSummarizer;
use App\Services\OpenAiCommitSummarizer;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BudgetMonitor::class);

        // AI stays off (null driver) until switched on in AI settings with a complete provider config.
        $this->app->bind(CommitSummarizer::class, function (): CommitSummarizer {
            $settings = app(AiSettings::class);

            try {
                if (! $settings->isReady()) {
                    return new NullCommitSummarizer;
                }
            } catch (Throwable) {
                return new NullCommitSummarizer;
            }

            return match ($settings->provider()) {
                'openai' => new OpenAiCommitSummarizer(apiKey: $settings->apiKey(), model: $settings->model()),
                'anthropic' => new AnthropicCommitSummarizer(apiKey: (string) $settings->apiKey(), model: $settings->model()),
                'openai_compatible' => new OpenAiCommitSummarizer(
                    apiKey: $settings->apiKey(),
                    model: $settings->model(),
                    baseUrl: (string) $settings->baseUrl(),
                    providerName: 'openai_compatible',
                ),
                default => new NullCommitSummarizer,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            $settings = SystemSetting::query()
                ->whereIn('key', ['timezone', 'locale'])
                ->pluck('value', 'key');

            $timezone = (string) $settings->get('timezone', '');

            if ($timezone !== '' && in_array($timezone, timezone_identifiers_list(), true)) {
                config(['app.timezone' => $timezone]);
                date_default_timezone_set($timezone);
            }

            $locale = trim((string) $settings->get('locale', ''));

            if ($locale !== '') {
                config(['app.locale' => $locale]);
                App::setLocale($locale);
            }
        } catch (Throwable) {
            // Ignore early boot/migration states where settings table is not available yet.
        }

        Client::observe(AuditableObserver::class);
        Invoice::observe(AuditableObserver::class);
        InvoiceLine::observe(AuditableObserver::class);
        Project::observe(AuditableObserver::class);
        TimeEntry::observe(AuditableObserver::class);
        Transaction::observe(AuditableObserver::class);

        Blade::directive('deskMoney', function (string $expression): string {
            return "<?php echo \\App\\Support\\DeskFormat::money({$expression}); ?>";
        });

        Blade::directive('deskDate', function (string $expression): string {
            return "<?php echo \\App\\Support\\DeskFormat::date({$expression}); ?>";
        });

        Blade::directive('deskDuration', function (string $expression): string {
            return "<?php echo \\App\\Support\\DeskFormat::durationMinutes({$expression}); ?>";
        });
    }
}
