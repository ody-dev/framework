<?php

namespace Ody\Core\Foundation\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestHandler implements RequestHandlerInterface
{
    /**
     * @var callable
     */
    private $callable;

    /**
     * @var array Additional parameters to pass to the handler
     */
    private $params;

    /**
     * RequestHandler constructor
     *
     * @param callable $callable The route handler
     * @param array $params Additional parameters (like route variables)
     */
    public function __construct(callable $callable, array $params = [])
    {
        $this->callable = $callable;
        $this->params = $params;
    }

    /**
     * Handle the request and produce a response
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        // Call the route handler with the request, response and params
        $result = call_user_func($this->callable, $request, $response, $this->params);

        // If the handler returned a response, use it
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        // If nothing was returned, use the response we created
        return $response;
    }
}