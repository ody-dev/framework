<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Foundation\Console\Commands;

use Ody\Foundation\Console\Command;
use Ody\Foundation\Console\CommandRegistry;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * ListCommand
 *
 * A custom implementation of the list command for ODY Framework
 */
class ListCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all available commands';

    /**
     * Command categories
     *
     * @var array
     */
    protected array $categories = [
        'app'       => 'Application Commands',
        'make'      => 'Generator Commands',
        'db'        => 'Database Commands',
        'cache'     => 'Cache Commands',
        'queue'     => 'Queue Commands',
        'schedule'  => 'Scheduler Commands',
        'system'    => 'System Commands',
    ];

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addArgument('namespace', InputArgument::OPTIONAL, 'The namespace name')
            ->addOption('raw', null, InputOption::VALUE_NONE, 'To output raw command list')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'The output format (txt, xml, json, or md)', 'txt')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Show all commands, including hidden ones');
    }

    /**
     * Handle the command execution.
     *
     * @return int
     */
    protected function handle(): int
    {
        // If raw output requested, delegate to the descriptor
        if ($this->input->getOption('raw')) {
            $this->displayRawList();
            return self::SUCCESS;
        }

        // Display ODY Framework ASCII logo
        $this->displayLogo();

        // Get all available commands
        $commands = $this->getApplication()->all();

        // Skip hidden commands unless --all is specified
        if (!$this->input->getOption('all')) {
            $commands = array_filter($commands, function($command) {
                return !$command->isHidden();
            });
        }

        // Organize commands by category
        $categorizedCommands = $this->categorizeCommands($commands);

        // Display commands by category
        $this->displayCategorizedCommands($categorizedCommands);

        return self::SUCCESS;
    }

    /**
     * Display the raw command list.
     *
     * @return void
     */
    protected function displayRawList(): void
    {
        $helper = new DescriptorHelper();
        $helper->describe(
            $this->output,
            $this->getApplication(),
            [
                'format' => $this->input->getOption('format'),
                'raw_text' => true,
                'namespace' => $this->input->getArgument('namespace'),
            ]
        );
    }

    /**
     * Display the ODY Framework ASCII logo.
     *
     * @return void
     */
    protected function displayLogo(): void
    {
        $this->output->writeln([
            '<fg=blue>',
            '  ___  ______  __  __   ',
            ' / _ \|  _  \ \ \/ /   ',
            '| | | | | | |  \  /    ',
            '| |_| | |/ /   /  \    ',
            ' \___/|___/   /_/\_\   Framework ',
            '</fg=blue>',
            '<fg=green>v' . $this->getFrameworkVersion() . '</fg=green>',
            '',
        ]);
    }

    /**
     * Get the framework version.
     *
     * @return string
     */
    protected function getFrameworkVersion(): string
    {
        if ($this->container->has('config')) {
            $version = $this->container->make('config')->get('app.version');
            if ($version) {
                return $version;
            }
        }

        return '1.0.0';
    }

    /**
     * Categorize commands by their namespace.
     *
     * @param array $commands
     * @return array
     */
    protected function categorizeCommands(array $commands): array
    {
        $categorized = [];

        // Initialize categories with empty arrays
        foreach ($this->categories as $key => $name) {
            $categorized[$key] = [
                'name' => $name,
                'commands' => [],
            ];
        }

        // Add "other" category for uncategorized commands
        $categorized['other'] = [
            'name' => 'Other Commands',
            'commands' => [],
        ];

        // Categorize commands
        foreach ($commands as $name => $command) {
            $category = $this->getCategoryForCommand($name);
            $categorized[$category]['commands'][$name] = $command;
        }

        // Remove empty categories
        foreach ($categorized as $key => $category) {
            if (empty($category['commands'])) {
                unset($categorized[$key]);
            }
        }

        return $categorized;
    }

    /**
     * Determine the category for a command.
     *
     * @param string $name
     * @return string
     */
    protected function getCategoryForCommand(string $name): string
    {
        if (strpos($name, 'make:') === 0) {
            return 'make';
        }

        if (in_array($name, ['migrate', 'migrate:status', 'migrate:rollback', 'db:seed']) || strpos($name, 'db:') === 0) {
            return 'db';
        }

        if (strpos($name, 'cache:') === 0) {
            return 'cache';
        }

        if (strpos($name, 'queue:') === 0) {
            return 'queue';
        }

        if (strpos($name, 'schedule:') === 0) {
            return 'schedule';
        }

        if (in_array($name, ['serve', 'env', 'list', 'help'])) {
            return 'system';
        }

        return 'other';
    }

    /**
     * Display commands organized by category.
     *
     * @param array $categories
     * @return void
     */
    protected function displayCategorizedCommands(array $categories): void
    {
        foreach ($categories as $category) {
            if (empty($category['commands'])) {
                continue;
            }

            $this->output->writeln('<comment>' . $category['name'] . ':</comment>');

            $width = $this->getColumnWidth($category['commands']);

            foreach ($category['commands'] as $name => $command) {
                $this->output->writeln(sprintf('  <info>%-' . $width . 's</info> %s', $name, $command->getDescription()));
            }

            $this->output->writeln('');
        }

        $this->output->writeln('<comment>Usage:</comment>');
        $this->output->writeln('  command [options] [arguments]');
        $this->output->writeln('');
        $this->output->writeln('<comment>Options:</comment>');
        $this->output->writeln('  <info>-h, --help</info>       Display help for the given command');
        $this->output->writeln('  <info>-q, --quiet</info>      Do not output any message');
        $this->output->writeln('  <info>-v, --verbose</info>    Increase the verbosity of messages');
        $this->output->writeln('  <info>--ansi</info>           Force ANSI output');
        $this->output->writeln('  <info>--no-ansi</info>        Disable ANSI output');
        $this->output->writeln('  <info>--version</info>        Display this application version');
        $this->output->writeln('');
    }

    /**
     * Calculate the width for the command name column.
     *
     * @param array $commands
     * @return int
     */
    protected function getColumnWidth(array $commands): int
    {
        $widths = [];

        foreach ($commands as $name => $command) {
            $widths[] = strlen($name);
        }

        return $widths ? max($widths) + 2 : 20;
    }
}