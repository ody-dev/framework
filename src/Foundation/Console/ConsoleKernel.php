<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Foundation\Console;

use Ody\Container\Container;
use Ody\Foundation\Application;
use Ody\Foundation\Providers\EnvServiceProvider;
use Ody\Foundation\Support\Config;
use Ody\Foundation\Providers\ServiceProviderManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * ConsoleKernel
 *
 * Central class for managing console commands and handling command-line requests.
 */
class ConsoleKernel
{
    /**
     * @var Container
     */
    protected Container $container;

    /**
     * @var Application|null
     */
    protected ?Application $app = null;

    /**
     * @var ConsoleApplication
     */
    protected ConsoleApplication $console;

    /**
     * @var CommandRegistry
     */
    protected CommandRegistry $commandRegistry;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var array
     */
    protected array $bootstrappers = [
        \Ody\Foundation\Providers\EnvServiceProvider::class,
        \Ody\Foundation\Providers\ConfigServiceProvider::class,
        \Ody\Foundation\Providers\LoggingServiceProvider::class,
        \Ody\Foundation\Providers\DatabaseServiceProvider::class,
    ];


    /**
     * ConsoleKernel constructor
     *
     * @param Container|null $container
     */
    public function __construct(?Container $container = null)
    {
        // Initialize the container
        $this->container = $container ?: new Container();
        Container::setInstance($this->container);

        // Register basic logger for initial operations
        if (!$this->container->has(LoggerInterface::class)) {
            $this->container->instance(LoggerInterface::class, new NullLogger());
        }
        $this->logger = $this->container->make(LoggerInterface::class);

        // Create and register command registry
        $this->commandRegistry = new CommandRegistry($this->container, $this->logger);
        $this->container->instance(CommandRegistry::class, $this->commandRegistry);

        // Create Symfony Console application
        $this->console = new ConsoleApplication('ODY Console', $this->getFrameworkVersion());
        $this->container->instance(ConsoleApplication::class, $this->console);

        // Register self in container
        $this->container->instance(self::class, $this);
    }

