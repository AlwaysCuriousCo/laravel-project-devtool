<?php

use AlwaysCurious\LaravelProjectDevtool\Events\AbortSetup;
use AlwaysCurious\LaravelProjectDevtool\Events\DatabaseMigrated;
use AlwaysCurious\LaravelProjectDevtool\Events\DatabaseSeeded;
use AlwaysCurious\LaravelProjectDevtool\Events\SetupStarting;
use AlwaysCurious\LaravelProjectDevtool\Tests\TestSeeder;
use Illuminate\Support\Facades\Event;

it('aborts cleanly when a SetupStarting listener throws AbortSetup', function () {
    TestSeeder::$runs = 0;
    $migrated = false;

    Event::listen(SetupStarting::class, function () {
        throw new AbortSetup('Refusing: super-admin password is not set.');
    });
    Event::listen(DatabaseMigrated::class, function () use (&$migrated) {
        $migrated = true;
    });

    $this->artisan('project:dev', ['--setup' => true, '--force' => true])
        ->expectsOutputToContain('Refusing: super-admin password is not set.')
        ->expectsOutputToContain('Nothing was changed.')
        ->assertExitCode(1);

    // Nothing destructive ran: no migrate, no seed.
    expect($migrated)->toBeFalse()
        ->and(TestSeeder::$runs)->toBe(0);
});

it('swallows AbortSetup thrown outside the pre-flight point and completes the run', function () {
    TestSeeder::$runs = 0;

    Event::listen(DatabaseSeeded::class, function () {
        throw new AbortSetup('too late to abort');
    });

    $this->artisan('project:dev', ['--setup' => true, '--force' => true])
        ->expectsOutputToContain('tried to abort outside a pre-flight point')
        ->expectsOutputToContain('✔ Dev setup complete.')
        ->assertExitCode(0);

    // The run still completed: seeding happened.
    expect(TestSeeder::$runs)->toBe(1);
});

it('does not let a broken hook kill the run', function () {
    Event::listen(DatabaseSeeded::class, function () {
        throw new RuntimeException('boom from a custom hook');
    });

    $this->artisan('project:dev', ['--setup' => true, '--force' => true])
        ->expectsOutputToContain('failed: boom from a custom hook')
        ->expectsOutputToContain('✔ Dev setup complete.')
        ->assertExitCode(0);
});
