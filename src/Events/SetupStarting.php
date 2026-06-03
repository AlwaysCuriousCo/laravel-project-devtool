<?php

namespace AlwaysCurious\LaravelProjectDevtool\Events;

/**
 * Fired at the very start of --setup, BEFORE the destructive-wipe
 * confirmation and before any destructive work happens.
 *
 * This is the pre-flight / guard point: a listener may throw {@see AbortSetup}
 * here to cleanly veto the run before the database is touched.
 */
final class SetupStarting extends SetupEvent {}