    /**
     * Bootstrap the console kernel
     *
     * @return self
     */
    public function bootstrap(): self
    {
        try {

            // Ensure the console service provider is registered
            $this->registerServiceProviders();

            // Register all commands with Symfony Console
            $this->registerCommandsWithConsole();

        } catch (\Throwable $e) {
            $this->logger->error('Error bootstrapping console kernel: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }

        return $this;
    }

    /**
     * Handle an incoming console command
     *
     * @param InputInterface|null $input
     * @param OutputInterface|null $output
     * @return int
     */
    public function handle(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        try {
            // Get the application instance
            $app = $this->getApplication();

            // Mark it as running in console mode
            $app->setRunningInConsole(true);

            // Run the Symfony Console application
            return $this->console->run($input, $output);
        } catch (\Throwable $e) {
            $this->logger->error('Console error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            // Show the error in the console
            if ($output) {
                $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            } else {
                echo "Error: " . $e->getMessage() . PHP_EOL;
            }

            return 1;
        }
    }

    /**
     * Register the required service providers
     *
     * @return void
     */
    protected function registerServiceProviders(): void
    {
        // If service provider manager already exists, use it
        if ($this->container->has(ServiceProviderManager::class)) {
            $providerManager = $this->container->make(ServiceProviderManager::class);
        } else {
            // Otherwise create a new one
            $config = $this->container->has(Config::class) ? $this->container->make(Config::class) : null;
            $providerManager = new ServiceProviderManager($this->container, $config, $this->logger);
            $this->container->instance(ServiceProviderManager::class, $providerManager);
        }

        // Register console service providers
        foreach ($this->bootstrappers as $provider) {
            if (!$providerManager->isRegistered($provider) && class_exists($provider)) {
                $providerManager->register($provider);
            }
        }

        // Register the console service provider explicitly
        if (class_exists(\Ody\Foundation\Providers\ConsoleServiceProvider::class)) {
            $providerManager->register(\Ody\Foundation\Providers\ConsoleServiceProvider::class);
        }

        // Boot all registered providers
        $providerManager->boot();
    }

    /**
     * Get or create the application instance
     *
     * @return Application
     */
    public function getApplication(): Application
    {
        if ($this->app === null) {
            if ($this->container->has(Application::class)) {
                $this->app = $this->container->make(Application::class);
            } else {
                // Create service provider manager if needed
                if (!$this->container->has(ServiceProviderManager::class)) {
                    $config = $this->container->has(Config::class) ? $this->container->make(Config::class) : null;
                    $providerManager = new ServiceProviderManager($this->container, $config, $this->logger);
                    $this->container->instance(ServiceProviderManager::class, $providerManager);
                } else {
                    $providerManager = $this->container->make(ServiceProviderManager::class);
                }

                // Create application
                $this->app = new Application($this->container, $providerManager);
                $this->container->instance(Application::class, $this->app);
            }

            // Bootstrap the application
            if (method_exists($this->app, 'bootstrap')) {
                $this->app->bootstrap();
            }

            // Mark as running in console
            $this->app->setRunningInConsole(true);
        }

        return $this->app;
    }


    /**
     * Register app commands from app/Console/Commands directory
     *
     * @return void
     */
    protected function registerAppCommands(): void
    {
        $commandsPath = $this->getApplication()->getContainer()->has(Config::class)
            ? $this->getApplication()->getContainer()->make(Config::class)->get('app.commands_path', 'app/Console/Commands')
            : 'app/Console/Commands';

        $this->commandRegistry->addFromDirectory($commandsPath);
    }

    /**
     * Load commands from configuration
     *
     * @return void
     */
    protected function loadCommandsFromConfig(): void
    {
        if ($this->container->has(Config::class)) {
            $config = $this->container->make(Config::class);
            $commands = $config->get('app.commands', []);

            foreach ($commands as $commandClass) {
                if (class_exists($commandClass)) {
                    $this->commandRegistry->add($commandClass);
                }
            }
        }
    }

    /**
     * Discover commands in application directories
     *
     * @return void
     */
    protected function discoverCommands(): void
    {
        if ($this->container->has(Config::class)) {
            $config = $this->container->make(Config::class);
            $directories = $config->get('app.command_directories', []);

            foreach ($directories as $directory) {
                $this->commandRegistry->addFromDirectory($directory);
            }
        }
    }

    /**
     * Register all commands with Symfony Console
     *
     * @return void
     */
    /**
     * Register all commands with Symfony Console
     *
     * @return void
     */
    protected function registerCommandsWithConsole(): void
    {
        // After ConsoleServiceProvider is registered, get the registry
        if ($this->container->has(CommandRegistry::class)) {
            // Get the registry from the container to ensure we have the latest version
            $registry = $this->container->make(CommandRegistry::class);

            // Register commands with Symfony Console
            foreach ($registry->getCommands() as $command) {
                if (!$this->console->has($command->getName())) {
                    $this->console->add($command);
                    $this->logger->debug("Registered command with console: " . $command->getName());
                }
            }
        } else {
            $this->logger->warning("CommandRegistry not found in container");
        }
    }

    /**
     * Get the framework version
     *
     * @return string
     */
    protected function getFrameworkVersion(): string
    {
        if ($this->container->has(Config::class)) {
            $config = $this->container->make(Config::class);
            $version = $config->get('app.version');

            if ($version) {
                return $version;
            }
        }

        // Fallback version
        return '1.0.0';
    }

    /**
     * Get the container instance
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get the command registry
     *
     * @return CommandRegistry
     */
    public function getCommandRegistry(): CommandRegistry
    {
        return $this->commandRegistry;
    }

    /**
     * Get the Symfony Console application
     *
     * @return ConsoleApplication
     */
    public function getConsole(): ConsoleApplication
    {
        return $this->console;
    }
}