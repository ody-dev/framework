<?php

namespace Ody\Core\Foundation\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ody\Core\Foundation\Http\Response;
use Ody\Core\Foundation\Logger;

/**
 * JSON Body Parser Middleware (PSR-15)
 */
class JsonBodyParserMiddleware implements MiddlewareInterface
{
    /**
     * Process an incoming server request
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (strpos($contentType, 'application/json') !== false) {
            $contents = (string) $request->getBody();

            if (!empty($contents)) {
                $data = json_decode($contents, true);
                if (is_array($data)) {
                    $request = $request->withParsedBody($data);
                }
            }
        }

        return $handler->handle($request);
    }
}