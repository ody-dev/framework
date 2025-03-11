<?php
namespace Ody\Core\Foundation\Facades;

/**
 * App Facade
 *
 * @method static \Ody\Core\Foundation\Router getRouter()
 * @method static \Ody\Core\Foundation\Middleware\Middleware getMiddleware()
 * @method static \Ody\Core\Foundation\Logger getLogger()
 * @method static \Illuminate\Container\Container getContainer()
 * @method static \Psr\Http\Message\ResponseInterface handleRequest(\Psr\Http\Message\ServerRequestInterface $request = null)
 * @method static void run()
 */
class App extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return \Ody\Core\Foundation\Application::class;
    }
}