<?php

use AlwaysCurious\LaravelProjectDevtool\Tests\FakeProjectDev;
use AlwaysCurious\LaravelProjectDevtool\Tests\TestSeeder;

/**
 * Swap the real ProjectDev for the fake (stubbed process runner) so the build
 * and dependency-install steps can be asserted without shelling out.
 */
function useFakeProjectDev(): FakeProjectDev
{
    $fake = new FakeProjectDev;
    app()->instance(FakeProjectDev::class, $fake);

    // Artisan resolves console commands from the container by class name when
    // registered via resolveCommands; bind the project:dev name to the fake.
    app('Illuminate\Contracts\Console\Kernel')->registerCommand($fake);

    return $fake;
}

it('skips the asset build when the build command is empty', function () {
    config()->set('project-devtool.build', null);

    $this->artisan('project:dev', ['--setup' => true, '--force' => true])
        ->expectsOutputToContain('Skipping asset build')
        ->assertExitCode(0);
});

it('runs the configured asset build command', function () {
    config()->set('project-devtool.build', ['npm', 'run', 'build']);
    $fake = useFakeProjectDev();

    $this->artisan('project:dev', ['--setup' => true, '--force' => true])
        ->assertExitCode(0);

    $labels = array_column($fake->calls, 'label');
    expect($labels)->toContain('asset build');

    $build = collect($fake->calls)->firstWhere('label', 'asset build');
    expect($build['command'])->toBe(['npm', 'run', 'build']);
});

it('installs dependencies with --new and bails on a failing process', function () {
    config()->set('project-devtool.build', null);
    config()->set('project-devtool.install', [
        'composer' => ['composer', 'install'],
        'npm' => ['npm', 'install'],
    ]);

    $fake = useFakeProjectDev();
    $fake->failOn = 'composer install';

    TestSeeder::$runs = 0;

    $this->artisan('project:dev', ['--setup' => true, '--new' => true, '--force' => true])
        ->expectsOutputToContain('Failed: composer install')
        ->assertExitCode(1);

    // It bailed before migrate/seed.
    expect(TestSeeder::$runs)->toBe(0);

    $labels = array_column($fake->calls, 'label');
    expect($labels)->toBe(['composer install']); // npm install never reached
});

it('reports installed dependencies in the summary with --new', function () {
    config()->set('project-devtool.build', null);
    $fake = useFakeProjectDev();

    $this->artisan('project:dev', ['--setup' => true, '--new' => true, '--force' => true])
        ->expectsOutputToContain('Dependencies: installed')
        ->assertExitCode(0);

    $labels = array_column($fake->calls, 'label');
    expect($labels)->toBe(['composer install', 'npm install']);
});
