<?php
namespace Ody\Core\Foundation\Http;

/**
 * Standard HTTP Response wrapper
 */
class Response
{
    /**
     * @var int HTTP status code
     */
    public $statusCode = 200;

    /**
     * @var array Response headers
     */
    private $headers = [];

    /**
     * @var string Response body
     */
    private $body = '';

    /**
     * @var bool Whether the response has been sent
     */
    private $sent = false;

    /**
     * Set HTTP status code
     *
     * @param int $statusCode
     * @return self
     */
    public function status(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Set response header
     *
     * @param string $name
     * @param string $value
     * @return self
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Set content type
     *
     * @param string $contentType
     * @return self
     */
    public function contentType(string $contentType): self
    {
        $this->header('Content-Type', $contentType);
        return $this;
    }

    /**
     * Set JSON content type
     *
     * @return self
     */
    public function json(): self
    {
        $this->contentType('application/json');
        return $this;
    }

    /**
     * Set plain text content type
     *
     * @return self
     */
    public function text(): self
    {
        $this->contentType('text/plain');
        return $this;
    }

    /**
     * Set HTML content type
     *
     * @return self
     */
    public function html(): self
    {
        $this->contentType('text/html');
        return $this;
    }

    /**
     * Set response body
     *
     * @param string $content
     * @return self
     */
    public function body(string $content): self
    {
        $this->body = $content;
        return $this;
    }

    /**
     * Set JSON response
     *
     * @param mixed $data
     * @param int $options JSON encoding options
     * @return self
     */
    public function withJson($data, int $options = 0): self
    {
        $this->json();
        $this->body(json_encode($data, $options));
        return $this;
    }

    /**
     * End the response
     *
     * @param string|null $content
     * @return void
     */
    public function end(?string $content = null): void
    {
        if ($content !== null) {
            $this->body = $content;
        }

        $this->send();
    }

    /**
     * Send the response
     *
     * @return void
     */
    public function send(): void
    {
        if ($this->sent) {
            return;
        }

        // Set status code
        http_response_code($this->statusCode);

        // Set headers
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        // Output body
        echo $this->body;

        $this->sent = true;
    }

    /**
     * Check if response has been sent
     *
     * @return bool
     */
    public function isSent(): bool
    {
        return $this->sent;
    }

    /**
     * Get response body
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Get headers
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}