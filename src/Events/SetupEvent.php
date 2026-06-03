<?php

namespace AlwaysCurious\LaravelProjectDevtool\Events;

use Illuminate\Console\Command;

/**
 * Base class for every project:dev --setup lifecycle event.
 *
 * Every event carries the running console command so listeners can:
 *   - dispatch nested artisan commands:  $event->command->call('...')
 *   - read the invocation options:        $event->command->option('new')
 *   - write to the SAME console output:   $event->command->info('...')
 *
 * Listeners attach simply by type-hinting a concrete subclass in their
 * handle() method — Laravel auto-discovers them in the consuming app's
 * app/Listeners directory. No string keys, no ordering config, no
 * central registration.
 */
abstract class SetupEvent
{
    public function __construct(
        public readonly Command $command,
    ) {}
}
