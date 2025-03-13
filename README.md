# ODY Framework Documentation

> [!WARNING]
> 🚧 !! This is a WIP, build completely from scratch. This will replace ody-core which was build on top of Slim framework. !!

## Introduction

ODY is a modern PHP API framework built with a focus on high performance and modern architecture. It leverages 
Swoole's coroutines for asynchronous processing, follows PSR standards for interoperability, and provides a 
clean architecture for building robust APIs.

!! Swoole not fully implemented yet !!

## Key Features

- **High Performance**: Built with Swoole support for asynchronous processing and coroutines
- **PSR Compliance**: Implements PSR-7, PSR-15, and PSR-17 for HTTP messaging and middleware
- **Modern PHP**: Requires PHP 8.3+ and takes advantage of modern language features
- **Modular Design**: Build and integrate different modules with a clean architecture
- **Middleware System**: Powerful middleware system for request/response processing
- **Dependency Injection**: Built-in IoC container for dependency management
- **Console Support**: CLI commands for various tasks and application management
- **Routing**: Simple and flexible routing system with support for route groups and middleware

## Installation

### Requirements

- PHP 8.3 or higher
- Swoole PHP extension (≥ 6.0.0)
- Composer

### Basic Installation

```bash
composer create-project ody/api-core your-project-name
cd your-project-name
```

## Configuration

Configuration files are stored in the `config` directory. The primary configuration files include:

- `app.php`: Application settings, service providers, and middleware
- `database.php`: Database connections configuration
- `logging.php`: Logging configuration and channels

Environment-specific configurations can be set in `.env` files. A sample `.env.example` file is provided that you can copy to `.env` and customize:

```bash
cp .env.example .env
```

## Project Structure

```
your-project/
├── app/                  # Application code
│   ├── Controllers/      # Controller classes
│   └── ...
├── config/               # Configuration files
├── public/               # Public directory (web server root)
│   └── index.php         # Application entry point
├── routes/               # Route definitions
│   ├── api.php           # API routes
│   └── web.php           # Web routes
├── src/                  # Framework core components
├── storage/              # Storage directory for logs, cache, etc.
├── tests/                # Test files
├── vendor/               # Composer dependencies
├── .env                  # Environment variables
├── .env.example          # Environment variables example
├── composer.json         # Composer package file
├── ody                   # CLI entry point
└── README.md             # Project documentation
```

## Routing

Routes are defined in the `routes` directory. The framework supports various HTTP methods and route patterns:

```php
// Basic route definition
Route::get('/hello', function (ServerRequestInterface $request, ResponseInterface $response) {
    return $response->json([
        'message' => 'Hello World'
    ]);
});

// Route with named controller
Route::post('/users', 'App\Controllers\UserController@store');

// Route with middleware
Route::get('/users/{id}', 'App\Controllers\UserController@show')
    ->middleware('auth:api');

// Route groups
Route::group(['prefix' => '/api/v1', 'middleware' => ['throttle:60,1']], function ($router) {
    $router->get('/status', function ($request, $response) {
        return $response->json([
            'status' => 'operational'
        ]);
    });
});
```

## Controllers

Controllers handle the application logic and are typically stored in the `app/Controllers` directory:

```php
<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class UserController
{
    private $logger;
    
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Get all users
        $users = [
            ['id' => 1, 'name' => 'John Doe'],
            ['id' => 2, 'name' => 'Jane Smith']
        ];
        
        return $response->withHeader('Content-Type', 'application/json')
                       ->withBody(json_encode($users));
    }
    
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        $id = $params['id'];
        
        // Get user by ID
        $user = ['id' => $id, 'name' => 'John Doe'];
        
        return $response->withHeader('Content-Type', 'application/json')
                       ->withBody(json_encode($user));
    }
}
```

## Middleware

Middleware provides a mechanism to filter HTTP requests/responses. The framework includes several built-in middleware 
classes and supports custom middleware:

