<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\TimeEntry;
use App\Models\Transaction;
use App\Observers\AuditableObserver;
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
        //
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
