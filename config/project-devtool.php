<?php

// config for AlwaysCurious/LaravelProjectDevtool
return [

    /*
    |--------------------------------------------------------------------------
    | Seeder class
    |--------------------------------------------------------------------------
    | The seeder class run during `project:dev --setup`. Leave null to use the
    | framework default DatabaseSeeder (no --class is passed).
    */
    'seeder' => null,

    /*
    |--------------------------------------------------------------------------
    | Asset build command
    |--------------------------------------------------------------------------
    | The command (as an argv array) used to build front-end assets during
    | --setup. Set to null or [] to skip the build step entirely.
    */
    'build' => ['npm', 'run', 'build'],

    /*
    |--------------------------------------------------------------------------
    | Dependency install commands
    |--------------------------------------------------------------------------
    | Used by `project:dev --setup --new`. Each is an argv array; set to
    | null/[] to skip that install step.
    */
    'install' => [
        'composer' => ['composer', 'install'],
        'npm' => ['npm', 'install'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Opt-in recipes
    |--------------------------------------------------------------------------
    | Reusable listeners shipped with the package, OFF by default so a project
    | that doesn't use them pulls in zero coupling. Flip `enabled` to true to
    | have the service provider register the recipe's listener.
    */
    'recipes' => [
        // Regenerate filament-shield permissions after migrate:fresh, before
        // seeding, so the seeder can grant them to a super-admin role.
        'shield' => [
            'enabled' => false,
            'panel' => 'admin',
        ],
    ],

];
