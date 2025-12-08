<p align="center"><img src="/art/logo.svg" alt="Laravel Pulse Logo"></p>

<p align="center">
<a href="https://github.com/mohaphez/pulse/actions"><img src="https://github.com/mohaphez/pulse/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/mohaphez/pulse"><img src="https://img.shields.io/packagist/dt/mohaphez/pulse" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/mohaphez/pulse"><img src="https://img.shields.io/packagist/v/mohaphez/pulse" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/mohaphez/pulse"><img src="https://img.shields.io/packagist/l/mohaphez/pulse" alt="License"></a>
</p>

## Introduction

**Laravel Pulse with Tailwind CSS 4** - A fork of Laravel Pulse upgraded to use Tailwind CSS 4 with improved performance and modern CSS features.

This package is a real-time application performance monitoring tool and dashboard for your Laravel application, now powered by Tailwind CSS 4.


## Requirements

- PHP 8.1 or higher
- Laravel 10.48.4, 11.0.8, or 12.0+
- Node.js 18.0 or higher
- Tailwind CSS 4.0

## Installation

Install via Composer:

```bash
composer require mohaphez/pulse
```

Then follow the standard [Laravel Pulse installation guide](https://laravel.com/docs/pulse).

## Official Documentation

Documentation for Laravel Pulse can be found on the [Laravel website](https://laravel.com/docs/pulse).

## Building Assets

To build the frontend assets for development:

```bash
npm install
npm run build
```

To watch for changes during development:

```bash
npm run watch
```

**Note:** This package uses Tailwind CSS 4, which introduces a new CSS-first configuration approach. The Tailwind configuration is now defined in `resources/css/pulse.css` using the `@theme` directive instead of `tailwind.config.js`.

## Contributing

Thank you for considering contributing to Pulse! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Credits

This package is a fork of [Laravel Pulse](https://github.com/laravel/pulse) by Taylor Otwell and the Laravel team, upgraded to Tailwind CSS 4.

## Security Vulnerabilities

Please review [our security policy](https://github.com/mohaphez/pulse/security/policy) on how to report security vulnerabilities.

## License

Laravel Pulse is open-sourced software licensed under the [MIT license](LICENSE.md).
