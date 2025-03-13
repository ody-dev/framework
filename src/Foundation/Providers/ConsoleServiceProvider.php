<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Foundation\Providers;

use Ody\Container\Container;
use Ody\Foundation\Console\CommandRegistry;
use Ody\Foundation\Console\ConsoleKernel;
use Ody\Foundation\Support\Config;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application as ConsoleApplication;

/**
 * Console Service Provider
 *
 * Registers console-related services in the container
 */
class ConsoleServiceProvider extends ServiceProvider
{
    /**
     * The core console commands provided by the framework
     *
     * @var array
     */
    protected array $commands = [
        \Ody\Foundation\Console\Commands\ServeCommand::class,
        \Ody\Foundation\Console\Commands\EnvironmentCommand::class,
        \Ody\Foundation\Console\Commands\TestCommand::class,
        \Ody\Foundation\Console\Commands\MakeCommandCommand::class,
    ];

    /**
     * Register console-related services
     *
     * @return void
     */
    public function register(): void
    {
        // Register CommandRegistry as a singleton
        $this->singleton(CommandRegistry::class, function (Container $container) {
            return new CommandRegistry(
                $container,
                $container->make(LoggerInterface::class)
            );
        });

        // Register ConsoleKernel
        $this->singleton(ConsoleKernel::class, function (Container $container) {
            return new ConsoleKernel($container);
        });

        // Register Symfony Console application
        $this->singleton(ConsoleApplication::class, function (Container $container) {
            return $container->make(ConsoleKernel::class)->getConsole();
        });
    }

    /**
     * Bootstrap console services
     *
     * @return void
     */
    public function boot(): void
    {
        // Skip if not running in console (prevents unnecessary scanning in HTTP requests)
        if ($this->container->has('app')) {
            $app = $this->container->make('app');
            if (method_exists($app, 'isConsole') && !$app->isConsole()) {
                return;
            }
        }

        // Get or create a command registry
        if (!$this->container->has(CommandRegistry::class)) {
            $this->register(); // Ensure services are registered
        }

        $registry = $this->make(CommandRegistry::class);

        // Register built-in framework commands
        $this->registerFrameworkCommands($registry);

        // Register application commands from config
        $this->registerApplicationCommands($registry);

        // Discover commands from specified directories
        // TODO: not implemented
        $this->discoverCommands($registry);
    }

    /**
     * Register framework built-in commands
     *
     * @param CommandRegistry $registry
     * @return void
     */
    protected function registerFrameworkCommands(CommandRegistry $registry): void
    {
        foreach ($this->commands as $commandClass) {
            // Simply pass the class name to the registry
            $registry->add($commandClass);
        }
    }

    /**
     * Register application commands from config
     *
     * @param CommandRegistry $registry
     * @return void
     */
    protected function registerApplicationCommands(CommandRegistry $registry): void
    {
        $config = $this->container->make(Config::class);
        $commands = $config->get('app.commands', []);

        foreach ($commands as $command) {
            if (class_exists($command)) {
                $registry->add($command);
            }
        }
    }

    /**
     * Discover commands from specific directories
     *
     * @param CommandRegistry $registry
     * @return void
     */
    protected function discoverCommands(CommandRegistry $registry): void
    {
        $config = $this->container->make(Config::class);
        $directories = $config->get('app.command_directories', [base_path('app/Console/Commands')]);

        foreach ($directories as $directory) {
            $registry->addFromDirectory($directory);
        }
    }

    /**
     * Get the services provided by the provider
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            CommandRegistry::class,
            ConsoleKernel::class,
            ConsoleApplication::class,
        ];
    }
}