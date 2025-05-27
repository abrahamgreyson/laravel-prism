<?php

namespace Abe\Prism;

use Abe\Prism\Commands\InstallCommand;
use Abe\Prism\Commands\ListCommand;
use Abe\Prism\Commands\StatusCommand;
use Abe\Prism\Commands\DisableCommand;
use Abe\Prism\Commands\EnableCommand;
use Abe\Prism\Commands\UninstallCommand;
use Abe\Prism\Commands\DoctorCommand;
use Abe\Prism\Commands\CleanCommand;
use Abe\Prism\Commands\ResetCommand;
use Abe\Prism\Extensions\TelescopeExtension;
use Abe\Prism\Support\ExtensionManager;
use Abe\Prism\Support\LaravelConfigurator;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PrismServiceProvider extends PackageServiceProvider
{
    protected ExtensionManager $extensionManager;

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */

        $package->name('laravel-prism')
            ->hasConfigFile('prism')
            ->hasCommands([
                InstallCommand::class,
                ListCommand::class,
                StatusCommand::class,
                DisableCommand::class,
                EnableCommand::class,
                UninstallCommand::class,
                DoctorCommand::class,
                CleanCommand::class,
                ResetCommand::class,
            ]);
    }

    public function registeringPackage(): void
    {
        $this->registerSnowflake();
        $this->registerExtensionManager();
    }

    public function bootingPackage(): void
    {
        // 配置 Laravel 默认行为
        LaravelConfigurator::configure();

        // 启动所有扩展
        $this->extensionManager->bootAll();

        // 注册计划任务
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $this->extensionManager->scheduleAll($schedule);
        });
    }

    /**
     * Register snowflake instance.
     */
    protected function registerSnowflake(): void
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
     * Register extension manager and all extensions.
     */
    protected function registerExtensionManager(): void
    {
        $this->extensionManager = new ExtensionManager($this->app);
        
        // 注册所有扩展
        $this->registerExtensions();
        
        // 注册所有扩展的服务
        $this->extensionManager->registerAll();
    }

    /**
     * Register all available extensions.
     */
    protected function registerExtensions(): void
    {
        // 注册 Telescope 扩展
        $this->extensionManager->register(new TelescopeExtension());
        
        // 未来可以在这里添加更多扩展
        // $this->extensionManager->register(new OctaneExtension());
        // $this->extensionManager->register(new HorizonExtension());
    }
}
