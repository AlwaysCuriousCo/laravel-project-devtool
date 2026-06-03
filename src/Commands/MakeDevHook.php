<?php

namespace AlwaysCurious\LaravelProjectDevtool\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Scaffolds a project:dev lifecycle listener in the consuming app's
 * app/Listeners directory, pre-wired to a chosen lifecycle event.
 *
 * This turns the hook pattern into ergonomics: consumers get a correct,
 * type-hinted listener without memorising event names.
 */
class MakeDevHook extends GeneratorCommand
{
    protected $name = 'make:dev-hook';

    protected $description = 'Create a new project:dev lifecycle hook (listener) in app/Listeners';

    protected $type = 'Listener';

    /**
     * The lifecycle events a hook may attach to.
     *
     * @var array<int, string>
     */
    public const EVENTS = [
        'SetupStarting',
        'CachesCleared',
        'DatabaseMigrated',
        'DatabaseSeeded',
        'AssetsBuilding',
        'SetupCompleted',
    ];

    protected function getStub(): string
    {
        return __DIR__.'/../../resources/stubs/dev-hook.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Listeners';
    }

    public function handle(): ?bool
    {
        if ($this->resolveEvent() === null) {
            // fail() reports the message and exits non-zero (a bare
            // `return false` would cast to exit code 0).
            $this->fail('Invalid --event. Choose one of: '.implode(', ', self::EVENTS));
        }

        return parent::handle();
    }

    /**
     * Validate the --event option against the known event list.
     * Defaults to DatabaseSeeded when omitted.
     */
    protected function resolveEvent(): ?string
    {
        $event = $this->option('event') ?: 'DatabaseSeeded';

        return in_array($event, self::EVENTS, true) ? $event : null;
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $event = $this->resolveEvent();

        return str_replace(
            ['{{ event }}', '{{event}}'],
            $event,
            $stub
        );
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['event', null, InputOption::VALUE_OPTIONAL, 'Lifecycle event to hook ('.implode('|', self::EVENTS).')'],
        ];
    }
}
