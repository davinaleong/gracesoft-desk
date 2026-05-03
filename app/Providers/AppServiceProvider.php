<?php

namespace App\Providers;

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
