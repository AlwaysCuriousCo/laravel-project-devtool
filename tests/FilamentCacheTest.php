<?php

use Illuminate\Console\Command;

/**
 * A stand-in for Filament's `filament:optimize-clear` command. Registering it
 * lets us assert ProjectDev detects Filament by command presence and clears its
 * caches, without depending on Filament being installed in the test app.
 */
class FakeFilamentOptimizeClear extends Command
{
    public static int $runs = 0;

    protected $signature = 'filament:optimize-clear';

    protected $description = 'Fake Filament cache clear for tests.';

    public function handle(): int
    {
        self::$runs++;

        return self::SUCCESS;
    }
}

function registerFakeFilament(): void
{
    app('Illuminate\Contracts\Console\Kernel')->registerCommand(new FakeFilamentOptimizeClear);
}

it('clears Filament caches during the caches step when Filament is detected', function () {
    FakeFilamentOptimizeClear::$runs = 0;
    registerFakeFilament();

    $this->artisan('project:dev', ['--setup' => true, '--force' => true, '--only' => 'caches'])
        ->assertExitCode(0);

    expect(FakeFilamentOptimizeClear::$runs)->toBe(1);
});

it('skips the Filament cache clear cleanly when Filament is not installed', function () {
    // No command registered: the caches step must still succeed and must not
    // print a filament:optimize-clear timing row.
    $this->artisan('project:dev', ['--setup' => true, '--force' => true, '--only' => 'caches'])
        ->doesntExpectOutputToContain('filament:optimize-clear')
        ->assertExitCode(0);
});

it('halts the run when the Filament cache clear fails', function () {
    // A failing filament:optimize-clear must compose with the step contract and
    // bail before migrate/seed, exactly like a failing optimize:clear would.
    app('Illuminate\Contracts\Console\Kernel')->registerCommand(
        new class extends Command
        {
            protected $signature = 'filament:optimize-clear';

            public function handle(): int
            {
                return self::FAILURE;
            }
        }
    );

    $this->artisan('project:dev', ['--setup' => true, '--force' => true])
        ->expectsOutputToContain('Failed: filament:optimize-clear')
        ->assertExitCode(1);
});
