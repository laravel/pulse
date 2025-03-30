# Release Notes

## [Unreleased](https://github.com/laravel/pulse/compare/v1.4.1...1.x)

## [v1.4.1](https://github.com/laravel/pulse/compare/v1.4.0...v1.4.1) - 2025-03-30

* Test Improvements by [@crynobone](https://github.com/crynobone) in https://github.com/laravel/pulse/pull/449
* [1.x] Make `PulseMigration` compatible with Laravel v12.4: `public shouldRun()` by [@onlime](https://github.com/onlime) in https://github.com/laravel/pulse/pull/451

## [v1.4.0](https://github.com/laravel/pulse/compare/v1.3.4...v1.4.0) - 2025-02-11

* [1.x] Fix background color for overscroll-bounce by [@angelej](https://github.com/angelej) in https://github.com/laravel/pulse/pull/442
* [1.x] Fix: Keep select fields open by [@angelej](https://github.com/angelej) in https://github.com/laravel/pulse/pull/441
* Allow system default of dark mode if theme value is unrecognized by [@J-T-McC](https://github.com/J-T-McC) in https://github.com/laravel/pulse/pull/440
* [1.x] Fix empty state for the exceptions card by [@angelej](https://github.com/angelej) in https://github.com/laravel/pulse/pull/439
* [1.x] Encode slow queries if highlighting is disabled by [@angelej](https://github.com/angelej) in https://github.com/laravel/pulse/pull/443

## [v1.3.4](https://github.com/laravel/pulse/compare/v1.3.3...v1.3.4) - 2025-01-28

* Supports Laravel 12 by [@crynobone](https://github.com/crynobone) in https://github.com/laravel/pulse/pull/438

## [v1.3.3](https://github.com/laravel/pulse/compare/v1.3.2...v1.3.3) - 2025-01-02

* [1.x] Add nested controller route grouping by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pulse/pull/433
* [1.x] Supports Relay driver on PHP 8.4 by [@crynobone](https://github.com/crynobone) in https://github.com/laravel/pulse/pull/421

## [v1.3.2](https://github.com/laravel/pulse/compare/v1.3.1...v1.3.2) - 2024-12-12

* [1.x] Prevent memory leak on `pulse:work` command when using Telescope by [@gdebrauwer](https://github.com/gdebrauwer) in https://github.com/laravel/pulse/pull/430

## [v1.3.1](https://github.com/laravel/pulse/compare/v1.3.0...v1.3.1) - 2024-12-11

* Method visibility patch by [@angelej](https://github.com/angelej) in https://github.com/laravel/pulse/pull/429

## [v1.3.0](https://github.com/laravel/pulse/compare/v1.2.7...v1.3.0) - 2024-12-02

* [1.x] Ensure run at is always returned to the front end in UTC by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pulse/pull/425
* [1.x] Add Configurable Trim Duration for Pulse Data Storage by [@tharlei](https://github.com/tharlei) in https://github.com/laravel/pulse/pull/424
* [1.x] Prevent memory leak on `pulse:check` command when using Telescope by [@gdebrauwer](https://github.com/gdebrauwer) in https://github.com/laravel/pulse/pull/426
* [1.x] Use dedicated config value for trimming storage by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pulse/pull/427

## [v1.2.7](https://github.com/laravel/pulse/compare/v1.2.6...v1.2.7) - 2024-11-25

* [1.x] Supports PHP 8.4 by [@crynobone](https://github.com/crynobone) in https://github.com/laravel/pulse/pull/419
* [1.x] Format dates on graph updates by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pulse/pull/423

## [v1.2.6](https://github.com/laravel/pulse/compare/v1.2.5...v1.2.6) - 2024-11-12

* [1.x] Format dates to browsers timezone by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pulse/pull/417
* [1.x] Update NPM dependencies by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pulse/pull/418

## [v1.2.5](https://github.com/laravel/pulse/compare/v1.2.4...v1.2.5) - 2024-09-03

* [1.x] Removed useless default null values for env method by [@siarheipashkevich](https://github.com/siarheipashkevich) in https://github.com/laravel/pulse/pull/404

## [v1.2.4](https://github.com/laravel/pulse/compare/v1.2.3...v1.2.4) - 2024-07-08

* Skip unreadable filesystems by [@27pchrisl](https://github.com/27pchrisl) in https://github.com/laravel/pulse/pull/392

## [v1.2.3](https://github.com/laravel/pulse/compare/v1.2.2...v1.2.3) - 2024-06-04

* [1.x] Fix DB prefixing for Postgres and SQLite by [@jessarcher](https://github.com/jessarcher) in https://github.com/laravel/pulse/pull/386

## [v1.2.2](https://github.com/laravel/pulse/compare/v1.2.1...v1.2.2) - 2024-05-26

* [1.x] Use Contracts for common services by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pulse/pull/376
* [1.x] Fix static analysis by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pulse/pull/379

## [v1.2.1](https://github.com/laravel/pulse/compare/v1.2.0...v1.2.1) - 2024-05-22

* Fix exception from missing import on select component by [@rginnow](https://github.com/rginnow) in https://github.com/laravel/pulse/pull/373

## [v1.2.0](https://github.com/laravel/pulse/compare/v1.1.0...v1.2.0) - 2024-05-17

* [1.x] Ensure missing user does not throw a type error by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pulse/pull/366
* [1.x] Allow thresholds of slow queries, jobs and requests to be customised by regex pattern by [@matheus-carvalho](https://github.com/matheus-carvalho) in https://github.com/laravel/pulse/pull/340
* [1.x] Removes the padding right when there is no scroll active. by [@xiCO2k](https://github.com/xiCO2k) in https://github.com/laravel/pulse/pull/369
* [1.x] Associate label with select by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pulse/pull/371
* [1.x] Improve config for 3rd party cards by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pulse/pull/372

## [v1.1.0](https://github.com/laravel/pulse/compare/v1.0.0...v1.1.0) - 2024-05-06

* [1.x] Ignore offline servers (continued) by [@JustinElst](https://github.com/JustinElst) in https://github.com/laravel/pulse/pull/355
* Add support for Relay by [@riasvdv](https://github.com/riasvdv) in https://github.com/laravel/pulse/pull/358
* Added reverb to default vendor cache keys by [@phlawlessDevelopment](https://github.com/phlawlessDevelopment) in https://github.com/laravel/pulse/pull/360
* [1.x] Rename highlighting prop by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pulse/pull/362
* [1.x] Cache testing improvements by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pulse/pull/363

## v1.0.0 - 2024-04-30

Initial release of Pulse. For more information, please consult the [Pulse documentation](https://laravel.com/docs/pulse).
