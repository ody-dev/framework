<?php

namespace Ody\Core\Foundation\Middleware\Resolvers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Interface for middleware resolvers
 */
interface MiddlewareResolverInterface
{
    /**
     * Check if this resolver can handle the given middleware name
     *
     * @param string $name
     * @return bool
     */
    public function supports(string $name): bool;

    /**
     * Resolve middleware to a callable
     *
     * @param string $name
     * @param array $options
     * @return callable
     */
    public function resolve(string $name, array $options = []): callable;
}