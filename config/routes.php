<?php

use App\Controller\HelloController;
use App\Middleware\MiddlewareB;

return [
    ['GET', '/hello/index', [HelloController::class, 'index'],['middleware' => [MiddlewareB::class]]],
    ['GET', '/hello/hyperf', [HelloController::class, 'hyperf']],
];