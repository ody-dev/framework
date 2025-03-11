<?php

namespace Ody\Core\Foundation\Middleware\Resolvers;

use Ody\Core\Foundation\Http\Request;
use Ody\Core\Foundation\Middleware\AuthMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Resolver for auth middleware
 */
class AuthMiddlewareResolver implements MiddlewareResolverInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Check if this resolver can handle the given middleware name
     *
     * @param string $name
     * @return bool
     */
    public function supports(string $name): bool
    {
        return $name === 'auth' || strpos($name, 'auth:') === 0;
    }

    /**
     * Resolve middleware to a callable
     *
     * @param string $name
     * @param array $options
     * @return callable
     */
    public function resolve(string $name, array $options = []): callable
    {
        // Extract guard from name (auth:api, auth:jwt, etc.)
        $guard = $name === 'auth' ? 'web' : substr($name, 5);

        return function (ServerRequestInterface $request, callable $next) use ($guard) {
            // Check if we have a parameter in the request that should override the guard
            $requestGuard = null;
            if (
                $request instanceof Request &&
                isset($request->middlewareParams['auth']))
            {
                $requestGuard = $request->middlewareParams['auth'];
            }

            // Use request guard if available, otherwise use the one from the middleware name
            $finalGuard = $requestGuard ?? $guard;

            $authMiddleware = new AuthMiddleware($finalGuard, $this->logger);
            $handler = $this->createNextHandler($next);
            return $authMiddleware->process($request, $handler);
        };
    }

    /**
     * Create a next handler
     *
     * @param callable $next
     * @return RequestHandlerInterface
     */
    protected function createNextHandler(callable $next): RequestHandlerInterface
    {
        return new class($next) implements RequestHandlerInterface {
            private $next;

            public function __construct(callable $next) {
                $this->next = $next;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface {
                return call_user_func($this->next, $request);
            }
        };
    }
}