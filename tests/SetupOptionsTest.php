<?php

use AlwaysCurious\LaravelProjectDevtool\Events\DatabaseMigrated;
use AlwaysCurious\LaravelProjectDevtool\Events\DatabaseSeeded;
use AlwaysCurious\LaravelProjectDevtool\Tests\TestSeeder;
use Illuminate\Support\Facades\Event;

describe('--dry-run', function () {
    it('makes no changes but still fires the lifecycle events', function () {
        TestSeeder::$runs = 0;
        $migrated = false;
        $sawDryRunFlag = false;

        Event::listen(DatabaseMigrated::class, function (DatabaseMigrated $e) use (&$migrated, &$sawDryRunFlag) {
            $migrated = true;
            $sawDryRunFlag = $e->dryRun;
        });

        $this->artisan('project:dev', ['--setup' => true, '--dry-run' => true])
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('would run: migrate:fresh')
            ->expectsOutputToContain('Dry run complete')
            ->assertExitCode(0);

        // Events fired (so listeners can announce intent)…
        expect($migrated)->toBeTrue()
            ->and($sawDryRunFlag)->toBeTrue()
            // …but nothing destructive actually ran.
            ->and(TestSeeder::$runs)->toBe(0);
    });

    it('does not prompt for confirmation on a dry run', function () {
        // No expectsConfirmation(): a prompt here would fail the test.
        $this->artisan('project:dev', ['--setup' => true, '--dry-run' => true])
            ->assertExitCode(0);
    });
});

describe('--only / --skip', function () {
    it('runs only the named steps', function () {
        TestSeeder::$runs = 0;
        $migrated = false;
        $seeded = false;

        Event::listen(DatabaseMigrated::class, function () use (&$migrated) {
            $migrated = true;
        });
        Event::listen(DatabaseSeeded::class, function () use (&$seeded) {
            $seeded = true;
        });

        $this->artisan('project:dev', ['--setup' => true, '--force' => true, '--only' => 'seed'])
            ->expectsOutputToContain('Steps run: seed')
            ->assertExitCode(0);

        // seed ran; migrate did not.
        expect($seeded)->toBeTrue()
            ->and($migrated)->toBeFalse()
            ->and(TestSeeder::$runs)->toBe(1);
    });

    it('skips the named steps', function () {
        $seeded = false;
        Event::listen(DatabaseSeeded::class, function () use (&$seeded) {
            $seeded = true;
        });

        $this->artisan('project:dev', ['--setup' => true, '--force' => true, '--skip' => 'seed,build'])
            ->assertExitCode(0);

        expect($seeded)->toBeFalse();
    });

    it('does not prompt for confirmation when no destructive step runs', function () {
        // --only=caches touches no data, so there is nothing to confirm.
        $this->artisan('project:dev', ['--setup' => true, '--only' => 'caches'])
            ->assertExitCode(0);
    });

    it('warns about seeding, not dropping tables, when only seed runs', function () {
        // seed alone is destructive (overwrites rows) but drops nothing, so the
        // prompt must not claim "DROP ALL TABLES".
        $this->artisan('project:dev', ['--setup' => true, '--only' => 'seed'])
            ->expectsConfirmation(
                "This will run the database seeders against connection 'testing' (database ':memory:'), overwriting existing data. Continue?",
                'no'
            )
            ->assertExitCode(1);
    });

    it('warns about dropping tables without reseed when migrate runs alone', function () {
        $this->artisan('project:dev', ['--setup' => true, '--only' => 'migrate'])
            ->expectsConfirmation(
                "This will DROP ALL TABLES on connection 'testing' (database ':memory:'). Continue?",
                'no'
            )
            ->assertExitCode(1);
    });

    it('rejects an unknown step name', function () {
        $this->artisan('project:dev', ['--setup' => true, '--only' => 'bogus'])
            ->expectsOutputToContain('Unknown step(s): bogus')
            ->assertExitCode(1);
    });

    it('rejects using --only and --skip together', function () {
        $this->artisan('project:dev', ['--setup' => true, '--only' => 'seed', '--skip' => 'build'])
            ->expectsOutputToContain('Use either --only or --skip')
            ->assertExitCode(1);
    });
});

describe('timing summary', function () {
    it('prints a per-step timing table', function () {
        $this->artisan('project:dev', ['--setup' => true, '--force' => true])
            ->expectsOutputToContain('migrate:fresh')
            ->expectsOutputToContain('total')
            ->assertExitCode(0);
    });
});

describe('production guard', function () {
    it('refuses to run in a production environment', function () {
        app()->detectEnvironment(fn () => 'production');

        $this->artisan('project:dev', ['--setup' => true, '--force' => true])
            ->expectsOutputToContain('Refusing to run --setup in a production environment')
            ->assertExitCode(1);
    });

    it('runs in production when --force-production is passed', function () {
        app()->detectEnvironment(fn () => 'production');
        TestSeeder::$runs = 0;

        $this->artisan('project:dev', [
            '--setup' => true,
            '--force' => true,
            '--force-production' => true,
        ])->assertExitCode(0);

        expect(TestSeeder::$runs)->toBe(1);
    });
});
