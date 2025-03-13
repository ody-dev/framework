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
        // Internal commands
        \Ody\Foundation\Console\Commands\ListCommand::class,
        \Ody\Foundation\Console\Commands\ServeCommand::class,
        \Ody\Foundation\Console\Commands\EnvironmentCommand::class,
        \Ody\Foundation\Console\Commands\TestCommand::class,

        // Development commands
        \Ody\Foundation\Console\Commands\MakeCommandCommand::class,

        // The following commands are commented out until they are implemented
        // \Ody\Foundation\Console\Commands\MakeControllerCommand::class,
        // \Ody\Foundation\Console\Commands\MakeProviderCommand::class,

        // Database commands
        // \Ody\Foundation\Console\Commands\MigrateMakeCommand::class,
        // \Ody\Foundation\Console\Commands\MigrateCommand::class,
        // \Ody\Foundation\Console\Commands\MigrateRollbackCommand::class,
        // \Ody\Foundation\Console\Commands\MigrateStatusCommand::class,
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
        $this->discoverCommands($registry);

        // If Console instance is available, register commands directly
        if ($this->container->has(ConsoleApplication::class)) {
            $console = $this->container->make(ConsoleApplication::class);
            foreach ($registry->getCommands() as $command) {
                $console->add($command);
            }
        }
    }

    /**
     * Register framework built-in commands
     *
     * @param CommandRegistry $registry
     * @return void
     */
    protected function registerFrameworkCommands(CommandRegistry $registry): void
    {
        foreach ($this->commands as $command) {
            if (class_exists($command)) {
                $registry->add($command);
            }
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
        if (!$this->container->has(Config::class)) {
            return;
        }

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
        if (!$this->container->has(Config::class)) {
            return;
        }

        $config = $this->container->make(Config::class);
        $directories = $config->get('app.command_directories', [base_path('app/Console/Commands')]);

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            foreach (glob($directory . '/*.php') as $file) {
                $className = $this->getClassNameFromFile($file);
                if ($className && class_exists($className)) {
                    $registry->add($className);
                }
            }
        }
    }

    /**
     * Extract class name from file
     *
     * @param string $file
     * @return string|null
     */
    protected function getClassNameFromFile(string $file): ?string
    {
        $content = file_get_contents($file);
        $namespace = $this->extractNamespace($content);
        $class = $this->extractClassName($content);

        if ($namespace && $class) {
            return $namespace . '\\' . $class;
        }

        return null;
    }

    /**
     * Extract namespace from file content
     *
     * @param string $content
     * @return string|null
     */
    protected function extractNamespace(string $content): ?string
    {
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Extract class name from file content
     *
     * @param string $content
     * @return string|null
     */
    protected function extractClassName(string $content): ?string
    {
        if (preg_match('/class\s+([a-zA-Z0-9_]+)/', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
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