```php
<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CustomMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Perform actions before the request is handled
        
        // Pass the request to the next middleware in the stack
        $response = $handler->handle($request);
        
        // Perform actions after the request is handled
        
        return $response;
    }
}
```

Register your middleware in `config/app.php`:

```php
'middleware' => [
    'global' => [
        // Global middleware applied to all routes
        App\Middleware\CustomMiddleware::class,
    ],
    'named' => [
        // Named middleware that can be referenced in routes
        'custom' => App\Middleware\CustomMiddleware::class,
    ],
],
```

## Dependency Injection

The framework includes a powerful IoC container for managing dependencies:

```php
<?php

// Binding an interface to a concrete implementation
$container->bind(UserRepositoryInterface::class, UserRepository::class);

// Registering a singleton
$container->singleton(UserService::class, function($container) {
    return new UserService(
        $container->make(UserRepositoryInterface::class)
    );
});

// Resolving dependencies
$userService = $container->make(UserService::class);
```

## Command Line Interface

The framework provides a CLI tool for various tasks. To use it, run the `ody` command from your project root:

```bash
# List available commands
php ody list

# Get environment information
php ody env

# Create a new command
php ody make:command MyCustomCommand
```

## Logging

Logging is configured in `config/logging.php` and provides various channels for logging:

```php
// Using the logger
$logger->info('User logged in', ['id' => $userId]);
$logger->error('Failed to process payment', ['order_id' => $orderId]);

// Using the logger helper function
logger()->info('Processing request');
```

## Working with Swoole

The framework integrates with Swoole to provide high-performance asynchronous processing:

```php
// Example of using Swoole-specific features
if (extension_loaded('swoole')) {
    // Create a coroutine
    \Swoole\Coroutine\run(function() {
        // Perform async operations
        $result = \Swoole\Coroutine\System::exec('long_running_command');
        
        // Log the result
        logger()->info('Command completed', $result);
    });
}
```

## Service Providers

Service providers are used to register services with the application. Custom service providers can be created in the 
`app/Providers` directory:

```php
<?php

namespace App\Providers;

use Ody\Foundation\Providers\ServiceProvider;

class CustomServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register bindings
        $this->singleton('custom.service', function() {
            return new CustomService();
        });
    }
    
    public function boot(): void
    {
        // Bootstrap services
    }
}
```

Register your service provider in `config/app.php`:

```php
'providers' => [
    // Framework providers
    Ody\Foundation\Providers\DatabaseServiceProvider::class,
    
    // Application providers
    App\Providers\CustomServiceProvider::class,
],
```

## Running the Application

### Development Server

For development, you can use the built-in PHP server:

```bash
php -S localhost:8000 -t public
```

### Production Deployment

For production, you can use Swoole HTTP server:

```bash
php ody serve --swoole --host=0.0.0.0 --port=9501
```

## Advanced Features

### Custom Commands

Create custom console commands by extending the base Command class:

```php
<?php

namespace App\Console\Commands;

use Ody\Foundation\Console\Command;

class CustomCommand extends Command
{
    protected $name = 'app:custom';
    
    protected $description = 'A custom command';
    
    protected function handle(): int
    {
        $this->info('Custom command executed successfully!');
        
        return self::SUCCESS;
    }
}
```

### Working with Databases

The framework provides a simple database abstraction layer:

```php
<?php

// Using PDO directly
$db = app('db');
$users = $db->query('SELECT * FROM users')->fetchAll();

// Or inject PDO into your classes
public function __construct(PDO $db)
{
    $this->db = $db;
}
```

## Resources

- [GitHub Repository](https://github.com/ody-dev/ody-core)
- [Issue Tracker](https://github.com/ody-dev/ody-core/issues)
- [PSR Standards](https://www.php-fig.org/psr/)
- [Swoole Documentation](https://www.swoole.co.uk/docs/)

## License

ODY Framework is open-source software licensed under the MIT license.