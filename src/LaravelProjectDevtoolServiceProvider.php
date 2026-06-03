<?php

namespace AlwaysCurious\LaravelProjectDevtool;

use AlwaysCurious\LaravelProjectDevtool\Commands\MakeDevHook;
use AlwaysCurious\LaravelProjectDevtool\Commands\ProjectDev;
use AlwaysCurious\LaravelProjectDevtool\Events\DatabaseMigrated;
use AlwaysCurious\LaravelProjectDevtool\Recipes\GenerateShieldPermissions;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

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
            ->hasConfigFile('project-devtool')
            ->hasCommand(ProjectDev::class)
            ->hasCommand(MakeDevHook::class);
    }

    public function packageBooted(): void
    {
        // Publish the make:dev-hook stub so apps can customise it if desired.
        $this->publishes([
            __DIR__.'/../resources/stubs/dev-hook.stub' => base_path('stubs/dev-hook.stub'),
        ], 'project-devtool-stubs');

        $this->registerRecipes();
    }

    /**
     * Conditionally register opt-in recipe listeners. Each recipe stays fully
     * optional and self-guarding — nothing is wired up unless its config flag
     * is explicitly enabled.
     */
    protected function registerRecipes(): void
    {
        if (config('project-devtool.recipes.shield.enabled', false)) {
            Event::listen(DatabaseMigrated::class, GenerateShieldPermissions::class);
        }
    }
}
