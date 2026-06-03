<?php

use AlwaysCurious\LaravelProjectDevtool\Events\AssetsBuilding;
use AlwaysCurious\LaravelProjectDevtool\Events\CachesCleared;
use AlwaysCurious\LaravelProjectDevtool\Events\DatabaseMigrated;
use AlwaysCurious\LaravelProjectDevtool\Events\DatabaseSeeded;
use AlwaysCurious\LaravelProjectDevtool\Events\SetupCompleted;
use AlwaysCurious\LaravelProjectDevtool\Events\SetupStarting;
use AlwaysCurious\LaravelProjectDevtool\Tests\TestSeeder;
use Illuminate\Support\Facades\Event;

/**
 * Records every lifecycle event as it fires so tests can assert exact order.
 *
 * @return array<int, string> a reference-shared log; spread the same array in.
 */
function recordLifecycle(array &$log): void
{
    $events = [
        SetupStarting::class,
        CachesCleared::class,
        DatabaseMigrated::class,
        DatabaseSeeded::class,
        AssetsBuilding::class,
        SetupCompleted::class,
    ];

    foreach ($events as $event) {
        Event::listen($event, function ($e) use (&$log, $event) {
            $log[] = class_basename($event);
        });
    }
}

it('fires every lifecycle event in the exact documented order during --setup', function () {
    $log = [];
    recordLifecycle($log);

    $this->artisan('project:dev', ['--setup' => true, '--force' => true])
        ->assertExitCode(0);

    expect($log)->toBe([
        'SetupStarting',
        'CachesCleared',
        'DatabaseMigrated',
        'DatabaseSeeded',
        'AssetsBuilding',
        'SetupCompleted',
    ]);
});

it('runs the configured seeder during --setup', function () {
    TestSeeder::$runs = 0;

    $this->artisan('project:dev', ['--setup' => true, '--force' => true])
        ->assertExitCode(0);

    expect(TestSeeder::$runs)->toBe(1);
});

it('prints the engine completion summary', function () {
    $this->artisan('project:dev', ['--setup' => true, '--force' => true])
        ->expectsOutputToContain('✔ Dev setup complete.')
        ->expectsOutputToContain('skipped (pass --new)')
        ->assertExitCode(0);
});
