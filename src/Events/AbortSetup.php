<?php

namespace AlwaysCurious\LaravelProjectDevtool\Events;

use RuntimeException;

/**
 * Thrown by a {@see SetupStarting} listener to cleanly veto the --setup run
 * before anything destructive happens.
 *
 * The command only honours this exception when it is thrown from the
 * SetupStarting pre-flight point. Thrown from any later event it is treated
 * as a misplaced veto: reported as a warning and swallowed so a stray abort
 * cannot leave the database half-built.
 */
final class AbortSetup extends RuntimeException {}
