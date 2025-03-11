<?php

namespace Ody\Core\Foundation\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ody\Core\Foundation\Http\Response;

/**
 * PSR-15 Request Handler implementation
 */
class RequestHandler implements RequestHandlerInterface
{
    /**
     * @var callable
     */
    private $handler;

    /**
     * @var array
     */
    private array $middlewareStack = [];

    /**
     * @var int Current middleware position
     */
    private int $currentPosition = 0;

    /**
     * RequestHandler constructor
     *
     * @param callable $handler
     * @param array $middleware
     */
    public function __construct(callable $handler, array $middleware = [])
    {
        $this->handler = $handler;
        $this->middlewareStack = $middleware;
    }

    /**
     * Handle the request
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // If there are no more middleware, execute the handler
        if ($this->currentPosition === count($this->middlewareStack)) {
            return call_user_func($this->handler, $request);
        }

        // Get the next middleware
        $middleware = $this->middlewareStack[$this->currentPosition];
        $this->currentPosition++;

        // Process the request through the middleware
        return $middleware->process($request, $this);
    }
}