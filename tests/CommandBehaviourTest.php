<?php

use AlwaysCurious\LaravelProjectDevtool\Events\DatabaseMigrated;
use AlwaysCurious\LaravelProjectDevtool\Tests\TestSeeder;
use Illuminate\Support\Facades\Event;

it('skips confirmation prompts with --force', function () {
    // No expectsConfirmation(): if the command prompted, this would hang/fail.
    $this->artisan('project:dev', ['--setup' => true, '--force' => true])
        ->assertExitCode(0);
});

it('prompts for the named connection and database without --force', function () {
    $this->artisan('project:dev', ['--setup' => true])
        ->expectsConfirmation(
            "This will DROP ALL TABLES on connection 'testing' (database ':memory:') and reseed. Continue?",
            'no'
        )
        ->expectsOutputToContain('Aborted — nothing was changed.')
        ->assertExitCode(1);
});

it('proceeds when the confirmation is accepted', function () {
    TestSeeder::$runs = 0;

    $this->artisan('project:dev', ['--setup' => true])
        ->expectsConfirmation(
            "This will DROP ALL TABLES on connection 'testing' (database ':memory:') and reseed. Continue?",
            'yes'
        )
        ->assertExitCode(0);

    expect(TestSeeder::$runs)->toBe(1);
});

it('prints help and runs nothing destructive with no action flag', function () {
    $migrated = false;
    Event::listen(DatabaseMigrated::class, function () use (&$migrated) {
        $migrated = true;
    });

    $this->artisan('project:dev')
        ->expectsOutputToContain('Available actions:')
        ->expectsOutputToContain('Fresh reset')
        ->expectsOutputToContain('php artisan project:dev --setup --new')
        ->assertExitCode(0);

    expect($migrated)->toBeFalse();
});
