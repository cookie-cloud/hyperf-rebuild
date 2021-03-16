<?php


namespace Rebuild\HttpServer;


use FastRoute\Dispatcher;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Utils\Context;
use Hyperf\Utils\Contracts\Arrayable;
use Hyperf\Utils\Contracts\Jsonable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rebuild\HttpServer\Contract\CoreMiddlewareInterface;
use Rebuild\HttpServer\Route\DispatcherFactory;
use Rebuild\HttpServer\Route\Dispatched;

class CoreMiddleware implements CoreMiddlewareInterface
{
    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    public function __construct(DispatcherFactory $dispatcherFactory)
    {
        $this->dispatcher = $dispatcherFactory->getDispatcher('http');
    }

    public function dispatch(ServerRequestInterface $request): ServerRequestInterface
    {
        $httpMethod = $request->getMethod();
        $uri = $request->getUri()->getPath();

        $routeInfo = $this->dispatcher->dispatch($httpMethod, $uri);
        $dispatched = new Dispatched($routeInfo);

        $request = Context::set(ServerRequestInterface::class, $request->withAttribute(Dispatched::class, $dispatched));
        return $request;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $dispatched = $request->getAttribute(Dispatched::class);
        if (!$dispatched instanceof Dispatched) {
            throw new \InvalidArgumentException('Route not found');
        }

        switch ($dispatched->status) {
            case Dispatcher::NOT_FOUND:
                $response = $this->handleNotFound($request);
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $response = $this->handleMethodNotFound($request);
                break;
            case Dispatcher::FOUND:
                $response = $this->handleFound($request, $dispatched);
                break;
        }
        if (! $response instanceof ResponseInterface) {
            $response = $this->transferToResponse($response);
        }

        return $response;
    }

    protected function handleNotFound(ServerRequestInterface $request)
    {
        /** @var ResponseInterface $response */
        return $this->response()->withStatus(404)->withBody(new SwooleStream('Not Found'));
    }

    protected function handleMethodNotFound(ServerRequestInterface $request)
    {
        /** @var ResponseInterface $response */
        return $this->response()->withStatus(405)->withBody(new SwooleStream('Method Not Allow'));
    }

    protected function handleFound(ServerRequestInterface $request, Dispatched $dispatched)
    {
        [$controller, $action] = $dispatched->handler;
        if (!class_exists($controller)) {
            throw new \InvalidArgumentException('Controller Not Exists');
        }

        if (!method_exists($controller, $action)) {
            throw new \InvalidArgumentException('Action Of Controller Not Exists');
        }

        $controllerInstance = new $controller;
        $response = $controllerInstance->{$action}(...$dispatched->params);
        return $response;
    }

    protected function transferToResponse($response)
    {
        if (is_string($response)) {
            return $this->response()
                ->withAddedHeader('Content-Type', 'text/plain')
                ->withBody(new SwooleStream($response));
        }

        if (is_array($response) && $response instanceof Arrayable) {
            return $this->response()
                ->withAddedHeader('Content-Type', 'application/json')
                ->withBody(new SwooleStream(json_encode($response)));
        }

        if ($response instanceof Jsonable) {
            return $this->response()
                ->withAddedHeader('Content-Type', 'application/json')
                ->withBody(new SwooleStream((string)$response));
        }

        return $this->response()
            ->withAddedHeader('Content-Type', 'text/plain')
            ->withBody(new SwooleStream((string)$response));
    }

    protected function response(): ResponseInterface
    {
        return Context::get(ResponseInterface::class);
    }
}