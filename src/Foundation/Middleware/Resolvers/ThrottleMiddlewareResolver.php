<?php

namespace Ody\Core\Foundation\Middleware\Resolvers;

use Ody\Core\Foundation\Http\Request;
use Ody\Core\Foundation\Middleware\ThrottleMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Resolver for throttle middleware
 */
class ThrottleMiddlewareResolver implements MiddlewareResolverInterface
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
        return $name === 'throttle' || strpos($name, 'throttle:') === 0;
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
        // Extract default rate if specified in the name
        $defaultRate = $name === 'throttle' ? '60,1' : substr($name, 9);

        return function (ServerRequestInterface $request, callable $next) use ($defaultRate) {
            // Get rate from request parameters or use default
            $rate = $request instanceof Request && isset($request->middlewareParams['throttle'])
                ? $request->middlewareParams['throttle']
                : $defaultRate;

            list($maxAttempts, $minutes) = explode(',', $rate);
            $throttleMiddleware = new ThrottleMiddleware((int)$maxAttempts, (int)$minutes);
            $handler = $this->createNextHandler($next);
            return $throttleMiddleware->process($request, $handler);
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