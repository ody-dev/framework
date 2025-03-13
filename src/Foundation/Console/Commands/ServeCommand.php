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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;

/**
 * ServeCommand
 *
 * Start a development server for the application
 */
class ServeCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'serve';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the development server';

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'The host address to serve on', '127.0.0.1')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'The port to serve on', 8000)
            ->addOption('workers', 'w', InputOption::VALUE_OPTIONAL, 'Number of Swoole workers', 4)
            ->addOption('max-requests', 'm', InputOption::VALUE_OPTIONAL, 'Maximum requests per worker', 1000)
            ->addOption('swoole', 's', InputOption::VALUE_NONE, 'Use Swoole server instead of PHP built-in server');
    }

    /**
     * Handle the command.
     *
     * @return int
     */
    protected function handle(): int
    {
        $host = $this->input->getOption('host');
        $port = $this->input->getOption('port');
        $useSwoole = $this->input->getOption('swoole');

        $this->info('Starting development server...');

        if ($useSwoole && extension_loaded('swoole')) {
            return $this->startSwooleServer($host, $port);
        }

        if ($useSwoole && !extension_loaded('swoole')) {
            $this->warning('Swoole extension is not loaded. Falling back to PHP built-in server.');
        }

        return $this->startPhpServer($host, $port);
    }

    /**
     * Start the Swoole server.
     *
     * @param string $host
     * @param int $port
     * @return int
     */
    protected function startSwooleServer(string $host, int $port): int
    {
        $workers = (int)$this->input->getOption('workers');
        $maxRequests = (int)$this->input->getOption('max-requests');

        $this->comment("Swoole server started on <info>http://{$host}:{$port}</info>");
        $this->comment("Press Ctrl+C to stop the server");

        $serverScript = base_path('bin/swoole.php');

        // Create server script if it doesn't exist
        if (!file_exists($serverScript)) {
            $this->createSwooleServerScript($serverScript);
        }

        $process = new Process([
            PHP_BINARY,
            $serverScript,
            $host,
            $port,
            $workers,
            $maxRequests
        ]);

        $process->setTty(true);
        $process->setTimeout(null);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        return self::SUCCESS;
    }

    /**
     * Start the PHP built-in server.
     *
     * @param string $host
     * @param int $port
     * @return int
     */
    protected function startPhpServer(string $host, int $port): int
    {
        $publicPath = base_path('public');
        $this->comment("Server started on <info>http://{$host}:{$port}</info>");
        $this->comment("Document root is <info>{$publicPath}</info>");
        $this->comment("Press Ctrl+C to stop the server");

        $process = new Process([
            PHP_BINARY,
            '-S',
            "{$host}:{$port}",
            '-t',
            $publicPath,
            base_path('server.php')
        ]);

        $process->setTty(true);
        $process->setTimeout(null);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        return self::SUCCESS;
    }

    /**
     * Create a Swoole server script if it doesn't exist
     *
     * @param string $path
     * @return void
     */
    protected function createSwooleServerScript(string $path): void
    {
        $script = <<<'EOD'
<?php
/**
 * Swoole HTTP Server for ODY Framework
 */

// Get command line arguments
$host = $argv[1] ?? '127.0.0.1';
$port = (int)($argv[2] ?? 8000);
$workers = (int)($argv[3] ?? 4);
$maxRequests = (int)($argv[4] ?? 1000);

// Define the application path
define('APP_BASE_PATH', dirname(__DIR__));

// Require the autoloader
require APP_BASE_PATH . '/vendor/autoload.php';

// Create the Swoole HTTP server
$server = new \Swoole\HTTP\Server($host, $port);

// Configure server
$server->set([
    'worker_num' => $workers,
    'max_request' => $maxRequests,
    'document_root' => APP_BASE_PATH . '/public',
    'enable_static_handler' => true,
]);

// Log server start
echo "ODY Swoole HTTP Server started on http://{$host}:{$port}" . PHP_EOL;
echo "Worker Count: {$workers}, Max Requests: {$maxRequests}" . PHP_EOL;

// Handle requests
$server->on('request', function ($request, $response) {
    try {
        // Set globals to simulate traditional PHP environment
        $_GET = $request->get ?? [];
        $_POST = $request->post ?? [];
        $_FILES = $request->files ?? [];
        $_COOKIE = $request->cookie ?? [];
        $_SERVER = $request->server ?? [];
        $_SERVER['REQUEST_METHOD'] = $request->server['request_method'] ?? 'GET';
        $_SERVER['REQUEST_URI'] = $request->server['request_uri'] ?? '/';
        $_SERVER['REMOTE_ADDR'] = $request->server['remote_addr'] ?? '127.0.0.1';
        
        // Store response in context for later access
        $context = \Swoole\Coroutine::getContext();
        $context['swoole_response'] = $response;
        
        // Initialize the application
        $app = \Ody\Foundation\Bootstrap::init();
        
        // Run the application
        $app->run();
    } catch (\Throwable $e) {
        // Handle uncaught exceptions
        $response->status(500);
        $response->header('Content-Type', 'application/json');
        $response->end(json_encode([
            'error' => 'Internal Server Error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]));
    }
});

// Start the server
$server->start();
EOD;

        // Create directory if it doesn't exist
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Write script to file
        file_put_contents($path, $script);
        chmod($path, 0755);

        $this->info("Created Swoole server script at <info>{$path}</info>");
    }
}