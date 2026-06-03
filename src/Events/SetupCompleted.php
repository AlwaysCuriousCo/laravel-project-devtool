<?php

namespace AlwaysCurious\LaravelProjectDevtool\Events;

/**
 * Fired after the asset build, just before the command returns.
 *
 * The end-of-run report point: attach here to append app-specific summary
 * lines (login URLs, admin credentials, next steps) to the console output.
 */
final class SetupCompleted extends SetupEvent {}
