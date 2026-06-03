<?php

namespace AlwaysCurious\LaravelProjectDevtool\Recipes;

use AlwaysCurious\LaravelProjectDevtool\Events\DatabaseMigrated;

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

        $command->info('Generating Shield permissions…');

        $command->call('shield:generate', [
            '--all' => true,
            '--panel' => $panel,
            '--no-interaction' => true,
        ]);
    }
}
