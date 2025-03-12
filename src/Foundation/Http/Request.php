<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Core\Foundation\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7Server\ServerRequestCreator;

/**
 * PSR-7 compatible HTTP Request
 */
class Request implements ServerRequestInterface
{
    /**
     * @var ServerRequestInterface PSR-7 server request
     */
    private ServerRequestInterface $psrRequest;

    /**
     * @var array Route parameters
     */
    public array $routeParams = [];

    /**
     * @var array Middleware parameters
     */
    public array $middlewareParams = [];

    /**
     * Request constructor
     *
     * @param ServerRequestInterface $request
     */
    public function __construct(ServerRequestInterface $request)
    {
        $this->psrRequest = $request;
    }

    /**
     * Create request from globals
     *
     * @return self
     */
    public static function createFromGlobals(): self
    {
        $psr17Factory = new Psr17Factory();

        $creator = new ServerRequestCreator(
            $psr17Factory, // ServerRequestFactory
            $psr17Factory, // UriFactory
            $psr17Factory, // UploadedFileFactory
            $psr17Factory  // StreamFactory
        );

        $serverRequest = $creator->fromGlobals();

        return new self($serverRequest);
    }

    /**
     * Get request method
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->psrRequest->getMethod();
    }

    /**
     * Get request URI
     *
     * @return UriInterface
     */
    public function getUri(): UriInterface
    {
        return $this->psrRequest->getUri();
    }

    /**
     * Get request URI as string
     *
     * @return string
     */
    public function getUriString(): string
    {
        return (string) $this->psrRequest->getUri();
    }

    /**
     * Get request path (without query string)
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->psrRequest->getUri()->getPath();
    }

    /**
     * Get raw request body
     *
     * @return string
     */
    public function rawContent(): string
    {
        return (string) $this->psrRequest->getBody();
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
        $parsedBody = $this->psrRequest->getParsedBody();

        if (is_array($parsedBody) && isset($parsedBody[$key])) {
            return $parsedBody[$key];
        }

        $queryParams = $this->psrRequest->getQueryParams();

        if (isset($queryParams[$key])) {
            return $queryParams[$key];
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
        $queryParams = $this->psrRequest->getQueryParams();
        $parsedBody = $this->psrRequest->getParsedBody();

        if (!is_array($parsedBody)) {
            $parsedBody = [];
        }

        return array_merge($queryParams, $parsedBody);
    }

    /* PSR-7 ServerRequestInterface methods */

    public function getProtocolVersion()
    {
        return $this->psrRequest->getProtocolVersion();
    }

    public function withProtocolVersion($version)
    {
        $new = clone $this;
        $new->psrRequest = $this->psrRequest->withProtocolVersion($version);
        return $new;
    }

    public function getHeaders()
    {
        return $this->psrRequest->getHeaders();
    }

    public function hasHeader($name)
    {
        return $this->psrRequest->hasHeader($name);
    }

    public function getHeader($name)
    {
        return $this->psrRequest->getHeader($name);
    }

    public function getHeaderLine($name)
    {
        return $this->psrRequest->getHeaderLine($name);
    }

    public function withHeader($name, $value)
    {
        $new = clone $this;
        $new->psrRequest = $this->psrRequest->withHeader($name, $value);
        return $new;
    }

    public function withAddedHeader($name, $value)
    {
        $new = clone $this;
        $new->psrRequest = $this->psrRequest->withAddedHeader($name, $value);
        return $new;
    }

    public function withoutHeader($name)
    {
        $new = clone $this;
        $new->psrRequest = $this->psrRequest->withoutHeader($name);
        return $new;
    }

    public function getBody()
    {
        return $this->psrRequest->getBody();
    }

    public function withBody(StreamInterface $body)
    {
        $new = clone $this;
        $new->psrRequest = $this->psrRequest->withBody($body);
        return $new;
    }

    public function getRequestTarget()
    {
        return $this->psrRequest->getRequestTarget();
    }

    public function withRequestTarget($requestTarget)
    {
        $new = clone $this;
        $new->psrRequest = $this->psrRequest->withRequestTarget($requestTarget);
        return $new;
    }

    public function withMethod($method)
    {
        $new = clone $this;
        $new->psrRequest = $this->psrRequest->withMethod($method);
        return $new;
    }

    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        $new = clone $this;
        $new->psrRequest = $this->psrRequest->withUri($uri, $preserveHost);
        return $new;
    }

    public function getServerParams()
    {
        return $this->psrRequest->getServerParams();
    }

    public function getCookieParams()
    {
        return $this->psrRequest->getCookieParams();
    }

    public function withCookieParams(array $cookies)
    {
        $new = clone $this;
        $new->psrRequest = $this->psrRequest->withCookieParams($cookies);
        return $new;
    }

    public function getQueryParams()
    {
        return $this->psrRequest->getQueryParams();
    }

    public function withQueryParams(array $query)
    {
        $new = clone $this;
        $new->psrRequest = $this->psrRequest->withQueryParams($query);
        return $new;
    }

    public function getUploadedFiles()
    {
        return $this->psrRequest->getUploadedFiles();
    }

    public function withUploadedFiles(array $uploadedFiles)
    {
        $new = clone $this;
        $new->psrRequest = $this->psrRequest->withUploadedFiles($uploadedFiles);
        return $new;
    }

    public function getParsedBody()
    {
        return $this->psrRequest->getParsedBody();
    }

    public function withParsedBody($data)
    {
        $new = clone $this;
        $new->psrRequest = $this->psrRequest->withParsedBody($data);
        return $new;
    }

    public function getAttributes()
    {
        return $this->psrRequest->getAttributes();
    }

    public function getAttribute($name, $default = null)
    {
        return $this->psrRequest->getAttribute($name, $default);
    }

    public function withAttribute($name, $value)
    {
        $new = clone $this;
        $new->psrRequest = $this->psrRequest->withAttribute($name, $value);
        return $new;
    }

    public function withoutAttribute($name)
    {
        $new = clone $this;
        $new->psrRequest = $this->psrRequest->withoutAttribute($name);
        return $new;
    }

    /**
     * Get the underlying PSR-7 server request
     *
     * @return ServerRequestInterface
     */
    public function getPsrRequest(): ServerRequestInterface
    {
        return $this->psrRequest;
    }
}