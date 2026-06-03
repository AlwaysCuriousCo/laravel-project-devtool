# Laravel Project Devtool

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alwayscurious/laravel-project-devtool.svg?style=flat-square)](https://packagist.org/packages/alwayscurious/laravel-project-devtool)
[![Tests](https://github.com/alwayscurious/laravel-project-devtool/actions/workflows/run-tests.yml/badge.svg)](https://github.com/alwayscurious/laravel-project-devtool/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/alwayscurious/laravel-project-devtool.svg?style=flat-square)](https://packagist.org/packages/alwayscurious/laravel-project-devtool)

**One command to a clean-slate dev environment — and a clean way for *your* app to hook into it.**

`php artisan project:dev --setup` tears the local environment down and rebuilds
it from scratch: clear caches → `migrate:fresh` → seed → build assets. Onboard a
new teammate, recover from a broken branch, or reset between feature spikes in a
single command instead of a wiki page of steps.

The part that makes it worth installing instead of writing your own shell
script: at every stage of the reset it fires a **lifecycle event**, and your app
attaches its own work by dropping a listener into `app/Listeners`. Generate
permissions, seed demo data, print login credentials — **without ever editing
this package**. The engine is mechanism; your app supplies the policy.

> ⚠️ **Dev-only.** `--setup` runs `migrate:fresh`, which **drops every table**.
> Install it as a `--dev` dependency and never point it at data you care about.

---

## Install

```bash
composer require --dev alwayscurious/laravel-project-devtool
```

That's the whole setup — the command auto-registers. Publish the config only if
you want to change defaults:

```bash
php artisan vendor:publish --tag="project-devtool-config"
```

Try it:

```bash
php artisan project:dev            # prints available actions, does nothing destructive
php artisan project:dev --setup    # the full reset, with a confirmation prompt
```

---

## The command

```
project:dev
    {--setup : Fresh reset — rebuild DB, seed, and build assets}
    {--new   : With --setup, also install dependencies (composer + npm install)}
    {--force : With --setup, skip confirmation prompts}
```

| You want to…                                   | Run |
| ---------------------------------------------- | --- |
| See what's available (safe, no-op)             | `php artisan project:dev` |
| Reset the environment                          | `php artisan project:dev --setup` |
| Reset a freshly cloned repo, deps and all      | `php artisan project:dev --setup --new` |
| Reset unattended (CI, scripts)                 | `php artisan project:dev --setup --force` |

Before wiping anything, `--setup` confirms with a prompt that **names the exact
connection and database** it's about to drop — so a wrong `.env` can't surprise
you. `--force` skips it.

---

## How `--setup` runs (and where you plug in)

The sequence is fixed and ordering-sensitive — your listeners can rely on it:

```
  ┌─ SetupStarting      ← guard point; a listener may veto here (AbortSetup)
  │   (wipe confirmation)
  ├─ optimize:clear  →  CachesCleared
  ├─ migrate:fresh   →  DatabaseMigrated   ← the gap before seeding
  ├─ db:seed         →  DatabaseSeeded
  ├─ asset build     →  AssetsBuilding (fired just before the build)
  └─ SetupCompleted    ← end-of-run report point
```

Each event carries the running command, so a listener gets the live console and
can run nested artisan commands:

```php
$event->command->info('…');                                 // same output stream
$event->command->option('new');                             // read invocation flags
$event->command->call('some:command', ['--force' => true]); // nested artisan
```

### Why seeding comes *after* a separate `DatabaseMigrated` event

This is the design detail the package exists for. Some apps must **generate**
data (permissions, lookup tables) *after* the schema exists but *before* the
seeder runs — because the seeder consumes it (e.g. granting permissions to a
role). So the reset deliberately fires `DatabaseMigrated` in the gap between
`migrate:fresh` and `db:seed`, giving you a hook at exactly that moment. Merge
the two and the seam disappears; keep them split and your app slots right in.

---

## Integrating your app: write a hook

A hook is just a listener that type-hints one of the lifecycle events. Laravel's
event discovery wires it up — no registration, no config, no service provider
edits.

### Scaffold one

```bash
php artisan make:dev-hook BuildDemoData --event=DatabaseSeeded
```

Generates `app/Listeners/BuildDemoData.php`, pre-typed to the event and
documented with the full event list. `--event` is validated and defaults to
`DatabaseSeeded`.

### Or write it by hand

```php
namespace App\Listeners;

use AlwaysCurious\LaravelProjectDevtool\Events\DatabaseSeeded;

class BuildDemoData
{
    public function handle(DatabaseSeeded $event): void
    {
        $event->command->info('Seeding demo content…');
        $event->command->call('db:seed', [
            '--class' => \Database\Seeders\DemoSeeder::class,
            '--force' => true,
        ]);
    }
}
```

### Lifecycle reference

| Event              | Fires…                                                   | Reach for it to… |
| ------------------ | -------------------------------------------------------- | ---------------- |
| `SetupStarting`    | before the wipe confirmation; **may throw `AbortSetup`** | guard the run (env present? right branch?) |
| `CachesCleared`    | after `optimize:clear`                                   | prep that needs a clean cache/config |
| `DatabaseMigrated` | after `migrate:fresh`, **before** seeding                | generate permissions / data the seeder needs |
| `DatabaseSeeded`   | after `db:seed`                                          | demo / sample data |
| `AssetsBuilding`   | just before the asset build                              | prepare build inputs |
| `SetupCompleted`   | after the build, before the command returns              | print login URLs, credentials, next steps |

### Hooks can't break the reset

A thrown exception from any listener is **reported and swallowed** so a broken
custom hook never leaves you with a half-built database. The single exception is
the deliberate veto below.

---

## Guarding the reset: `AbortSetup`

Veto a doomed run *before* anything destructive happens by throwing `AbortSetup`
from a `SetupStarting` listener:

```php
namespace App\Listeners;

use AlwaysCurious\LaravelProjectDevtool\Events\AbortSetup;
use AlwaysCurious\LaravelProjectDevtool\Events\SetupStarting;

class EnsureSuperAdminPassword
{
    public function handle(SetupStarting $event): void
    {
        if (empty(env('SUPER_ADMIN_PASSWORD'))) {
            throw new AbortSetup('SUPER_ADMIN_PASSWORD is not set — refusing to reset.');
        }
    }
}
```

The command prints the message, says *“Nothing was changed.”*, and exits with a
failure code — the database is never touched. `AbortSetup` is honoured **only**
from the `SetupStarting` pre-flight point; thrown later it's treated as a
misplaced veto (warned and swallowed) so it can't corrupt a half-built database.

---

## Batteries (opt-in recipes)

Common integrations ship as listeners that are **off by default** — a project
that doesn't use them pulls in zero coupling — and **self-guard** when an
optional dependency is missing.

### Filament Shield permissions

`GenerateShieldPermissions` regenerates
[filament-shield](https://github.com/bezhanSalleh/filament-shield) permissions on
`DatabaseMigrated`, in the gap before seeding, so your seeder can grant them to a
super-admin role. If filament-shield isn't installed it skips with a notice
instead of failing. Enable it in config:

```php
'recipes' => [
    'shield' => [
        'enabled' => true,
        'panel'   => 'admin',
    ],
],
```

---

## Configuration

`config/project-devtool.php` — everything you'd want to vary, nothing hardcoded:

```php
return [
    // Seeder class run during --setup (null = framework default DatabaseSeeder).
    'seeder' => null,

    // Asset build command (argv array). Set to null/[] to skip the build step.
    'build' => ['npm', 'run', 'build'],

    // Dependency install commands used by --new.
    'install' => [
        'composer' => ['composer', 'install'],
        'npm'      => ['npm', 'install'],
    ],

    // Opt-in recipes — off by default.
    'recipes' => [
        'shield' => ['enabled' => false, 'panel' => 'admin'],
    ],
];
```

- `build` → `null`/`[]` skips the asset build (the command tells you it did).
- `seeder` → a class name to seed something other than the default `DatabaseSeeder`.
- `install` → swap in `yarn`, `pnpm`, `bun`, etc.

---

## Requirements

- PHP `^8.3`
- Laravel 11, 12, or 13

## Testing

```bash
composer test
```

## License

The MIT License (MIT). See [License File](LICENSE.md).
