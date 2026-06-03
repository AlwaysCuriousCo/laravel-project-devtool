<?php

use AlwaysCurious\LaravelProjectDevtool\Events\DatabaseMigrated;
use AlwaysCurious\LaravelProjectDevtool\Events\DatabaseSeeded;
use AlwaysCurious\LaravelProjectDevtool\Tests\TestSeeder;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Event;

/**
 * A stand-in for migrate:fresh that always fails, so we can prove a non-zero
 * nested artisan exit halts the run instead of being silently swallowed.
 */
class FailingMigrateFresh extends Command
{
    protected $signature = 'migrate:fresh {--force} {--seed} {--database=} {--path=*} {--realpath} {--schema-path=} {--seeder=} {--step} {--drop-views} {--drop-types}';

    protected $description = 'fake failing migrate:fresh';

    public function handle(): int
    {
        return self::FAILURE;
    }
}

it('halts the run and reports failure when a nested artisan step fails', function () {
    TestSeeder::$runs = 0;
    $migrated = false;
    $seeded = false;

    Event::listen(DatabaseMigrated::class, function () use (&$migrated) {
        $migrated = true;
    });
    Event::listen(DatabaseSeeded::class, function () use (&$seeded) {
        $seeded = true;
    });

    // Override the real migrate:fresh with one that exits non-zero.
    $this->app[Kernel::class]->registerCommand(new FailingMigrateFresh);

    $this->artisan('project:dev', ['--setup' => true, '--force' => true])
        ->expectsOutputToContain('Failed: migrate:fresh')
        ->assertExitCode(1);

    // The run must stop at the failed step: no event, no seeding.
    expect($migrated)->toBeFalse()
        ->and($seeded)->toBeFalse()
        ->and(TestSeeder::$runs)->toBe(0);
});
