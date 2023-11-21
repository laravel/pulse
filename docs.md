# Laravel Pulse

- [Introduction](#introduction)
- [Installation](#installation)
    - [Configuration](#configuration)
- [Capturing Entries](#capturing-entries)
- [Dashboard](#dashboard)
    - [Authorization](#dashboard-authorization)
    - [Customization](#dashboard-customization)
    - [Cards](#dashboard-cards)
        - [Servers](#servers-card)
        - [Application Usage](#application-usage-card)
        - [Exceptions](#exceptions-card)
        - [Queues](#queues-card)
        - [Slow Routes](#slow-routes-card)
        - [Slow Jobs](#slow-jobs-card)
        - [Slow Queries](#slow-queries-card)
        - [Slow Outgoing Requests](#slow-outgoing-requests-card)
        - [Cache](#cache-card)
- [Recorders](#recorders)
    - [System Stats](#system-stats-recorder)
    - [Requests](#requests-recorder)
    - [Jobs](#jobs-recorder)
    - [Slow Queries](#slow-queries-recorder)
    - [Outgoing Requests](#outgoing-requests-recorder)
    - [Exceptions](#exceptions-recorder)
    - [Cache Interactions](#cache-interactions-recorder)
- [Performance](#performance)
    - [Using a Different Database](#using-a-different-database)
    - [Redis Ingest](#ingest)
    - [Sampling](#sampling)

<a name="introduction"></a>
## Introduction

Laravel Pulse delivers at-a-glance insights into your application's performance and usage. Track down bottlenecks like slow jobs and endpoints, find your most active users, and more.

For in-depth debugging of individual events, check out [Laravel Telescope](/docs/{{version}}/telescope).

<a name="installation"></a>
## Installation

> **Warning**  
> Pulse's first-party storage implementation requires a MySQL database.

You may use the Composer package manager to install Pulse into your Laravel project:

```sh
composer require laravel/pulse
```

After installing Pulse, you should run the `migrate` command in order to create the tables needed to store Pulse's data:

```sh
php artisan migrate
```

It is also possible to [configure a dedicated database connection](#using-a-different-database) for Pulse's data.

<a name="configuration"></a>
### Configuration

Many of Pulse's configuration options can be controlled using environment variables. To see the available options, register new recorders, or configure advanced options, you may publish the `config/pulse.php` configuration file:

```sh
php artisan vendor:publish --tag pulse-config
```

<a name="capturing-entries"></a>
## Capturing Entries

Most Pulse recorders will automatically capture entries based on events fired by Laravel. However, the [system stats recorder](#system-stats-recorder) and some third-party cards must poll for information regularly. To use these card, you must run the `pulse:check` daemon command on all your individual application servers:

```php
php artisan pulse:check
```

> **Note**  
> To keep the `pulse:check` process running permanently in the background, you should use a process monitor such as Supervisor to ensure that the command does not stop running.

<a name="dashboard"></a>
## Dashboard

<a name="dashboard-authorization"></a>
### Dashboard Authorization

The Pulse dashboard may be accessed at the `/pulse` route. By default, you will only be able to access this dashboard in the `local` environment, so you will need to configure authorization for your production environments by customising the `'viewPulse'` authorization gate. You can do this within your `app/Providers/AppServiceProvider.php` file:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Gate::define('viewPulse', function (User $user) {
        return $user->isAdmin();
    });

    // ...
}
```

<a name="dashboard-customization"></a>
### Dashboard Customization

The Pulse dashboard cards and layout may be configured by publishing the dashboard view:

```sh
php artisan vendor:publish --tag pulse-dashboard
```

The dashboard view will be published to `resources/views/vendor/pulse/dashboard.blade.php`.

The dashboard is powered by [Livewire](https://livewire.laravel.com/) and allows you to customize the cards and layout without needing to rebuild any JavaScript assets.

The `<x-pulse>` component is responsible for rendering the dashboard and provides a grid layout for the cards.

Each card accepts a `cols` and `rows` attribute to control how much space it takes up.

<a name="dashboard-cards"></a>
### Dashboard Cards

<a name="servers-card"></a>
#### Servers

The `<livewire:pulse.servers />` card displays system resource usage for all servers running the `pulse:check` command. See the [system stats recorder](#system-stats-recorder) for more information.

<a name="application-usage-card"></a>
#### Application Usage

The `<livewire:pulse.usage />` card displays the top 10 users making requests to your application, dispatching jobs, and experiencing slow routes.

If you wish to view all usage metrics on screen at the same time, you may include the card multiple times, specifying the `type` attribute:

```blade
<livewire:pulse.usage type="request_counts" />
<livewire:pulse.usage type="slow_endpoint_counts" />
<livewire:pulse.usage type="dispatched_job_counts" />
```

See the [requests recorder](#requests-recorder) and [jobs recorder](#jobs-recorder) sections for more information.

<a name="exceptions-card"></a>
#### Exceptions

The `<livewire:pulse.exceptions />` card shows the frequency and recency of exceptions occurring in your application. By default, exceptions are grouped based on the exception class and location where it occurred. See the [exceptions recorder](#exceptions-recorder) for more information.

<a name="queues-card"></a>
#### Queues

The `<livewire:pulse.queues />` shows the throughput of the queues in your application, including the number of jobs queued, processing, processed, released, and failed. See the [jobs recorder](#jobs-recorder) for more information.

<a name="slow-routes-card"></a>
#### Slow Routes

The `<livewire:pulse.slow-routes />` card shows the routes in your application that are exceeding the configured threshold, which is 1,000ms by default. See the [requests recorder](#requests-recorder) for more information.

<a name="slow-jobs-card"></a>
#### Slow Jobs

The `<livewire:pulse.slow-jobs />` card shows the queued jobs in your application that are exceeding the configured threshold, which is 1,000ms by default. See the [jobs recorder](#jobs-recorder) for more information.

<a name="slow-queries-card"></a>
#### Slow Queries

The `<livewire:pulse.slow-queries />` card shows the database queries in your application that are exceeding the configured threshold, which is 1,000ms by default.

By default, exceptions are grouped based on the parameterized SQL query and location where it occurred, but you may opt not to capture the location if you only wish to group them based on the SQL query.

See the [slow queries recorder](#slow-queries-recorder) for more information.

<a name="slow-outgoing-requests-card"></a>
#### Slow Outgoing Requests

The `<livewire:pulse.slow-outgoing-requests />` shows outgoing requests made using Laravel's [HTTP client](/docs/{{version}}/http-client) that are exceeding the configured threshold, which is 1,000ms by default.

By default, entries will be grouped by the full URL. However you may wish to normalize or group similar outgoing requests using a regular expression. See the [outgoing requests recorder](#outgoing-requests-recorder) for more information.

<a name="cache-card"></a>
#### Cache

The `<livewire:pulse.cache />` card shows the cache hit and miss ratio for your application globally and for individual keys.

By default, entries will be grouped by the key. However you may wish to normalize or group similar keys. See the [cache interactions recorder](#cache-interactions-recorder) for more information.

<a name="recorders"></a>
## Recorders

Recorders are responsible for capturing entries from your application to be recorded in the Pulse database. Recorders are registered and configured in the `recorders` section of the [Pulse configuration file](#configuration).

<a name="system-stats-recorder"></a>
### System Stats

The `SystemStats` recorder captures CPU, memory, and storage usage of the servers that power your application to display on the [Servers](#servers-card) card. This recorder requires the [`pulse:check` command](#capturing-entries) to be running on each of the servers you wish to monitor.

Each reporting server must have a unique name. By default, Pulse will use the value returned by PHP's `gethostname` function. If you wish to customize this, you may set the `PULSE_SERVER_NAME` environment variable:

```env
PULSE_SERVER_NAME=load-balancer
```

The configuration also allows you to customize the monitored directories and whether the graph data should be aggregated using either the average or maximum value.

<a name="requests-recorder"></a>
### Requests

The `Requests` recorder captures information about every request made to your application for display on the [Application Usage](#application-usage-card) and [Slow Routes](#slow-routes-card) cards.

You may optionally adjust the slow route threshold, sample rate, and ignored paths.

<a name="jobs-recorder"></a>
### Jobs

The `Jobs` recorder captures information about your applications queues for display on the [Queues](#queues-card) and [Slow Jobs](#slow-jobs-card) cards.

You may optionally adjust the slow job threshold, [sample rate](#sampling), and ignored job patterns.

<a name="slow-queries-recorder"></a>
### Slow Queries

The `SlowQueries` recorder captures any database queries in your application that exceed the configured threshold for display on the [Slow Queries](#slow-queries-card) card.

You may optionally adjust the slow query threshold, [sample rate](#sampling), and ignored query patterns. You may also configure whether to capture the query location. The captured location will be displayed on the Pulse dashboard which can help to track down the query origin. However, if the same query is made in multiple locations then it will appear multiple times for each unique location.

<a name="outgoing-requests-recorder"></a>
### Outgoing Requests

The `OutgoingRequests` recorder captures information about outgoing HTTP requests made using Laravel's [HTTP client](/docs/{{version}}/http-client) for display on the [Slow Outgoing Requests](#slow-outgoing-requests-card) card.

You may optionally adjust the slow outgoing request threshold, [sample rate](#sampling), and ignored URL patterns.

You may also configure URL grouping so that similar URLs are grouped as a single entry. For example, you may wish to remove unique IDs from URL paths or group by domain only. Groups are configured using a regular expression to "find and replace" parts of the URL. Some examples are included in the configuration file:

```php
Recorders\OutgoingRequests::class => [
    // ...
    'groups' => [
        // '#^https://api\.github\.com/repos/.*$#' => 'api.github.com/repos/*',
        // '#^https?://([^/]*).*$#' => '\1',
        // '#/\d+#' => '/*',
    ],
],
```

The first pattern that matches will be used. If no patterns match, then the URL will be captured as-is.

If you wish to apply new group configuration to existing entries, you may use the `pulse:regroup` command:

```
php artisan pulse:regroup
```

<a name="exceptions-recorder"></a>
### Exceptions

The `Exceptions` recorder captures information about reportable exceptions occurring in your application for display in the [Exceptions](#exceptions-card) card.

You may optionally adjust the [sample rate](#sampling), and ignored exceptions patterns. You may also configure whether to capture the location that the exception originated from. The captured location will be displayed on the Pulse dashboard which can help to track down the exception origin. However, if the same exception occurs in multiple locations then it will appear multiple times for each unique location.

<a name="cache-interactions-recorder"></a>
### Cache Interactions

The `CacheInteractions` recorder captures information about the [cache](/docs/{{version}}/cache) hits and misses occurring in your application.

You may optionally adjust the [sample rate](#sampling), and ignored key patterns.

You may also configure key grouping so that similar keys are grouped as a single entry. For example, you may wish to remove unique IDs from keys caching the same type of information. Groups are configured using a regular expression to "find and replace" parts of the key. An example is included in the configuration file:

```php
Recorders\CacheInteractions::class => [
    // ...
    'groups' => [
        // '/:\d+/' => ':*',
    ],
],
```

The first pattern that matches will be used. If no patterns match, then the key will be captured as-is.

If you wish to apply new group configuration to existing entries, you may use the `pulse:regroup` command:

```
php artisan pulse:regroup
```

<a name="performance"></a>
## Performance

Pulse has been designed to drop-in to an existing application without requiring any additional infrastructure. However, for high-traffic applications, there are several ways of removing any impact Pulse may have on your application performance.

<a name="using-a-different-database"></a>
### Using a Different Database

For high-traffic applications, you may prefer to use a dedicated database connection for Pulse to avoid impacting your application database, especially when viewing the Pulse dashboard.

You may customize the [database connection](/docs/{{version}}/database#configuration) used by Pulse by setting the `PULSE_DB_CONNECTION` environment variable.

```env
PULSE_DB_CONNECTION=pulse
```

<a name="ingest"></a>
## Redis Ingest

By default, Pulse will store entries directly to the [configured database connection](#using-a-different-database) after the request has been returned or a job has been processed.

Pulse may be configured to send entries to a Redis stream instead, so that a dedicated background process can store the entries to your database.

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

<a name="sampling"></a>
## Sampling

By default, Pulse will capture every relevant event that occurs in your application. For high-traffic applications, this can result in needing to aggregate millions of database rows in the dashboard, especially for the longer time periods.

You may instead opt to enabling sampling on the recorders of your choosing. For example, setting the sample rate to 0.1 on the [`Requests`](#requests-recorder) recorder will mean that you only record approximately 10% of the requests to your application. In the dashboard, the values will be scaled up and prefixed with a `~` to indicate that they are an approximation.

As a general rule, the more entries you have for a particular metric, the lower you can set the sample rate without sacrificing too much accuracy. Each recorder may be sampled independently based on your application.
