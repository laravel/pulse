# Upgrade Guide

# Beta to 1.x

- [SQL highlighting configuration was moved to the dashboard component](https://github.com/laravel/pulse/pull/356). This is required if you are disabling SQL highlighting.
- [Auto-incrementing IDs were added to Pulse's tables](https://github.com/laravel/pulse/pull/142). This is recommended if you are using a configuration that requires tables to have a unique key on every table, e.g., PlanetScale.
- [The TEXT columns were made MEDIUMTEXT columns in the `pulse_` tables](https://github.com/laravel/pulse/pull/185). This is recommend to support longer content values, such as long SQL queries.
- [Pulse's migrations are now published to the application](https://github.com/laravel/pulse/pull/81). This is recommend so you can have complete control over the migrations as needed.
- [`pulse:check` now dispatches events roughly every second](https://github.com/laravel/pulse/pull/314). It is recommended to use the new `throttle` function when performing work on specific intervals.
