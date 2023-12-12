# Upgrade Guide

# Beta to 1.x

## Required

- [Added a `highlight` configuration option to `pulse.recorders.SlowQueries` configuration block](https://github.com/laravel/pulse/pull/172).

## Optional

- [Auto-incrementing IDs were added to Pulse's tables](https://github.com/laravel/pulse/pull/142). This is recommended if you are using a configuration that requires tables to have a unique key on every table, e.g., PlanetScale.
- [The TEXT columns were made MEDIUMTEXT columns in the `pulse_` tables](https://github.com/laravel/pulse/pull/185). Recommend to support longer content values, such as long SQL queries.
- [Pulse's migrations are now published to the application](https://github.com/laravel/pulse/pull/81). Recommend so you can have complete control over the migrations as needed.
