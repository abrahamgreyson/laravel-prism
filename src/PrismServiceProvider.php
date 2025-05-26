<?php

namespace Abe\Prism;

use Abe\Prism\Commands\InstallCommand;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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

        $package->name('laravel-prism')
            ->hasConfigFile('prism')
            ->hasCommand(InstallCommand::class);
    }

    public function registeringPackage(): void
    {
        $this->registerSnowflake();
        $this->registerTelescope();
    }

    /**
     * Register snowflake instance.
     */
    public function registerSnowflake(): void
    {
        // snowflake setting
        $this->app->singleton('snowflake', function ($app) {
            return (new \Godruoyi\Snowflake\Snowflake)
                // the day I started rewrite this project
                ->setStartTimeStamp(strtotime('2025-02-14') * 1000)
                ->setSequenceResolver(new \Godruoyi\Snowflake\LaravelSequenceResolver($app->get('cache.store')));
        });
    }

    /**
     * Register Telescope services in local environment.
     */
    public function registerTelescope(): void
    {
        // Telescope can be run only in local
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
        }
        // Telescope filter
        if ($this->app->environment('local') && class_exists(\App\Providers\TelescopeServiceProvider::class)) {
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    public function bootingPackage(): void
    {
        // disabled resource wrapping
        JsonResource::withoutWrapping();

        // 根据配置决定是否使用不可变日期
        if (config('prism.immutable_date', true)) {
            Date::use(CarbonImmutable::class);
        }

        // 根据配置决定是否启用模型严格模式
        if (config('prism.model_strict', true)) {
            // 在非生产环境启用所有严格检查，生产环境不启用懒加载检查
            Model::shouldBeStrict(! app()->isProduction());
        }

        // 根据配置决定是否解除模型保护
        if (config('prism.unguard_models', true)) {
            Model::unguard();
        }

        // 根据配置决定是否禁止破坏性命令（仅在生产环境）
        if (config('prism.prohibit_destructive_commands', true)) {
            DB::prohibitDestructiveCommands(app()->isProduction());
        }

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
