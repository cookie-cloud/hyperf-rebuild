<?php


namespace Rebuild\HttpServer\Route;


use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Rebuild\HttpServer\MiddlewareManager;
use function FastRoute\simpleDispatcher;

class DispatcherFactory
{
    /**
     * @var string[]
     */
    protected $routeFiles = [BASE_PATH . '/config/routes.php'];

    /**
     * @var Dispatcher[]
     */
    protected $dispatcher = [];

    /**
     * @var array
     */
    protected $routes = [];

    public function __construct()
    {
        $this->initConfigRoute();
    }

    public function getDispatcher(string $serverName): Dispatcher
    {
        if (! isset($this->dispatcher[$serverName])) {
            $this->dispatcher[$serverName] = simpleDispatcher(function (RouteCollector $r) {
                foreach ($this->routes as $route) {
                    [$httpMethod, $path, $handler] = $route;
                    if (isset($route[3])) {
                        $options = $route[3];
                        if (isset($options['middleware']) && is_array($options['middleware'])) {
                            MiddlewareManager::addMiddlerwares($path, $httpMethod, $options['middleware']);
                        }
                    }
                    $r->addRoute($httpMethod, $path, $handler);

                }
            });
        }

        return $this->dispatcher[$serverName];
    }

    public function initConfigRoute()
    {
        foreach ($this->routeFiles as $file) {
            if (file_exists($file)) {
                $route = require_once $file;
                $this->routes = array_merge_recursive($this->routes, $route);
            }
        }
    }
}