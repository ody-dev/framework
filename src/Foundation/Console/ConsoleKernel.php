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
use Ody\Foundation\Bootstrap;
use Ody\Foundation\Application;
use Ody\Foundation\Providers\ConsoleServiceProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * ConsoleKernel
 *
 * Handles command-line interface operations using Symfony Console
 */
class ConsoleKernel
{
    /**
     * @var Container
     */
    protected Container $container;

    /**
     * @var Application
     */
    protected Application $app;

    /**
     * @var ConsoleApplication
     */
    protected ConsoleApplication $console;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var array
     */
    protected array $bootstrappers = [
        \Ody\Foundation\Providers\ConfigServiceProvider::class,
        \Ody\Foundation\Providers\LoggingServiceProvider::class,
        \Ody\Foundation\Providers\DatabaseServiceProvider::class,
        \Ody\Foundation\Providers\ConsoleServiceProvider::class,
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

        // Make sure basic dependencies are available
        if (!$this->container->has(LoggerInterface::class)) {
            $this->container->instance(LoggerInterface::class, new NullLogger());
        }

        try {
            // Get the application from the container
            if ($this->container->has(Application::class)) {
                $this->app = $this->container->make(Application::class);
            } else {
                // If not available, use the bootstrapper to create it
                ConsoleBootstrapper::bootstrap($this->container);
                $this->app = $this->container->make(Application::class);
            }

            // Mark the application as running in console mode
            $this->app->setRunningInConsole(true);

            // Register self in container
            $this->container->instance(self::class, $this);
        } catch (\Throwable $e) {
            // If something went wrong, provide more detailed error information
            echo "Error initializing application: " . $e->getMessage() . PHP_EOL;
            echo "File: " . $e->getFile() . " Line: " . $e->getLine() . PHP_EOL;

            // Exit with error status
            exit(1);
        }

        // Create a Symfony Console application
        $this->console = new ConsoleApplication('ODY Console', $this->getFrameworkVersion());

        // Get logger from container (should be registered by now)
        $this->logger = $this->container->make(LoggerInterface::class);
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
            // Mark the application as running in console mode
            $this->app->setRunningInConsole(true);

            // Load console commands
            $this->loadCommands();

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
            }

            return 1;
        }
    }

    /**
     * Load console commands from service providers and config
     *
     * @return void
     */
    protected function loadCommands(): void
    {
        // Make sure the Console Service Provider is registered
        if (!$this->container->has(ConsoleServiceProvider::class)) {
            $this->app->getProviderManager()->register(ConsoleServiceProvider::class);
        }

        // Get the command registry from the container
        $registry = $this->container->make(CommandRegistry::class);

        // Register our default commands if registry is empty
        if (count($registry->getCommands()) === 0) {
            $this->registerDefaultCommands($registry);
        }

        // Add commands to the Symfony Console application
        foreach ($registry->getCommands() as $command) {
            $this->console->add($command);
        }
    }

    /**
     * Register default commands if needed
     *
     * @param CommandRegistry $registry
     * @return void
     */
    protected function registerDefaultCommands(CommandRegistry $registry): void
    {
        // Core commands that should always be available
        $commands = [
            // Register the built-in commands directly to ensure they're always available
            new Commands\ListCommand(),
            new Commands\ServeCommand(),
            new Commands\EnvironmentCommand(),
            new Commands\MakeCommandCommand(),
        ];

        // Register each command
        foreach ($commands as $command) {
            // Register with the registry
            $registry->add($command);

            // Also add directly to console application for immediate use
            $this->console->add($command);
        }

        // Log registration
        $this->logger->info('Registered default console commands');
    }

    /**
     * Get the application container
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get the application instance
     *
     * @return Application
     */
    public function getApplication(): Application
    {
        return $this->app;
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

    /**
     * Get the framework version
     *
     * @return string
     */
    protected function getFrameworkVersion(): string
    {
        // Try to get version from container if available
        if ($this->container->has('config')) {
            $version = $this->container->make('config')->get('app.version');
            if ($version) {
                return $version;
            }
        }

        // Fallback to a default version
        return '1.0.0';
    }
}