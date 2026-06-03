<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->listenerPath = app_path('Listeners');
    File::deleteDirectory($this->listenerPath);
});

afterEach(function () {
    File::deleteDirectory($this->listenerPath);
});

it('generates a listener type-hinted to the chosen event', function () {
    $this->artisan('make:dev-hook', ['name' => 'BuildDemoData', '--event' => 'DatabaseSeeded'])
        ->assertExitCode(0);

    $file = $this->listenerPath.'/BuildDemoData.php';
    expect(File::exists($file))->toBeTrue();

    $contents = File::get($file);
    expect($contents)
        ->toContain('use AlwaysCurious\LaravelProjectDevtool\Events\DatabaseSeeded;')
        ->toContain('public function handle(DatabaseSeeded $event): void')
        ->toContain('class BuildDemoData')
        // the scaffolded hook models the dry-run contract
        ->toContain('if ($event->dryRun)');
});

it('defaults to DatabaseSeeded when no event is given', function () {
    $this->artisan('make:dev-hook', ['name' => 'DefaultHook'])
        ->assertExitCode(0);

    $contents = File::get($this->listenerPath.'/DefaultHook.php');
    expect($contents)->toContain('public function handle(DatabaseSeeded $event): void');
});

it('can target the DatabaseMigrated lifecycle event', function () {
    $this->artisan('make:dev-hook', ['name' => 'GeneratePerms', '--event' => 'DatabaseMigrated'])
        ->assertExitCode(0);

    $contents = File::get($this->listenerPath.'/GeneratePerms.php');
    expect($contents)
        ->toContain('use AlwaysCurious\LaravelProjectDevtool\Events\DatabaseMigrated;')
        ->toContain('public function handle(DatabaseMigrated $event): void');
});

it('uses a published stub when one exists at stubs/dev-hook.stub', function () {
    $stubPath = base_path('stubs');
    File::ensureDirectoryExists($stubPath);
    File::put($stubPath.'/dev-hook.stub', "<?php\n// CUSTOM PUBLISHED STUB for {{ event }}\nclass {{ class }} {}\n");

    try {
        $this->artisan('make:dev-hook', ['name' => 'CustomHook', '--event' => 'DatabaseSeeded'])
            ->assertExitCode(0);

        $contents = File::get($this->listenerPath.'/CustomHook.php');
        expect($contents)
            ->toContain('CUSTOM PUBLISHED STUB for DatabaseSeeded')
            ->toContain('class CustomHook');
    } finally {
        File::delete($stubPath.'/dev-hook.stub');
    }
});

it('rejects an unknown event', function () {
    $this->artisan('make:dev-hook', ['name' => 'Bogus', '--event' => 'NopeNotReal'])
        ->expectsOutputToContain('Invalid --event')
        ->assertExitCode(1);

    expect(File::exists($this->listenerPath.'/Bogus.php'))->toBeFalse();
});
