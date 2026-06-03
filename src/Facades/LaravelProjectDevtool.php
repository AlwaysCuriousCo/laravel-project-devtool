<?php

namespace AlwaysCurious\LaravelProjectDevtool\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \AlwaysCurious\LaravelProjectDevtool\LaravelProjectDevtool
 */
class LaravelProjectDevtool extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AlwaysCurious\LaravelProjectDevtool\LaravelProjectDevtool::class;
    }
}
