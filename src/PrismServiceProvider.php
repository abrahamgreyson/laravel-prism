<?php

namespace Abe\Prism;

use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
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
        $package->name('laravel-prism');
    }

    public function register(): void
    {
        // Telescope can be run only in local
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
        }
        // Telescope filter
        if ($this->app->environment('local') && class_exists(\App\Providers\TelescopeServiceProvider::class)) {
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
        // snowflake setting
        $this->app->singleton('snowflake', function ($app) {
            return (new \Godruoyi\Snowflake\Snowflake)
                // the day I started rewrite this project
                ->setStartTimeStamp(strtotime('2025-02-14') * 1000)
                ->setSequenceResolver(new \Godruoyi\Snowflake\LaravelSequenceResolver($app->get('cache.store')));
        });
    }

    public function boot(): void
    {
        // disabled resource wrapping
        JsonResource::withoutWrapping();

        // use immutable dates
        Date::use(CarbonImmutable::class);

        // 注册计划任务
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $this->registerTelescopePruneCommand($schedule);
        });
    }

    /**
     * 注册 Telescope 清理命令到计划任务
     */
    protected function registerTelescopePruneCommand(Schedule $schedule): void
    {
        // 检查是否安装并注册了 Telescope
        if (class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $schedule->command('telescope:prune --hours=24')->daily()->at('02:00');
        }
    }
}
