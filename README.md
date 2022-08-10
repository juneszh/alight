# Alight
Alight is a lightweight framework for php. Easily and quickly build high performance RESTful web applications. Out-of-the-box built-in routing, database, caching, error handling, logging and job scheduling libraries. Focus on creating solutions for the core process of web applications. Keep it simple and extensible.

## Requirements
PHP 7.4+

## Getting Started
* [Installation](#installation)
* [Configuration](#configuration)
* [Routing](#routing)
* [Database](#database)
* [Caching](#caching)
* [Error Handling](#error-handling)
* [Helpers](#helpers)

## Installation
### Step 1: Install Composer
Don’t have Composer? [Install Composer](https://getcomposer.org/download/) first.

### Step 2: Creating Project
#### Using template with create-project
```bash
$ composer create-project juneszh/alight-project {YOUR_PROJECT}
```
The project template contains common folder structure, suitable for MVC pattern, please refer to: [Alight Project](https://github.com/juneszh/alight-project).

*It is easy to customize folders by modifying the configuration. But the following tutorials are based on the template configuration.*

### Step 3: Configuring a Web Server
Nginx example (Nginx 1.17.10, PHP 7.4.3, Ubuntu 20.04.3):
```nginx
server {
    listen 80;
    listen [::]:80;

    root /var/www/html/{YOUR_PROJECT}/public;

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

### Optionally: Setting up a Job Scheduler
If you need to run php scripts in the background periodically.
```bash
$ contab -e
```
Add the following to the end line:
```bash
* * * * * sudo -u www-data /usr/bin/php /var/www/html/{YOUR_PROJECT}/app/scheduler.php >> /dev/null 2>&1
```

## Configuration
All of the configuration options for the Alight framework are initialized by `Alight\start()`

```php
// Import a configuration file (recommended)
// You can find this code in the file "config/bootstrap.php"
Alight\start('config/app.php');

// It is also possible import the configuration array directly
Alight\start([
    'app' => [
        'debug' => true,
        'timezone' => 'Europe/Kiev'
    ],
    'route' => 'config/route.php',
    'database' => [
        'type' => 'mysql',
        'server' => '127.0.0.1',
        'database' => 'alight',
        'username' => 'root',
        'password' => '',
    ],
]);
```

### Available configuration
See [Config.php](./src/Config.php) for details.

## Routing
Before learning routing rules, you need to create a php file first that stores routing rules. Because the routing cache is updated or not, it is based on the modification time of the routing file. For example:

File: config/route.php
```php
Route::get('/', 'Controller::index');
```

File: config/app.php
```php
return [
    'route' => 'config/route.php'
    // Also supports multiple files
    // 'route' => ['config/home.php', config/user.php'] 
];
```

By the way, the route configuration supports importing specified files for **subdomains**:
```php
return [
    'route' => [
        //Import on any request
        '*' => 'config/route/root.php', 
        //Import when requesting admin.yourdomain.com
        'admin' => 'config/route/admin.php', 
        //Import multiple files when requesting api.yourdomain.com
        'api' => ['config/route/api.php', 'config/route/api_mobile.php'], 
    ]
];
```

### Basic usage
```php
Route::get($pattern, $handler);
// Example
Route::get('/', 'Controller::index');
Route::get('/', ['Controller', 'index']);
// Or try this to easy trigger hints from IDE
Route::get('/', [Controller::class, 'index']);

// Common HTTP request methods
Route::head('/test', 'handler');
Route::post('/test', 'handler');
Route::delete('/test', 'handler');
Route::put('/test', 'handler');
Route::options('/test', 'handler');
Route::trace('/test', 'handler');
Route::patch('/test', 'handler');

// Map for Custom methods
Route::map(['GET', 'POST'], '/test', 'handler');

// Any for all common methods
Route::any('/test', 'handler');
```

### Regular Expressions
```php
// Matches /user/42, but not /user/xyz
Route::get('/user/{id:\d+}', 'handler');

// Matches /user/foobar, but not /user/foo/bar
Route::get('/user/{name}', 'handler');

// Matches /user/foo/bar as well
Route::get('/user/{name:.+}', 'handler');
```
**nikic/fast-route** handles all regular expressions in the routing path. See [FastRoute Usage](https://github.com/nikic/FastRoute#defining-routes) for details.

### Options

#### Group
```php
Route::group('/admin');
// Matches /admin/role/list
Route::get('/role/list', 'handler');
// Matches /admin/role/info
Route::get('/role/info', 'handler');

// Override the group
Route::group('/api');
// Matches /api/news/list
Route::get('/news/list', 'handler');
```

#### Customize 'any'
You can customize the methods contained in `Route::any()`.
```php
Route::setAnyMethods(['GET', 'POST']);
Route::any('/only/get/and/post', 'handler');
```

#### Before handler
If you want to run some common code before route's handler.
```php
// For example log every hit request
Route::beforeHandler([svc\Request::class, 'log']);

Route::get('/test', 'handler');
Route::post('/test', 'handler');
```



#### Disable route caching
Not recommended, but if your code requires: 
```php
// Effective in the current route file
Route::disableCache();
```

#### Life cycle
All routing options only take effect in the current file and will be auto reset by `route::init()` before the next file is imported. For example:

File: config/admin.php
```php
Route::group('/admin');
Route::setAnyMethods(['GET', 'POST']);

// Matches '/admin/login' by methods 'GET', 'POST'
Route::any('/login', 'handler');
```

File: config/www.php
```php
// Matches '/login' by methods 'GET', 'POST', 'PUT', 'DELETE', etc
Route::any('/login', 'handler');
```

### Utilities
#### Cache-Control header
Send a Cache-Control header to control caching in browsers and shared caches (CDN) in order to optimize the speed of access to unmodified data.
```php
// Cache one day
Route::get('/about/us', 'handler')->cache(86400);
// Or force disable cache
Route::put('/user/info', 'handler')->cache(0);
```

#### Handling user authorization
We provide a simple authorization handler to manage user login status.
```php
// Define a global authorization verification handler
Route::authHandler([\svc\Auth::class, 'verify']);

// Enable verification in routes
Route::get('/user/info', 'handler')->auth();
Route::get('/user/password', 'handler')->auth();

// No verification by default
Route::get('/about/us', 'handler');

// In general, routing with authorization will not use browser cache
// So auth() has built-in cache(0) to force disable cache
// Please add cache(n) after auth() to override the configuration if you need
Route::get('/user/rank/list', 'handler')->auth()->cache(3600);
```
File: app/Services/Auth.php
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
Route::put('/user/info', 'handler')->auth()->cd(2);
Route::post('/user/status', 'handler')->auth()->cd(2);
```

#### Cross-Origin Resource Sharing (CORS)
When your API needs to be used for Ajax requests by a third-party website (or your project has multiple domains), you need to send a set of CORS headers. For specific reasons, please refer to: [Mozilla docs](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS).

```php
// All third-party domains will receive the cors header
Route::put('/share/all', 'handler')->cors(); 

// The specified domain will receive the cors header
Route::put('/share/partner', 'handler')->cors('abc.com'); 

// All domains will receive a 'Access-Control-Allow-Origin: *' header
Route::put('/share/partner', 'handler')->cors('*');
```
*If your website is using CDN, please use this utility carefully. To avoid request failure after the header is cached by CDN.*


## Database
Alight passes the 'database' configuration to the **catfan/medoo** directly. For specific configuration options, please refer to [Medoo Get Started](https://medoo.in/api/new). For example:

File: config/app.php
```php
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

### Basic usage
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

### Basic usage (PSR-16)
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

### PSR-6 interface
```php
$cache = \Alight\Cache::psr6('memcached');
$cacheItem = $cache->getItem('test');
if (!$cacheItem->isHit()){
    $cacheItem->expiresAfter(3600);
    $cacheItem->set('hello world!');
}
$cacheData = $cacheItem->get();
$cache->deleteItem('test');

// Or symfony/cache adapter style
$cacheData = $cache->get('test', function ($item){
    $item->expiresAfter(3600);
    return 'hello world!';
});
$cache->delete('test');
```

### Native interface
Also supports memcached or redis native interfaces for using advanced caching:
```php
$memcached = \Alight\Cache::memcached('memcached');
$memcached->increment('increment');

$redis = \Alight\Cache::redis('redis');
$redis->lPush('list', 'first');
```

### More adapter
**symfony/cache** supports more than 10 adapters, but we only have built-in 3 commonly used, such as filesystem, memcached, redis. If you need more adapters, you can expand it. For example:

File: config/app.php
```php
return [
    'app' => [
        'cacheAdapter' => [\svc\Cache::class, 'adapter'],
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

File: app/Services/Cache.php
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
Alight catches all errors via `Alight\start()` and saves to log file by default. When you turn on 'debug' in the app configuration, errors will be automatically output in pretty html (by **filp/whoops**) or JSON.

File: config/app.php
```php
return [
    'app' => [
        'debug' => true,
    ]
];

```

### Custom handler
Also supports custom error handler to override built-in one. For example:

File: config/app.php
```php
return [
    'app' => [
        'errorHandler' => [\svc\Error::class, 'catch'],
    ]
];

```

File: app/Services/Error.php
```php
namespace svc;

class Error
{
    public static function catch()
    {
        set_exception_handler([self::class, 'handler']);
    }

    public static function handler(Throwable $exception)
    {
        // Some codes ...
    }
}
```

## Helpers
### API Response
Alight provides `Alight\apiResponse()` to standardize the format of API Response. 
```php
HTTP 200 OK

{
    "error": 0,      // API error code
    "message": "OK", // API status description
    "data": {}       // Object data
}
```
Status Definition:
| Http Status | API Error | Description    |
| ---: | ----: | ------ |
| 200  | 0     | OK |
| 200  | 1xxx  | General business errors, only display message to user |
| 200  | 2xxx  | Special business errors, need to define next action for user |
| 4xx  | 4xx   | Client errors |
| 5xx  | 5xx   | Server errors |

See [HTTP response status codes](https://developer.mozilla.org/en-US/docs/Web/HTTP/Status) for more http status.

### Project root path
Alight provides `Alight\rootPath()` to standardize the format of file paths in project. 

```php
// Suppose the absolute path of the project is /var/www/html/my_project/
Alight\rootPath('public/favicon.ico'); // /var/www/html/my_project/public/favicon.ico

// Of course, you can also use absolute path files with the first character  '/'
Alight\rootPath('/var/data/config/web.php');
```

The file paths in the configuration are all based on the `Alight\rootPath()`. For example:
```php
Alight\start([
    'route' => 'route.php',     // /var/www/html/my_project/route.php
    'job' => 'job.php'          // /var/www/html/my_project/job.php
]);
```

### Views
Alight provides `Alight\render()` to display a view template call the render method with the path of the template file and optional template data:

File: config/app.php
```php
return [
    'viewPath' => 'app/Views' // Define the default view path
]);
```

File: app/Controllers/Pages.php
```php
namespace ctr;
class Pages
{
    public static function index()
    {
        \Alight\render('hello.php', ['name' => 'Ben']);
    }
}
```

File: app/Views/hello.php
```html
<h1>Hello, <?= $name ?>!</h1>
```

File: config/route.php
```php
Alight\Route::get('/', [ctr\Pages::class, 'index']);
```

The project's homepage output would be:
```php
Hello, Ben!
```

### Others
See [functions.php](./src/functions.php) for more others help.

## Credits
* Composer requires
    * [nikic/fast-route](https://github.com/nikic/FastRoute/)
    * [catfan/medoo](https://medoo.in/)
    * [symfony/cache](https://symfony.com/doc/current/components/cache.html)
    * [monolog/monolog](https://github.com/Seldaek/monolog/)
    * [filp/whoops](https://github.com/filp/whoops/)
* Special thanks
    * [mikecao/flight](https://flightphp.com/)
 

## License
* [MIT license](./LICENSE)