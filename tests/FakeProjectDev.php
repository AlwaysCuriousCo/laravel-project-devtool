<?php

namespace AlwaysCurious\LaravelProjectDevtool\Tests;

use AlwaysCurious\LaravelProjectDevtool\Commands\ProjectDev;

/**
 * A ProjectDev whose external-process runner is stubbed, so tests can exercise
 * --new and the asset build without ever shelling out to composer/npm.
 *
 * @var array<int, array{0: array<int, string>, 1: string}> $calls
 */
class FakeProjectDev extends ProjectDev
{
    /** @var array<int, array{command: array<int, string>, label: string}> */
    public array $calls = [];

    /** Force a non-zero exit for the process whose label contains this needle. */
    public ?string $failOn = null;

    protected function runProcess(array $command, string $label): bool
    {
        $this->calls[] = ['command' => $command, 'label' => $label];

        if ($this->failOn !== null && str_contains($label, $this->failOn)) {
            $this->error("Failed: {$label} (exit 1)");

            return false;
        }

        return true;
    }
}
