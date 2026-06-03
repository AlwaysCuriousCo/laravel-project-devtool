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
use Illuminate\Support\Str;
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
        {--setup        : Fresh reset — rebuild DB, seed, and build assets}
        {--new          : With --setup, also install dependencies (composer install + npm install)}
        {--force        : With --setup, skip confirmation prompts}
        {--dry-run      : With --setup, simulate the run without making any changes}
        {--only=        : With --setup, run ONLY these steps (comma-separated: '.self::STEP_LIST.')}
        {--skip=        : With --setup, skip these steps (comma-separated: '.self::STEP_LIST.')}
        {--force-production : Allow --setup to run when the app environment is production}';

    protected $description = 'Developer tooling for resetting and rebuilding the local environment.';

    /**
     * The selectable --setup steps, in execution order. Used by --only/--skip
     * and to validate user input.
     */
    private const STEPS = ['install', 'caches', 'migrate', 'seed', 'build'];

    private const STEP_LIST = 'install,caches,migrate,seed,build';

    /**
     * Per-step wall-clock timings collected during the run, in seconds.
     *
     * @var array<string, float>
     */
    private array $timings = [];

    /** Whether the current run is a no-op simulation. */
    private bool $dryRun = false;

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
        $this->line('    <comment>--new</comment>       With --setup, also run composer install + npm install.');
        $this->line('    <comment>--force</comment>     With --setup, skip confirmation prompts.');
        $this->line('    <comment>--dry-run</comment>   With --setup, simulate the run without making changes.');
        $this->line('    <comment>--only=</comment>     Run only these steps ('.self::STEP_LIST.').');
        $this->line('    <comment>--skip=</comment>     Skip these steps ('.self::STEP_LIST.').');
        $this->newLine();
        $this->line('Example:');
        $this->line('  php artisan project:dev --setup --new');
        $this->line('  php artisan project:dev --setup --only=migrate,seed');
        $this->line('  php artisan project:dev --setup --dry-run');

        return self::SUCCESS;
    }

    /**
     * The --setup flow. Step ordering is load-bearing — see the package README.
     */
    private function runSetup(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        // Validate --only/--skip before doing anything.
        $steps = $this->resolveSteps();
        if ($steps === null) {
            return self::FAILURE;
        }

        // Guard: never wipe a production environment by accident.
        if ($this->getLaravel()->environment('production') && ! $this->option('force-production')) {
            $this->error('Refusing to run --setup in a production environment.');
            $this->line('Pass --force-production if you are absolutely sure. Nothing was changed.');

            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('DRY RUN — simulating; no changes will be made.');
            $this->newLine();
        }

        // 1. Resolve the connection + database name for the confirmation prompt.
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        // 2. Pre-flight guard point. A SetupStarting listener may veto the run
        //    by throwing AbortSetup BEFORE anything destructive happens.
        try {
            $this->fire(new SetupStarting($this, $this->dryRun), allowAbort: true);
        } catch (AbortSetup $e) {
            $this->error($e->getMessage());
            $this->line('Nothing was changed.');

            return self::FAILURE;
        }

        // 3. Destructive-wipe confirmation, naming the exact target and the
        //    actual effect of the resolved steps. Skipped on a dry run (nothing
        //    is wiped) or with --force.
        if (! $this->dryRun && ! $this->option('force') && $this->runsAnyDestructiveStep($steps)) {
            $target = "connection '{$connection}'".($database ? " (database '{$database}')" : '');

            // Only migrate:fresh drops tables; seed alone just (re)writes rows.
            $effect = in_array('migrate', $steps, true)
                ? "This will DROP ALL TABLES on {$target}".(in_array('seed', $steps, true) ? ' and reseed' : '').'.'
                : "This will run the database seeders against {$target}, overwriting existing data.";

            $proceed = $this->confirm($effect.' Continue?');

            if (! $proceed) {
                $this->error('Aborted — nothing was changed.');

                return self::FAILURE;
            }
        }

        // 4. Optional dependency install (composer + npm) via real processes.
        if ($this->option('new') && in_array('install', $steps, true)) {
            $install = config('project-devtool.install', []);

            $composer = $install['composer'] ?? null;
            if (! empty($composer) && ! $this->step('install', 'composer install', fn () => $this->runProcess($composer, 'composer install'))) {
                return self::FAILURE;
            }

            $npm = $install['npm'] ?? null;
            if (! empty($npm) && ! $this->step('install', 'npm install', fn () => $this->runProcess($npm, 'npm install'))) {
                return self::FAILURE;
            }
        }

        // 5. Clear caches, then fire CachesCleared.
        if (in_array('caches', $steps, true)) {
            if (! $this->step('caches', 'optimize:clear', fn () => $this->callArtisan('optimize:clear'))) {
                return self::FAILURE;
            }
            $this->fire(new CachesCleared($this, $this->dryRun));
        }

        // 6. Rebuild the schema (NO --seed — seeding is a separate later step),
        //    then fire DatabaseMigrated in the deliberate pre-seed gap. A failed
        //    migrate must halt before the event fires and before seeding.
        if (in_array('migrate', $steps, true)) {
            if (! $this->step('migrate', 'migrate:fresh', fn () => $this->callArtisan('migrate:fresh', ['--force' => true]))) {
                return self::FAILURE;
            }
            $this->fire(new DatabaseMigrated($this, $this->dryRun));
        }

        // 7. Seed (plain db:seed — model events stay enabled), then fire
        //    DatabaseSeeded. Seeder class is configurable.
        if (in_array('seed', $steps, true)) {
            $seedParams = ['--force' => true];
            $seeder = config('project-devtool.seeder');
            if (! empty($seeder)) {
                $seedParams['--class'] = $seeder;
            }
            if (! $this->step('seed', 'db:seed', fn () => $this->callArtisan('db:seed', $seedParams))) {
                return self::FAILURE;
            }
            $this->fire(new DatabaseSeeded($this, $this->dryRun));
        }

        // 8. Build assets. Fire AssetsBuilding first; skip cleanly if disabled.
        if (in_array('build', $steps, true)) {
            $this->fire(new AssetsBuilding($this, $this->dryRun));

            $build = config('project-devtool.build', ['npm', 'run', 'build']);
            if (empty($build)) {
                $this->line('Skipping asset build (no build command configured).');
            } elseif (! $this->step('build', 'asset build', fn () => $this->runProcess($build, 'asset build'))) {
                return self::FAILURE;
            }
        }

        // 9. Engine-level completion summary, then SetupCompleted for app
        //    report lines. The engine summary knows nothing app-specific.
        $this->printSummary($steps);

        $this->fire(new SetupCompleted($this, $this->dryRun));

        return self::SUCCESS;
    }

    /**
     * Resolve the effective step list from --only/--skip, validating input.
     * Returns null (and reports an error) if an unknown step name is given.
     *
     * @return array<int, string>|null
     */
    private function resolveSteps(): ?array
    {
        $parse = function (?string $raw): array {
            return collect(explode(',', (string) $raw))
                ->map(fn ($s) => Str::lower(trim($s)))
                ->filter()
                ->values()
                ->all();
        };

        $only = $parse($this->option('only'));
        $skip = $parse($this->option('skip'));

        if ($only && $skip) {
            $this->error('Use either --only or --skip, not both.');

            return null;
        }

        $unknown = array_diff(array_merge($only, $skip), self::STEPS);
        if ($unknown) {
            $this->error('Unknown step(s): '.implode(', ', $unknown).'. Valid steps: '.self::STEP_LIST.'.');

            return null;
        }

        if ($only) {
            return array_values(array_filter(self::STEPS, fn ($s) => in_array($s, $only, true)));
        }

        if ($skip) {
            return array_values(array_filter(self::STEPS, fn ($s) => ! in_array($s, $skip, true)));
        }

        return self::STEPS;
    }

    /**
     * Whether the resolved run includes a step that destroys data (so we know
     * whether the wipe confirmation is warranted).
     *
     * @param  array<int, string>  $steps
     */
    private function runsAnyDestructiveStep(array $steps): bool
    {
        return in_array('migrate', $steps, true) || in_array('seed', $steps, true);
    }

    /**
     * Execute a named step, recording its wall-clock time. On a dry run the
     * action is announced but not executed, and the step is treated as a
     * success so the simulated run continues to completion.
     *
     * @param  callable(): bool  $action
     */
    private function step(string $name, string $label, callable $action): bool
    {
        if ($this->dryRun) {
            $this->line("  <comment>[dry-run]</comment> would run: {$label}");

            return true;
        }

        $start = $this->now();
        $ok = $action();
        $this->timings[$label] = $this->now() - $start;

        return $ok;
    }

    /**
     * Run a nested artisan command, returning true only on a zero exit code so
     * a failed step composes with the step() success contract and halts the run.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function callArtisan(string $command, array $parameters = []): bool
    {
        $exitCode = $this->call($command, $parameters);

        if ($exitCode !== self::SUCCESS) {
            $this->error("Failed: {$command} (exit {$exitCode})");

            return false;
        }

        return true;
    }

    /**
     * Print the per-step timing report and the engine completion summary.
     *
     * @param  array<int, string>  $steps
     */
    private function printSummary(array $steps): void
    {
        $this->newLine();

        if (! $this->dryRun && $this->timings !== []) {
            $rows = [];
            $total = 0.0;
            foreach ($this->timings as $label => $seconds) {
                $rows[] = [$label, number_format($seconds, 2).'s'];
                $total += $seconds;
            }
            $rows[] = ['<comment>total</comment>', '<comment>'.number_format($total, 2).'s</comment>'];
            $this->table(['Step', 'Time'], $rows);
        }

        if ($this->dryRun) {
            $this->info('✔ Dry run complete — no changes were made.');
        } else {
            $this->info('✔ Dev setup complete.');
        }

        if ($steps !== self::STEPS) {
            $this->line('Steps run: '.implode(', ', $steps));
        }

        $this->line('Dependencies: '.(
            $this->option('new') && in_array('install', $steps, true) ? 'installed' : 'skipped (pass --new)'
        ));
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

    /**
     * Monotonic-ish wall clock in seconds. Extracted so timing stays in one
     * place (and tests don't depend on it).
     */
    protected function now(): float
    {
        return microtime(true);
    }
}
