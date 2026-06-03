<?php

namespace AlwaysCurious\LaravelProjectDevtool;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use AlwaysCurious\LaravelProjectDevtool\Commands\LaravelProjectDevtoolCommand;

class LaravelProjectDevtoolServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-project-devtool')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_project_devtool_table')
            ->hasCommand(LaravelProjectDevtoolCommand::class);
    }
}
