<?php


namespace Rebuild\HttpServer;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;
use Hyperf\Utils\Context;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Hyperf\HttpMessage\Server\Request as Psr7Request;
use Hyperf\HttpMessage\Server\Response as Psr7Response;

class Server
{
    public function onRequest(SwooleRequest $request, SwooleResponse $response)
    {
        /** @var RequestInterface $psr7Request */
        /** @var ResponseInterface $psr7Response */
        [$psr7Request, $psr7Response] = $this->initRequestAndResponse($request, $response);

        $httpMethod = $psr7Request->getMethod();
        $uri   = $psr7Request->getUri()->getPath();

        $dispatcher = simpleDispatcher(function (RouteCollector $r) {
            $routes = require BASE_PATH . '/config/routes.php';
            foreach ($routes as $route) {
                [$method, $path, $handler] = $route;
                $r->addRoute($method, $path, $handler);
            }
        });

        $routeInfo = $dispatcher->dispatch($httpMethod, $uri);
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                $response->status(404);
                $response->end('Not Found');
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $allowMethods = $routeInfo[1];
                $response->status(405);
                $response->header('Method-Allows', implode(',', $allowMethods));
                break;
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];
                [$controller, $action] = $handler;
                $instance = new $controller();
                $result = $instance->$action(...$vars);
                $response->end($result);
                break;
        }


    }

    protected function initRequestAndResponse(SwooleRequest $request, SwooleResponse $response): array
    {
        Context::set(ResponseInterface::class, $psr7Response = new Psr7Response());
        Context::set(RequestInterface::class, $psr7Request = Psr7Request::loadFromSwooleRequest($request));
        return [$psr7Request, $psr7Response];
    }
}