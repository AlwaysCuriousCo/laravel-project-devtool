<?php

namespace AlwaysCurious\LaravelProjectDevtool\Tests;

use Illuminate\Database\Seeder;

/**
 * A no-op seeder used by the suite. It records that it ran so tests can assert
 * whether the seed step executed (e.g. it must NOT run when a guard vetoes).
 */
class TestSeeder extends Seeder
{
    public static int $runs = 0;

    public function run(): void
    {
        self::$runs++;
    }
}
