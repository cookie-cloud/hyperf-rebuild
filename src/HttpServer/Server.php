<?php


namespace Rebuild\HttpServer;

use FastRoute\Dispatcher;
use Rebuild\Config\ConfigFactory;
use Rebuild\Dispatcher\HttpRequestHandler;
use Rebuild\HttpServer\Contract\CoreMiddlewareInterface;
use Rebuild\HttpServer\Route\Dispatched;
use Rebuild\HttpServer\Route\DispatcherFactory;
use Hyperf\Utils\Context;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Hyperf\HttpMessage\Server\Request as Psr7Request;
use Hyperf\HttpMessage\Server\Response as Psr7Response;

class Server
{
    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * @var CoreMiddlewareInterface
     */
    protected $coreMiddleware;

    protected   $globalMiddlewares;

    /**
     * @var DispatcherFactory
     */
    protected $dispatcherFactory;

    /**
     * Server constructor.
     * @param DispatcherFactory $dispatcherFactory
     */
    public function __construct(DispatcherFactory $dispatcherFactory)
    {
        $this->dispatcherFactory = $dispatcherFactory;
        $this->dispatcher = $this->dispatcherFactory->getDispatcher('http');
    }

    public function initCoreMiddleware()
    {
        $config = (new ConfigFactory())();
        $this->globalMiddlewares = $config->get('middlewares');
        $this->coreMiddleware = new CoreMiddleware($this->dispatcherFactory);
    }

    public function onRequest(SwooleRequest $request, SwooleResponse $response)
    {
        /** @var RequestInterface $psr7Request */
        /** @var ResponseInterface $psr7Response */
        [$psr7Request, $psr7Response] = $this->initRequestAndResponse($request, $response);

        $psr7Request = $this->coreMiddleware->dispatch($psr7Request);

        $httpMethod = $psr7Request->getMethod();
        $path = $psr7Request->getUri()->getPath();

        $middlewares = $this->globalMiddlewares;

        $dispatched = $psr7Request->getAttribute(Dispatched::class);
        if ($dispatched instanceof Dispatched && $dispatched->isFound()) {
            $registeredMiddlewares = MiddlewareManager::get($path, $httpMethod);
            $middlewares = array_merge($middlewares, $registeredMiddlewares);
        }

        $requestHandler = new HttpRequestHandler($middlewares, $this->coreMiddleware);
        $psr7Response = $requestHandler->handle($psr7Request);

        foreach ($psr7Response->getHeaders() as $key => $value) {
            $response->header($key, implode(',', $value));
        }

        $response->status($psr7Response->getStatusCode());

        $response->end($psr7Response->getBody()->getContents());
    }

    protected function initRequestAndResponse(SwooleRequest $request, SwooleResponse $response): array
    {
        Context::set(ResponseInterface::class, $psr7Response = new Psr7Response());
        Context::set(RequestInterface::class, $psr7Request = Psr7Request::loadFromSwooleRequest($request));
        return [$psr7Request, $psr7Response];
    }
}