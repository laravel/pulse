# Laravel Pulse

- [Introduction](#introduction)
- [Installation](#installation)
    - [Using a Different Database](#using-a-different-database)
- [Configuration](#configuration)
- [Ingest](#ingest)
- [System Resource Monitoring](#system-resource-monitoring)
- [Dashboard](#dashboard)
    - [Authorization](#dashboard-authorization)
    - [Customization](#dashboard-customization)

<a name="introduction"></a>
## Introduction

TODO

<a name="installation"></a>
## Installation

> **Warning**  
> Pulse currently requires MySQL.

You may use the Composer package manager to install Pulse into your Laravel project:

```sh
# TODO: Remove when package is published.
composer config minimum-stability dev
composer config repositories.pulse '{"type": "path", "url": "../pulse"}'

composer require laravel/pulse
```

After installing Pulse, you should run the `migrate` command in order to create the tables needed to store Pulse's data:

```sh
php artisan migrate
```

<a name="using-a-different-database"></a>
### Using a Different Database

For high-traffic applications, you may prefer to use a dedicated database connection for Pulse to avoid impacting your application database.

You may customize the [database connection](/docs/{{version}}/database#configuration) used by Pulse by setting the `PULSE_DB_CONNECTION` environment variable.

```env
PULSE_DB_CONNECTION=pulse
```

<a name="configuration"></a>
## Configuration

```sh
php artisan vendor:publish --tag pulse-config
```

<a name="ingest"></a>
## Ingest

By default, Pulse will store entries directly to the [configured database connection](#using-a-different-database) after the request has been returned or a job has been processed.

Pulse may be configured to send entries to a Redis stream instead, so that a dedicated background process can store the entries to your database instead.

```
PULSE_INGEST_DRIVER=redis
```

Pulse will use your default [Redis connection](/docs/{{version}}/redis#configuration) by default, but you may customize this with the `PULSE_REDIS_CONNECTION` environment variable:

```
PULSE_REDIS_CONNECTION=pulse
```

When using the Redis ingest, you will need to run the `pulse:work` command to monitor the stream and store entries into Pulse's database tables.

```php
php artisan pulse:work
```

> **Note**  
> To keep the `pulse:work` process running permanently in the background, you should use a process monitor such as Supervisor to ensure that the Pulse worker does not stop running.

<a name="system-resource-monitoring"></a>
## System Resource Monitoring

Pulse can monitor the CPU, memory, and storage usage of the servers that power your application by running the `pulse:check` command:

```php
php artisan pulse:check
```

Every reporting server must have a unique name. By default, Pulse will use the value returned by PHP's `gethostname` function. If you wish to customize this, you may set the `PULSE_SERVER_NAME` environment variable:

```env
PULSE_SERVER_NAME=load-balancer
```

<a name="dashboard"></a>
## Dashboard

<a name="dashboard-authorization"></a>
### Dashboard Authorization

```php
Pulse::authorizeUsing(function ($request) {
    return in_array($request->user()?->email, [
        'taylor@example.com',
    ]);
});
```

<a name="dashboard-customization"></a>
### Dashboard Customization

```sh
php artisan vendor:publish --tag pulse-dashboard
```

This will create a view file at `resources/views/vendor/pulse/dashboard.blade.php`.
