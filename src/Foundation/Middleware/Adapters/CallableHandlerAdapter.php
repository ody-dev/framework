<?php

namespace Ody\Core\Foundation\Middleware\Adapters;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Adapter to wrap a callable as a PSR-15 compatible request handler
 *
 * This adapter allows you to use a simple callable function as a PSR-15
 * RequestHandlerInterface, eliminating the need to create anonymous classes
 * or duplicate adapter code throughout the middleware system.
 */
class CallableHandlerAdapter implements RequestHandlerInterface
{
    /**
     * The callable to be wrapped
     *
     * @var callable
     */
    private $callable;

    /**
     * Additional arguments to pass to the callable
     *
     * @var array
     */
    private $arguments;

    /**
     * Create a new CallableHandlerAdapter
     *
     * @param callable $callable The function to wrap as a handler
     * @param array $arguments Additional arguments to pass to the callable after the request
     */
    public function __construct(callable $callable, array $arguments = [])
    {
        $this->callable = $callable;
        $this->arguments = $arguments;
    }

    /**
     * Handle the request and produce a response
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Call the wrapped callable with the request and any additional arguments
        return call_user_func($this->callable, $request, ...$this->arguments);
    }

    /**
     * Create a handler adapter from a callable
     *
     * This static factory method provides a convenient way to create adapter instances.
     *
     * @param callable $callable The function to wrap as a handler
     * @param array $arguments Additional arguments to pass to the callable
     * @return self
     */
    public static function create(callable $callable, array $arguments = []): self
    {
        return new self($callable, $arguments);
    }
}