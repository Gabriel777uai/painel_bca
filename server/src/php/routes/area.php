<?php

use Slim\Routing\RouteCollectorProxy;
use Controller\DashboadController;
use Controller\clientesController;
use Controller\AreaController;

return function (RouteCollectorProxy $group) {
    // Legacy routes
    $group->get('/search[/{icdarea}]', [DashboadController::class, 'getAreaSales']);
    $group->get('/to-search[/{search}[/{limit}]]', [DashboadController::class, 'getAreaSales']);
    $group->get('/test', [clientesController::class, 'intlTest']);

    // New routes
    $group->get('/list', [AreaController::class, 'listAreas']);
    $group->get('/name/{icdarea}', [AreaController::class, 'getAreaName']);
};
