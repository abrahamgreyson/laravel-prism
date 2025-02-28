<?php

namespace Abe\Prism;

use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PrismServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name('laravel-prism')->hasConfigFile();
    }

    public function register(): void
    {
        // Telescope can be run only in local
        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
        }
        // snowflake setting
        $this->app->singleton('snowflake', function ($app) {
            return (new \Godruoyi\Snowflake\Snowflake())
                // the day I started rewrite this project
                ->setStartTimeStamp(strtotime('2024-05-20') * 1000)
                ->setSequenceResolver(new \Godruoyi\Snowflake\LaravelSequenceResolver($app->get('cache.store')));
        });
    }

    public function boot(): void
    {
        // disabled resource wrapping
        JsonResource::withoutWrapping();

        // use immutable dates
        Date::use(CarbonImmutable::class);
    }
}
