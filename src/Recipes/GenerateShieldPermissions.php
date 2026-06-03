<?php

namespace AlwaysCurious\LaravelProjectDevtool\Recipes;

use AlwaysCurious\LaravelProjectDevtool\Events\AbortSetup;
use AlwaysCurious\LaravelProjectDevtool\Events\DatabaseMigrated;
use Illuminate\Console\Command;

/**
 * Opt-in recipe listener that regenerates filament-shield permissions in the
 * gap between `migrate:fresh` and `db:seed`, so the seeder can grant the
 * freshly generated permissions to a super-admin role.
 *
 * This listener is OFF by default. It is registered by the service provider
 * only when config('project-devtool.recipes.shield.enabled') is true, and it
 * self-guards: if filament-shield is not installed the run continues with a
 * notice instead of an error.
 */
final class GenerateShieldPermissions
{
    public function handle(DatabaseMigrated $event): void
    {
        $command = $event->command;

        if (! $command->getApplication()->has('shield:generate')) {
            $command->warn("Skipping 'shield:generate' — command not registered (filament-shield not installed?).");

            return;
        }

        $panel = config('project-devtool.recipes.shield.panel', 'admin');

        if ($event->dryRun) {
            $command->line("  <comment>[dry-run]</comment> would run: shield:generate --all --panel={$panel}");

            return;
        }

        $command->info('Generating Shield permissions…');

        $exitCode = $command->call('shield:generate', [
            '--all' => true,
            '--panel' => $panel,
            '--no-interaction' => true,
        ]);

        // The recipe exists to populate permissions before db:seed grants them.
        // If generation fails, seeding against missing permissions would leave a
        // silently broken database, so halt the run in the pre-seed gap.
        if ($exitCode !== Command::SUCCESS) {
            throw new AbortSetup("shield:generate failed (exit {$exitCode}); halting before seed.");
        }
    }
}
