<?php
namespace Ody\Core\Foundation\Facades;

/**
 * Config Facade
 *
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool has(string $key)
 * @method static void set(string $key, mixed $value)
 * @method static array all()
 * @method static void merge(array $items)
 * @method static void mergeKey(string $key, array $items)
 * @method static void loadFromDirectory(string $path)
 */
class Config extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'config';
    }
}