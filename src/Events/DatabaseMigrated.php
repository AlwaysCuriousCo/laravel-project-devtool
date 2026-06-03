<?php

namespace AlwaysCurious\LaravelProjectDevtool\Events;

/**
 * Fired after `migrate:fresh --force`, in the deliberate gap BEFORE seeding.
 *
 * This is the critical hook point for work that needs the schema to exist
 * but must run before the seeder — e.g. generating filament-shield
 * permissions so the seeder can grant them to a super-admin role.
 *
 * The opt-in GenerateShieldPermissions recipe attaches here.
 */
final class DatabaseMigrated extends SetupEvent {}
