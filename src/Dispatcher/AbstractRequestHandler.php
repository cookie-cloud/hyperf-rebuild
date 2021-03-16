<?php


namespace Rebuild\Dispatcher;


use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

abstract class AbstractRequestHandler implements RequestHandlerInterface
{
    protected $middlewares = [];

    protected $offset = 0;

    /**
     * @var MiddlewareInterface
     */
    protected $coreHandler;

    /**
     * AbstractRequestHandler constructor.
     * @param array $middlewares
     * @param MiddlewareInterface $coreHandler
     */
    public function __construct(array $middlewares, MiddlewareInterface $coreHandler)
    {
        $this->middlewares = $middlewares;
        $this->coreHandler = $coreHandler;
    }


    protected function handleRequest($request)
    {
        if (! isset($this->middlewares[$this->offset]) && ! empty($this->coreHandler)) {
            $handler = $this->coreHandler;
        } else {
            $handler = $this->middlewares[$this->offset];
            is_string($handler) && $handler = new $handler($request);
        }
        if (! method_exists($handler, 'process')) {
            throw new \InvalidArgumentException(sprintf('Invalid middleware, it has to provide a process() method.'));
        }
        return $handler->process($request, $this->next());
    }

    /**
     * @return $this
     */
    protected function next()
    {
        ++$this->offset;
        return $this;
    }
}