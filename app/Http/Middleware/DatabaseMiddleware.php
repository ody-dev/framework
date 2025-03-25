<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace App\Http\Middleware;

use Closure;
use Ody\DB\Doctrine\Facades\ORM;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Middleware for handling database connections in HTTP requests
 */
class DatabaseMiddleware implements MiddlewareInterface
{
    /**
     * Handle the incoming request and ensure database connections are properly managed.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Initialize the EntityManager for this request
        $em = ORM::entityManager();

        // Handle the request
        try {
            $response = $handler->handle($request);

            // Ensure all active transactions are committed
            if ($em->getConnection()->isTransactionActive()) {
                logger()->warning("Transaction active at end of request - committing automatically");
                $em->getConnection()->commit();
            }

            return $response;
        } catch (Throwable $e) {
            // Roll back any open transactions on error
            if ($em->getConnection()->isTransactionActive()) {
                try {
                    $em->getConnection()->rollBack();
                } catch (Throwable $rollbackException) {
                    logger()->error("Failed to rollback transaction: " . $rollbackException->getMessage());
                }
            }

            throw $e;
        }
    }

    /**
     * PSR-15 middleware compatibility for frameworks that use Closure-based middleware
     *
     * @param ServerRequestInterface $request
     * @param Closure $next
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request, Closure $next): ResponseInterface
    {
        return $this->process($request, new class($next) implements RequestHandlerInterface {
            private $next;

            public function __construct(Closure $next)
            {
                $this->next = $next;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->next)($request);
            }
        });
    }
}