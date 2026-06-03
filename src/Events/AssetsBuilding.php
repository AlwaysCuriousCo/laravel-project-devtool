<?php

namespace AlwaysCurious\LaravelProjectDevtool\Events;

/**
 * Fired just before the asset build command runs (default `npm run build`).
 *
 * Attach here to prepare anything the front-end build depends on.
 */
final class AssetsBuilding extends SetupEvent {}
