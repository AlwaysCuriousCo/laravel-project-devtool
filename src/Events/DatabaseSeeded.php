<?php

namespace AlwaysCurious\LaravelProjectDevtool\Events;

/**
 * Fired after `db:seed --force` completes.
 *
 * The common attach point for demo/sample data hooks that depend on the
 * base seeded state.
 */
final class DatabaseSeeded extends SetupEvent {}
