<?php
namespace Ody\Core\Foundation\Facades;

/**
 * Request Facade
 *
 * @method static string getMethod()
 * @method static \Psr\Http\Message\UriInterface getUri()
 * @method static string getUriString()
 * @method static string getPath()
 * @method static string rawContent()
 * @method static mixed json(bool $assoc = true)
 * @method static mixed input(string $key, mixed $default = null)
 * @method static array all()
 */
class Request extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'request';
    }

    /**
     * Create a request from globals
     *
     * This is a static method on the actual Request class
     *
     * @return \Ody\Core\Foundation\Http\Request
     */
    public static function createFromGlobals()
    {
        return \Ody\Core\Foundation\Http\Request::createFromGlobals();
    }
}