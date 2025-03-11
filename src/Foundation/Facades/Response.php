<?php
namespace Ody\Core\Foundation\Facades;

/**
 * Response Facade
 *
 * @method static \Ody\Core\Foundation\Http\Response status(int $statusCode)
 * @method static \Ody\Core\Foundation\Http\Response header(string $name, string $value)
 * @method static \Ody\Core\Foundation\Http\Response contentType(string $contentType)
 * @method static \Ody\Core\Foundation\Http\Response json()
 * @method static \Ody\Core\Foundation\Http\Response text()
 * @method static \Ody\Core\Foundation\Http\Response html()
 * @method static \Ody\Core\Foundation\Http\Response body(string $content)
 * @method static \Ody\Core\Foundation\Http\Response withJson(mixed $data, int $options = 0)
 */
class Response extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'response';
    }
}