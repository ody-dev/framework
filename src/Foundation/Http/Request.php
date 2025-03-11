<?php
namespace Ody\Core\Foundation\Http;

/**
 * Standard HTTP Request wrapper
 */
class Request
{
    /**
     * @var array Request server variables
     */
    public $server = [];

    /**
     * @var array Request headers
     */
    public $header = [];

    /**
     * @var array Request GET parameters
     */
    public $get = [];

    /**
     * @var array Request POST parameters
     */
    public $post = [];

    /**
     * @var array Request files
     */
    public $files = [];

    /**
     * @var array Request cookies
     */
    public $cookie = [];

    /**
     * @var array Parsed request body
     */
    public $parsedBody = [];

    /**
     * @var array Middleware parameters
     */
    public $middlewareParams = [];

    /**
     * @var array Route parameters
     */
    public $routeParams = [];

    /**
     * @var string|null Raw request body
     */
    private $rawContent = null;

    /**
     * Create request from globals
     *
     * @return self
     */
    public static function createFromGlobals(): self
    {
        $request = new self();

        // Server variables
        $request->server = $_SERVER;

        // Headers
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $request->header[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $name = str_replace('_', '-', strtolower($key));
                $request->header[$name] = $value;
            }
        }

        // GET parameters
        $request->get = $_GET;

        // POST parameters
        $request->post = $_POST;

        // Files
        $request->files = $_FILES;

        // Cookies
        $request->cookie = $_COOKIE;

        return $request;
    }

    /**
     * Get request method
     *
     * @return string
     */
    public function getMethod(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Get request URI
     *
     * @return string
     */
    public function getUri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    /**
     * Get request path (without query string)
     *
     * @return string
     */
    public function getPath(): string
    {
        $uri = $this->getUri();
        $position = strpos($uri, '?');

        if ($position !== false) {
            return substr($uri, 0, $position);
        }

        return $uri;
    }

    /**
     * Get raw request body
     *
     * @return string
     */
    public function rawContent(): string
    {
        if ($this->rawContent === null) {
            $this->rawContent = file_get_contents('php://input');
        }

        return $this->rawContent;
    }

    /**
     * Get request body as JSON
     *
     * @param bool $assoc Return as associative array
     * @return mixed
     */
    public function json(bool $assoc = true)
    {
        $content = $this->rawContent();
        return json_decode($content, $assoc);
    }

    /**
     * Get input parameter (GET, POST, or JSON body)
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function input(string $key, $default = null)
    {
        if (isset($this->parsedBody[$key])) {
            return $this->parsedBody[$key];
        }

        if (isset($this->post[$key])) {
            return $this->post[$key];
        }

        if (isset($this->get[$key])) {
            return $this->get[$key];
        }

        return $default;
    }

    /**
     * Get all input parameters
     *
     * @return array
     */
    public function all(): array
    {
        return array_merge($this->get, $this->post, $this->parsedBody);
    }

    /**
     * Get a header value
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getHeader(string $name, $default = null)
    {
        $name = strtolower($name);
        return $this->header[$name] ?? $default;
    }
}