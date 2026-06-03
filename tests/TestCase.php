<?php

namespace AlwaysCurious\LaravelProjectDevtool\Tests;

use AlwaysCurious\LaravelProjectDevtool\LaravelProjectDevtoolServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            LaravelProjectDevtoolServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        // In-memory sqlite so migrate:fresh / db:seed run against a real (but
        // throwaway) database without touching anything on disk.
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // optimize:clear runs cache:clear; keep the cache off the throwaway DB
        // so it never tries to delete from a non-existent `cache` table.
        $app['config']->set('cache.default', 'array');

        // Skip the asset build by default so the suite never shells out to npm.
        $app['config']->set('project-devtool.build', null);

        // Use a seeder that actually exists in the test app.
        $app['config']->set('project-devtool.seeder', TestSeeder::class);
    }
}
