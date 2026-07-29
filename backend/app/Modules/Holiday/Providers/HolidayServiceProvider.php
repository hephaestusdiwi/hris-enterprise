<?php

namespace App\Modules\Holiday\Providers;

use App\Modules\Holiday\Contracts\NationalHolidayProviderInterface;
use App\Modules\Holiday\Support\NationalHolidayProviderFactory;
use Illuminate\Support\ServiceProvider;

class HolidayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind interface -> implementasi. Controller & Service cukup type-hint
        // NationalHolidayProviderInterface, tidak pernah tahu implementasi konkretnya.
        $this->app->bind(NationalHolidayProviderInterface::class, function () {
            return NationalHolidayProviderFactory::make();
        });
    }
}