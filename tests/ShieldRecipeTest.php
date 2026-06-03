<?php

use AlwaysCurious\LaravelProjectDevtool\Events\DatabaseMigrated;
use AlwaysCurious\LaravelProjectDevtool\Recipes\GenerateShieldPermissions;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Event;

/**
 * A stand-in for filament-shield's shield:generate command so the recipe has a
 * real registered command to call.
 */
class FakeShieldGenerate extends Command
{
    // Note: --no-interaction is a reserved Symfony global option, so it is NOT
    // redeclared here even though the recipe passes it.
    protected $signature = 'shield:generate {--all} {--panel=}';

    protected $description = 'fake shield:generate';

    public static int $runs = 0;

    public static ?string $panel = null;

    public function handle(): int
    {
        self::$runs++;
        self::$panel = $this->option('panel');

        return self::SUCCESS;
    }
}

it('runs shield:generate with the configured panel when the recipe fires', function () {
    FakeShieldGenerate::$runs = 0;
    config()->set('project-devtool.recipes.shield.panel', 'control');

    $this->app[Kernel::class]->registerCommand(new FakeShieldGenerate);

    // Wire the recipe as it would be when enabled.
    Event::listen(DatabaseMigrated::class, GenerateShieldPermissions::class);

    $this->artisan('project:dev', ['--setup' => true, '--force' => true])
        ->expectsOutputToContain('Generating Shield permissions')
        ->assertExitCode(0);

    expect(FakeShieldGenerate::$runs)->toBe(1)
        ->and(FakeShieldGenerate::$panel)->toBe('control');
});

it('skips cleanly with a notice when shield:generate is not registered', function () {
    // No FakeShieldGenerate registered: the recipe must skip, not error.
    Event::listen(DatabaseMigrated::class, GenerateShieldPermissions::class);

    $this->artisan('project:dev', ['--setup' => true, '--force' => true])
        ->expectsOutputToContain("Skipping 'shield:generate'")
        ->assertExitCode(0);
});
