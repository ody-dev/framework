<?php

namespace Ody\Core\Foundation\Middleware;

use Ody\Core\Foundation\Http\Response;
use Ody\Core\Foundation\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Authentication Middleware (PSR-15)
 */
class AuthMiddleware implements MiddlewareInterface
{
    /**
     * @var string The guard type (e.g., 'api', 'jwt', 'web')
     */
    private string $guard;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * AuthMiddleware constructor
     *
     * @param string $guard
     * @param Logger $logger
     */
    public function __construct(string $guard = 'web', Logger $logger = null)
    {
        $this->guard = $guard;
        $this->logger = $logger ?? new Logger();
    }

    /**
     * Process an incoming server request
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authHeader = $request->getHeaderLine('Authorization');

        // Based on the guard type, implement different authentication strategies
        if ($this->guard === 'api') {
            // API token authentication
            if (strpos($authHeader, 'Bearer ') === 0) {
                $token = substr($authHeader, 7);
                if ($token === 'valid-api-token') {
                    return $handler->handle($request);
                }
            }
        } elseif ($this->guard === 'jwt') {
            // JWT authentication
            if (strpos($authHeader, 'Bearer ') === 0) {
                $token = substr($authHeader, 7);
                // Verify JWT token (simplified)
                if (strpos($token, 'jwt-') === 0) {
                    return $handler->handle($request);
                }
            }
        } else {
            // Regular authentication (web)
            if (strpos($authHeader, 'Bearer ') === 0) {
                $token = substr($authHeader, 7);
                if ($token === 'valid-token') {
                    return $handler->handle($request);
                }
            }
        }

        // Authentication failed
        $this->logger->warning('Unauthorized access attempt', [
            'guard' => $this->guard,
            'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown'
        ]);

        $response = new Response();
        return $response
            ->withStatus(401)
            ->json()
            ->withJson([
                'error' => 'Unauthorized',
                'guard' => $this->guard
            ]);
    }
}
