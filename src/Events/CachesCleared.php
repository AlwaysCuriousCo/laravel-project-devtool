<?php

namespace AlwaysCurious\LaravelProjectDevtool\Events;

/**
 * Fired immediately after `optimize:clear` has run.
 *
 * Attach here to do work that needs a clean cache/config state but must
 * happen before the database is rebuilt.
 */
final class CachesCleared extends SetupEvent {}
