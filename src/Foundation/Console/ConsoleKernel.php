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
     * ConsoleKernel constructor
     *
     * @param Container $container
     * @param ConsoleApplication|null $console
     * @param CommandRegistry|null $commandRegistry
     */
    public function __construct(
        Container $container,
        ?ConsoleApplication $console = null,
        ?CommandRegistry $commandRegistry = null
    ) {
        // Initialize the container
        $this->container = $container;
        Container::setInstance($this->container);

        // Use provided console application or create a new one
        $this->console = $console ?? $this->container->make(ConsoleApplication::class);

        // Register self in container
        $this->container->instance(self::class, $this);
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
     * Get or create the application instance
     *
     * @return Application
     */
    public function getApplication(): Application
    {
        if ($this->app === null) {
            // Create application
            $providerManager = $this->container->make(ServiceProviderManager::class);
            $this->app = new Application($this->container, $providerManager);
            $this->container->instance(Application::class, $this->app);

            // Bootstrap the application
            if (method_exists($this->app, 'bootstrap')) {
                $this->app->bootstrap();
            }
        }

        return $this->app;
    }
}