# Alight
Alight is a light-weight PHP framework. Easily and quickly build high performance RESTful web applications. Out-of-the-box built-in routing, database, caching, error handling, logging and job scheduling libraries. Focus on creating solutions for the core process of web applications. Keep simple and extensible.

## Alight Family

| Project                                                     | Description                                                                       |
| ----------------------------------------------------------- | --------------------------------------------------------------------------------- |
| [Alight](https://github.com/juneszh/alight)                 | Basic framework built-in routing, database, caching, etc.                         |
| [Alight-Admin](https://github.com/juneszh/alight-admin)     | A full admin panel extension based on Alight. No front-end coding required.       |
| [Alight-Project](https://github.com/juneszh/alight-project) | A template for beginner to easily create web applications by Alight/Alight-Admin. |

## Requirements
PHP 7.4+

## Getting Started
* [Installation](#installation)
* [Configuration](#configuration)
* [Routing](#routing)
* [Database](#database)
* [Caching](#caching)
* [Error Handling](#error-handling)
* [Job Scheduling](#job-scheduling)
* [Helpers](#helpers)

## Installation
### Step 1: Install Composer
Don’t have Composer? [Install Composer](https://getcomposer.org/download/) first.

### Step 2: Creating Project
#### Using template with create-project
```bash
$ composer create-project juneszh/alight-project {PROJECT_DIRECTORY}
```
The project template contains common folder structure, suitable for MVC pattern, please refer to: [Alight-Project](https://github.com/juneszh/alight-project).

*It is easy to customize folders by modifying the configuration. But the following tutorials are based on the template configuration.*

### Step 3: Configuring a Web Server
Nginx example (Nginx 1.17.10, PHP 7.4.3, Ubuntu 20.04.3):
```nginx
server {
    listen 80;
    listen [::]:80;

    root /var/www/{PROJECT_DIRECTORY}/public;

    index index.php;

    server_name {YOUR_DOMAIN};

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    }
}
```

## Configuration
All of the configuration options for the Alight framework will be imported from the file 'config/app.php', which you need to create yourself. For example:

File: config/app.php
```php
<?php

return [
    'app' => [
        'debug' => false,
        'timezone' => 'Europe/Kiev',
        'storagePath' => 'storage',
        'domainLevel' => 2,
        'corsDomain' => null,
        'corsHeaders' => null,
        'corsMethods' => null,
        'cacheAdapter' => null,
        'errorHandler' => null,
        'errorPageHandler' => null,
    ],
    'route' => 'config/route/web.php',
    'database' => [
        'type' => 'mysql',
        'host' => '127.0.0.1',
        'database' => 'alight',
        'username' => 'root',
        'password' => '',
    ],
    'cache' => [
        'type' => 'file',
    ],
    'job' => 'config/job.php',
];
```

### Get some items in the config
```php
<?php

Alight\Config::get('app');
Alight\Config::get('app', 'storagePath');
```

### Available Configuration
See [Config.php](./src/Config.php) for details.

## Routing
Before learning routing rules, you need to create a php file first that stores routing rules. Because the routing cache is updated or not, it is based on the modification time of the routing file. For example:

File: config/route/web.php
```php
Alight\Route::get('/', 'Controller::index');
```

File: config/app.php
```php
<?php

return [
    'route' => 'config/route/web.php'
    // Also supports multiple files
    // 'route' => ['config/route/web.php', config/route/api.php'] 
];
```

By the way, the route configuration supports importing specified files for **subdomains**:
```php
<?php

return [
    'route' => [
        //Import on any request
        '*' => 'config/route/web.php', 
        //Import when requesting admin.yourdomain.com
        'admin' => 'config/route/admin.php', 
        //Import multiple files when requesting api.yourdomain.com
        'api' => ['config/route/api.php', 'config/route/api_mobile.php'], 
    ]
];
```

### Basic Usage
```php
Alight\Route::get($pattern, $handler);
// Example
Alight\Route::get('/', 'Controller::index');
Alight\Route::get('/', ['Controller', 'index']);
// Or try this to easy trigger hints from IDE
Alight\Route::get('/', [Controller::class, 'index']);
// With default args
Alight\Route::get('post/list[/{page}]', [Controller::class, 'list'], ['page' => 1]);

// Common HTTP request methods
Alight\Route::options('/', 'handler');
Alight\Route::head('/', 'handler');
Alight\Route::post('/', 'handler');
Alight\Route::delete('/', 'handler');
Alight\Route::put('/', 'handler');
Alight\Route::patch('/', 'handler');

// Map for Custom methods
Alight\Route::map(['GET', 'POST'], 'test', 'handler');

// Any for all common methods
Alight\Route::any('test', 'handler');
```

### Regular Expressions
```php
// Matches /user/42, but not /user/xyz
Alight\Route::get('user/{id:\d+}', 'handler');

// Matches /user/foobar, but not /user/foo/bar
Alight\Route::get('user/{name}', 'handler');

// Matches /user/foo/bar as well, using wildcards
Alight\Route::get('user/{name:.+}', 'handler');

// The /{name} suffix is optional
Alight\Route::get('user[/{name}]', 'handler');

// Root wildcards for single page app
Alight\Route::get('/{path:.*}', 'handler');
```
**nikic/fast-route** handles all regular expressions in the routing path. See [FastRoute Usage](https://github.com/nikic/FastRoute#defining-routes) for details.

### Options

#### Group
```php
Alight\Route::group('admin');
// Matches /admin/role/list
Alight\Route::get('role/list', 'handler');
// Matches /admin/role/info
Alight\Route::get('role/info', 'handler');

// Override the group
Alight\Route::group('api');
// Matches /api/news/list
Alight\Route::get('news/list', 'handler');
```

#### Customize 'any'
You can customize the methods contained in `Alight\Route::any()`.
```php
Alight\Route::setAnyMethods(['GET', 'POST']);
Alight\Route::any('only/get/and/post', 'handler');
```

#### Before handler
If you want to run some common code before route's handler.
```php
// For example log every hit request
Alight\Route::beforeHandler([svc\Request::class, 'log']);

Alight\Route::get('test', 'handler');
Alight\Route::post('test', 'handler');
```



#### Disable route caching
Not recommended, but if your code requires: 
```php
// Effective in the current route file
Alight\Route::disableCache();
```

#### Life cycle
All routing options only take effect in the current file and will be auto reset by `Alight\Route::init()` before the next file is imported. For example:

File: config/admin.php
```php
Alight\Route::group('admin');
Alight\Route::setAnyMethods(['GET', 'POST']);

// Matches '/admin/login' by methods 'GET', 'POST'
Alight\Route::any('login', 'handler');
```

File: config/web.php
```php
// Matches '/login' by methods 'GET', 'POST', 'PUT', 'DELETE', etc
Alight\Route::any('login', 'handler');
```

### Utilities
#### Cache-Control header
Send a Cache-Control header to control caching in browsers and shared caches (CDN) in order to optimize the speed of access to unmodified data.
```php
// Cache one day
Alight\Route::get('about/us', 'handler')->cache(86400);
// Or force disable cache
Alight\Route::put('user/info', 'handler')->cache(0);
```

#### Handling user authorization
We provide a simple authorization handler to manage user login status.
```php
// Define a global authorization verification handler
Alight\Route::authHandler([\svc\Auth::class, 'verify']);

// Enable verification in routes
Alight\Route::get('user/info', 'handler')->auth();
Alight\Route::get('user/password', 'handler')->auth();

// No verification by default
Alight\Route::get('about/us', 'handler');

// In general, routing with authorization will not use browser cache
// So auth() has built-in cache(0) to force disable cache
// Please add cache(n) after auth() to override the configuration if you need
Alight\Route::get('user/rank/list', 'handler')->auth()->cache(3600);
```
File: app/service/Auth.php
```php
namespace svc;

class Auth
{
    public static function verify()
    {
        // Some codes about get user session from cookie or anywhere
        // Returns the user id if authorization is valid
        // Otherwise returns 0 or something else for failure
        // Then use Router::getAuthId() in the route handler to get this id again
        return $userId;
    }
}
```

#### Request cooldown
Many times the data submitted by the user takes time to process, and we don't want to receive the same data before it's processed. So we need to set the request cooldown time. The user will receive a 429 error when requesting again within the cooldown.
```php
// Cooldown only takes effect when authorized
Alight\Route::put('user/info', 'handler')->auth()->cd(2);
Alight\Route::post('user/status', 'handler')->auth()->cd(2);
```

#### Cross-Origin Resource Sharing (CORS)
When your API needs to be used for Ajax requests by a third-party website (or your project has multiple domains), you need to send a set of CORS headers. For specific reasons, please refer to: [Mozilla docs](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS).

```php
// Domains in config will receive the common cors header
Alight\Route::put('share/config', 'handler')->cors(); 

// The specified domain will receive the common cors header
Alight\Route::put('share/specified', 'handler')->cors('abc.com');

// The specified domain will receive the specified cors header
Alight\Route::put('share/specified2', 'handler')->cors('abc.com', 'Authorization', ['GET', 'POST']);

// All domains will receive a 'Access-Control-Allow-Origin: *' header
Alight\Route::put('share/all/http', 'handler')->cors('*'); 

// All domains will receive a 'Access-Control-Allow-Origin: [From Origin]' header
Alight\Route::put('share/all/https', 'handler')->cors('origin');
```
*If your website is using CDN, please use this utility carefully. To avoid request failure after the header is cached by CDN.*


## Database
Alight passes the 'database' configuration to the **catfan/medoo** directly. For specific configuration options, please refer to [Medoo Get Started](https://medoo.in/api/new). For example:

File: config/app.php
```php
<?php

return [
    'database' => [
        'type' => 'mysql',
        'host' => '127.0.0.1',
        'database' => 'alight',
        'username' => 'root',
        'password' => '',
    ],
    // Multiple databases (The first database is default)
    // 'database' => [
    //     'main' => [
    //         'type' => 'mysql',
    //         'host' => '127.0.0.1',
    //         'database' => 'alight',
    //         'username' => 'root',
    //         'password' => '',
    //     ],
    //     'remote' => [
    //         'type' => 'mysql',
    //         'host' => '1.1.1.1',
    //         'database' => 'alight',
    //         'username' => 'root',
    //         'password' => '',
    //     ],
    // ]
];
```

### Basic Usage
`Alight\Database::init()` is a static and single instance implementation of `new Medoo\Medoo()`, so it inherits all functions of `Medoo()`. Single instance makes each request connect to the database only once and reuse it, effectively reducing the number of database connections.
```php
// Initializes the default database
$db = \Alight\Database::init();
// Initializes others database with key
$db2 = \Alight\Database::init('remote');

$userList = $db->select('user', '*', ['role' => 1]);
$userInfo = $db->get('user', '*', ['id' => 1]);

$db->insert('user', ['name' => 'anonymous', 'role' => 2]);
$id = $db->id();

$result = $db->update('user', ['name' => 'alight'], ['id' => $id]);
$result->rowCount();

```

See [Medoo Documentation](https://medoo.in/doc) for usage details.

## Caching
Alight supports multiple cache drivers and multiple cache interfaces with **symfony/cache**. The configuration options 'dsn' and 'options' will be passed to the cache adapter, more details please refer to [Available Cache Adapters](https://symfony.com/doc/current/components/cache.html#available-cache-adapters). For example:

File: config/app.php
```php
<?php

return [
    'cache' => [
        'type' => 'file',
    ],
    // Multiple cache (The first cache is the default)
    // 'cache' => [
    //     'file' => [
    //         'type' => 'file',
    //     ],
    //     'memcached' => [
    //         'type' => 'memcached',
    //         'dsn' => 'memcached://localhost',
    //         'options' => [],
    //     ],
    //     'redis' => [
    //         'type' => 'redis',
    //         'dsn' => 'redis://localhost',
    //         'options' => [],
    //     ],
    // ]
];
```

### Basic Usage (PSR-16)
Like database, `Alight\Cache::init()` is a static and single instance implementation of the cache client to improve concurrent request performance.

```php
// Initializes the default cache
$cache = \Alight\Cache::init();
// Initializes others cache with key
$cache2 = \Alight\Cache::init('redis');

// Use SimpleCache(PSR-16) interface
if (!$cache->has('test')){
    $cache->set('test', 'hello world!', 3600);
}
$cacheData = $cache->get('test');
$cache->delete('test');
```

### PSR-6 Interface
```php
$cache6 = \Alight\Cache::psr6('memcached');
$cacheItem = $cache6->getItem('test');
if (!$cacheItem->isHit()){
    $cacheItem->expiresAfter(3600);
    $cacheItem->set('hello world!');
    // Bind to a tag
    $cacheItem->tag('alight');
}
$cacheData = $cacheItem->get();
$cache6->deleteItem('test');
// Delete all cached items in the same tag
$cache6->invalidateTags('alight')

// Or symfony/cache adapter style
$cacheData = $cache6->get('test', function ($item){
    $item->expiresAfter(3600);
    return 'hello world!';
});
$cache6->delete('test');
```

### Native Interface
Also supports memcached or redis native interfaces for using advanced caching:
```php
$memcached = \Alight\Cache::memcached('memcached');
$memcached->increment('increment');

$redis = \Alight\Cache::redis('redis');
$redis->lPush('list', 'first');
```

### More Adapter
**symfony/cache** supports more than 10 adapters, but we only have built-in 3 commonly used, such as filesystem, memcached, redis. If you need more adapters, you can expand it. For example:

File: config/app.php
```php
<?php

return [
    'app' => [
        'cacheAdapter' => [svc\Cache::class, 'adapter'],
    ],
    'cache' => [
        // ...
        'apcu' => [
            'type' => 'apcu'
        ],
        'array' => [
            'type' => 'array',
            'defaultLifetime' => 3600
        ]
    ]
];

```

File: app/service/Cache.php
```php
namespace svc;

use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;

class Cache
{
    public static function adapter(array $config)
    {
        switch ($config['type']) {
            case 'apcu':
                return new ApcuAdapter();
                break;
            case 'array':
                return new ArrayAdapter($config['defaultLifetime']);
            default:
                return new NullAdapter();
                break;
        }
    }
}
```

See [Symfony Cache Component](https://symfony.com/doc/current/components/cache.html) for more information.

## Error Handling
Alight catches all errors via `Alight\App::start()`. When turn on 'debug' in the app configuration, errors will be output in pretty html (by **filp/whoops**) or JSON.

File: config/app.php
```php
<?php

return [
    'app' => [
        'debug' => true,
    ]
];

```

### Custom Handler
When turn off 'debug' in production environment, Alight just logs errors to file and outputs HTTP status. 
You can override these default behaviors by app configuration. For example:

File: config/app.php
```php
<?php

return [
    'app' => [
        'errorHandler' => [svc\Error::class, 'catch'],
        'errorPageHandler' => [svc\Error::class, 'page'],
    ]
];

```

File: app/service/Error.php
```php
namespace svc;

class Error
{
    public static function catch(Throwable $exception)
    {
        // Some code like sending an email or using Sentry or something
    }

    public static function page(int $status)
    {
        switch ($status) {
            case 400:
                // Page code...
                break;
            case 401:
                // Page code...
                break;
            case 403:
                // Page code...
                break;
            case 404:
                // Page code...
                break;
            case 500:
                // Page code...
                break;
            default:
                // Page code...
                break;
        }
    }
}
```

## Job Scheduling

If you need to run php scripts in the background periodically.
### Step 1: Setting Up CRON
```bash
$ sudo contab -e
```
Add the following to the end line:
```bash
* * * * * sudo -u www-data /usr/bin/php /var/www/{PROJECT_DIRECTORY}/app/scheduler.php >> /dev/null 2>&1
```

### Step 2: Create Jobs
File: config/job.php
```php
Alight\Job::call('handler')->minutely();
Alight\Job::call('handler')->hourly();
Alight\Job::call('handler')->daily();
Alight\Job::call('handler')->weekly();
Alight\Job::call('handler')->monthly();
Alight\Job::call('handler')->yearly();
Alight\Job::call('handler')->everyMinutes(5);
Alight\Job::call('handler')->everyHours(2);
Alight\Job::call('handler')->date('2022-08-02 22:00');
```

### Tips
Each handler runs only one process at a time, and the default max runtime of a process is 1 hour. If your handler needs a longer runtime, use timeLimit().
```php
Alight\Job::call('handler')->hourly()->timeLimit(7200);// 7200 seconds
```

## Helpers

### Project Root Path
Alight provides `Alight\App::root()` to standardize the format of file paths in project. 

```php
// Suppose the absolute path of the project is /var/www/my_project/
\Alight\App::root('public/favicon.ico'); // /var/www/my_project/public/favicon.ico

// Of course, you can also use absolute path files with the first character  '/'
\Alight\App::root('/var/data/config/web.php');
```

The file paths in the configuration are all based on the `Alight\App::root()`. For example:
```php
Alight\App::start([
    'route' => 'config/route/web.php',     // /var/www/my_project/config/route/web.php
    'job' => 'config/job.php'          // /var/www/my_project/config/job.php
]);
```
### API Response
Alight provides `Alight\Response::api()` to standardize the format of API Response. 
```php
HTTP 200 OK

{
    "error": 0,      // API error code
    "message": "OK", // API status description
    "data": {}       // Object data
}
```
Status Definition:
| HTTP Status | API Error | Description                                                  |
| ----------: | --------: | ------------------------------------------------------------ |
|         200 |         0 | OK                                                           |
|         200 |      1xxx | General business errors, only display message to user        |
|         200 |      2xxx | Special business errors, need to define next action for user |
|         4xx |       4xx | Client errors                                                |
|         5xx |       5xx | Server errors                                                |

For example:
```php
\Alight\Response::api(0, null, ['name' => 'alight']);
// Response:
// HTTP 200 OK
//
// {
//     "error": 0,
//     "message": "OK",
//     "data": {
//         "name": "alight"
//     }
// }

\Alight\Response::api(1001, 'Invalid request parameter.');
// Response:
// HTTP 200 OK
//
// {
//     "error": 1001,
//     "message": "Invalid request parameter.",
//     "data": {}
// }

\Alight\Response::api(500, 'Unable to connect database.');
// Response:
// HTTP 500 Internal Server Error
//
// {
//     "error": 500,
//     "message": "Unable to connect database.",
//     "data": {}
// }
```



### Views
Alight provides `Alight\Response::render()` to display a view template call the render method with the path of the template file and optional template data:

File: app/controller/Pages.php
```php
namespace ctr;
class Pages
{
    public static function index()
    {
        \Alight\Response::render('hello.php', ['name' => 'Ben']);
    }
}
```

File: app/view/hello.php
```php
<h1>Hello, <?= $name ?>!</h1>
```

File: config/route/web.php
```php
Alight\Route::get('/', [ctr\Pages::class, 'index']);
```

The project's homepage output would be:
```php
Hello, Ben!
```

### Others
There are also some useful helpers placed in different namespaces. Please click the file for details:

| Namespace       | File                               |
| --------------- | ---------------------------------- |
| Alight\Request  | [Request.php](./src/Request.php)   |
| Alight\Response | [Response.php](./src/Response.php) |
| Alight\Utility  | [Utility.php](./src/Utility.php)   |

## Credits
* Composer requires
    * [nikic/fast-route](https://github.com/nikic/FastRoute)
    * [catfan/medoo](https://medoo.in)
    * [symfony/cache](https://symfony.com/doc/current/components/cache.html)
    * [monolog/monolog](https://github.com/Seldaek/monolog)
    * [filp/whoops](https://github.com/filp/whoops)
    * [voku/html-min](https://github.com/voku/HtmlMin)
* Special thanks
    * [mikecao/flight](https://flightphp.com)
 

## License
* [MIT license](./LICENSE)