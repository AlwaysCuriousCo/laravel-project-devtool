<?php

namespace AlwaysCurious\LaravelProjectDevtool\Commands;

use Illuminate\Console\Command;

class LaravelProjectDevtoolCommand extends Command
{
    public $signature = 'laravel-project-devtool';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
