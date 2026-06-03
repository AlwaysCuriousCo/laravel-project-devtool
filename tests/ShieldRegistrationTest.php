<?php

use AlwaysCurious\LaravelProjectDevtool\Events\DatabaseMigrated;
use AlwaysCurious\LaravelProjectDevtool\LaravelProjectDevtoolServiceProvider;
use AlwaysCurious\LaravelProjectDevtool\Recipes\GenerateShieldPermissions;
use Illuminate\Support\Facades\Event;

it('does NOT register the shield recipe listener when the flag is off (default)', function () {
    // Default config has recipes.shield.enabled => false.
    $listeners = array_map(
        fn ($l) => is_string($l) ? $l : null,
        Event::getRawListeners()[DatabaseMigrated::class] ?? []
    );

    expect($listeners)->not->toContain(GenerateShieldPermissions::class);
});

it('registers the shield recipe listener when the flag is on', function () {
    // Flip the flag, then re-run the provider boot so registration re-evaluates.
    config()->set('project-devtool.recipes.shield.enabled', true);

    $provider = new LaravelProjectDevtoolServiceProvider(app());
    $provider->packageBooted();

    expect(Event::hasListeners(DatabaseMigrated::class))->toBeTrue();

    $listeners = Event::getRawListeners()[DatabaseMigrated::class] ?? [];
    expect($listeners)->toContain(GenerateShieldPermissions::class);
});
