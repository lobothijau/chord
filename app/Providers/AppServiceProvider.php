<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // @assetv('js/form.js') — asset URL with mtime cache-buster so
        // deploys invalidate browser-cached JS/CSS (no build pipeline here).
        Blade::directive('assetv', function (string $expression) {
            return "<?php echo asset($expression) . '?v=' . filemtime(public_path($expression)); ?>";
        });
    }
}
