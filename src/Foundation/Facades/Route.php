<?php
namespace Ody\Core\Foundation\Facades;

/**
 * Route Facade
 *
 * Allows route registration using static methods
 *
 * @method static \Ody\Core\Foundation\Route get(string $path, mixed $handler)
 * @method static \Ody\Core\Foundation\Route post(string $path, mixed $handler)
 * @method static \Ody\Core\Foundation\Route put(string $path, mixed $handler)
 * @method static \Ody\Core\Foundation\Route patch(string $path, mixed $handler)
 * @method static \Ody\Core\Foundation\Route delete(string $path, mixed $handler)
 * @method static \Ody\Core\Foundation\Route options(string $path, mixed $handler)
 * @method static \Ody\Core\Foundation\Router group(array $attributes, callable $callback)
 */
class Route extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'router';
    }
}