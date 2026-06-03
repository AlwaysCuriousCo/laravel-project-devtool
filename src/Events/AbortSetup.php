<?php

namespace AlwaysCurious\LaravelProjectDevtool\Events;

use RuntimeException;

/**
 * Thrown by a listener to cleanly veto the --setup run at one of its two
 * sanctioned abort points:
 *
 *   - {@see SetupStarting} — the pre-flight guard, before anything destructive
 *     happens; the run stops having changed nothing.
 *   - {@see DatabaseMigrated} — the deliberate pre-seed gap, after the schema is
 *     rebuilt but before db:seed; a listener that prepares seed prerequisites
 *     (e.g. Shield permissions) can halt here so the seeder never runs against a
 *     half-built state.
 *
 * Thrown from any other event it is treated as a misplaced veto: reported as a
 * warning and swallowed so a stray abort cannot leave the database half-built.
 */
final class AbortSetup extends RuntimeException {}
