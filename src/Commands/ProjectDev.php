<?php

namespace AlwaysCurious\LaravelProjectDevtool\Commands;

use AlwaysCurious\LaravelProjectDevtool\Events\AbortSetup;
use AlwaysCurious\LaravelProjectDevtool\Events\AssetsBuilding;
use AlwaysCurious\LaravelProjectDevtool\Events\CachesCleared;
use AlwaysCurious\LaravelProjectDevtool\Events\DatabaseMigrated;
use AlwaysCurious\LaravelProjectDevtool\Events\DatabaseSeeded;
use AlwaysCurious\LaravelProjectDevtool\Events\SetupCompleted;
use AlwaysCurious\LaravelProjectDevtool\Events\SetupEvent;
use AlwaysCurious\LaravelProjectDevtool\Events\SetupStarting;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Developer-only one-command "clean slate" environment reset.
 *
 * The command is a mechanism: it sequences the destructive reset and fires a
 * lifecycle event at each stage. All app-specific policy (permission
 * generation, demo data, login reports) lives in consumer-side listeners or
 * opt-in recipes — never in this class.
 */
class ProjectDev extends Command
{
    protected $signature = 'project:dev
        {--setup : Fresh reset — rebuild DB, seed, and build assets}
        {--new   : With --setup, also install dependencies (composer install + npm install)}
        {--force : With --setup, skip confirmation prompts}';

    protected $description = 'Developer tooling for resetting and rebuilding the local environment.';

    public function handle(): int
    {
        if ($this->option('setup')) {
            return $this->runSetup();
        }

        return $this->showActions();
    }

    /**
     * No action flag: print the available-actions help block and exit cleanly.
     *
     * Dispatch is kept flag-based so additional actions can be added later
     * without restructuring the command.
     */
    private function showActions(): int
    {
        $this->line('<info>project:dev</info> — developer environment tooling.');
        $this->newLine();
        $this->line('Available actions:');
        $this->line('  <comment>--setup</comment>  Fresh reset: clear caches, migrate:fresh, seed, build assets.');
        $this->line('    <comment>--new</comment>    With --setup, also run composer install + npm install.');
        $this->line('    <comment>--force</comment>  With --setup, skip confirmation prompts.');
        $this->newLine();
        $this->line('Example:');
        $this->line('  php artisan project:dev --setup --new');

        return self::SUCCESS;
    }

    /**
     * The --setup flow. Step ordering is load-bearing — see the package README.
     */
    private function runSetup(): int
    {
        // 1. Resolve the connection + database name for the confirmation prompt.
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        // 2. Pre-flight guard point. A SetupStarting listener may veto the run
        //    by throwing AbortSetup BEFORE anything destructive happens.
        try {
            $this->fire(new SetupStarting($this), allowAbort: true);
        } catch (AbortSetup $e) {
            $this->error($e->getMessage());
            $this->line('Nothing was changed.');

            return self::FAILURE;
        }

        // 3. Destructive-wipe confirmation, naming the exact target.
        if (! $this->option('force')) {
            $proceed = $this->confirm(
                "This will DROP ALL TABLES on connection '{$connection}'"
                .($database ? " (database '{$database}')" : '')
                .' and reseed. Continue?'
            );

            if (! $proceed) {
                $this->error('Aborted — nothing was changed.');

                return self::FAILURE;
            }
        }

        // 4. Optional dependency install (composer + npm) via real processes.
        if ($this->option('new')) {
            $install = config('project-devtool.install', []);

            $composer = $install['composer'] ?? null;
            if (! empty($composer) && ! $this->runProcess($composer, 'composer install')) {
                return self::FAILURE;
            }

            $npm = $install['npm'] ?? null;
            if (! empty($npm) && ! $this->runProcess($npm, 'npm install')) {
                return self::FAILURE;
            }
        }

        // 5. Clear caches, then fire CachesCleared.
        $this->call('optimize:clear');
        $this->fire(new CachesCleared($this));

        // 6. Rebuild the schema (NO --seed — seeding is a separate later step),
        //    then fire DatabaseMigrated in the deliberate pre-seed gap.
        $this->call('migrate:fresh', ['--force' => true]);
        $this->fire(new DatabaseMigrated($this));

        // 7. Seed (plain db:seed — model events stay enabled), then fire
        //    DatabaseSeeded. Seeder class is configurable.
        $seedParams = ['--force' => true];
        $seeder = config('project-devtool.seeder');
        if (! empty($seeder)) {
            $seedParams['--class'] = $seeder;
        }
        $this->call('db:seed', $seedParams);
        $this->fire(new DatabaseSeeded($this));

        // 8. Build assets. Fire AssetsBuilding first; skip cleanly if disabled.
        $this->fire(new AssetsBuilding($this));

        $build = config('project-devtool.build', ['npm', 'run', 'build']);
        if (empty($build)) {
            $this->line('Skipping asset build (no build command configured).');
        } elseif (! $this->runProcess($build, 'asset build')) {
            return self::FAILURE;
        }

        // 9. Engine-level completion summary, then SetupCompleted for app
        //    report lines. The engine summary knows nothing app-specific.
        $this->newLine();
        $this->info('✔ Dev setup complete.');
        $this->line('Dependencies: '.($this->option('new') ? 'installed' : 'skipped (pass --new)'));

        $this->fire(new SetupCompleted($this));

        return self::SUCCESS;
    }

    /**
     * Dispatch a lifecycle event with veto semantics.
     *
     * - AbortSetup from a pre-flight point ($allowAbort) is re-thrown so the
     *   caller can cleanly cancel the run.
     * - AbortSetup thrown anywhere else is a misplaced veto: reported and
     *   swallowed so it cannot leave the database half-built.
     * - Any other listener failure is reported and swallowed — a broken
     *   custom hook must never abort the reset.
     */
    private function fire(SetupEvent $event, bool $allowAbort = false): void
    {
        try {
            event($event);
        } catch (AbortSetup $e) {
            if ($allowAbort) {
                throw $e;
            }
            $this->error('Hook ('.class_basename($event).') tried to abort outside a pre-flight point: '.$e->getMessage());
        } catch (Throwable $e) {
            $this->error('Hook ('.class_basename($event).') failed: '.$e->getMessage());
        }
    }

    /**
     * Run an external (non-artisan) command, streaming its output live.
     * Returns false on a non-zero exit so the caller can bail with FAILURE.
     *
     * Overridable so tests can stub external processes without shelling out.
     *
     * @param  array<int, string>  $command
     */
    protected function runProcess(array $command, string $label): bool
    {
        $process = new Process($command, base_path(), timeout: null);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->error("Failed: {$label} (exit {$process->getExitCode()})");

            return false;
        }

        return true;
    }
}
