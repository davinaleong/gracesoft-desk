<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\Transaction;
use App\Observers\AuditableObserver;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

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
