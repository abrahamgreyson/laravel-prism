<?php

namespace Abe\Prism;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Abe\Prism\Commands\PrismCommand;

class PrismServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-prism')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_prism_table')
            ->hasCommand(PrismCommand::class);
    }
}
