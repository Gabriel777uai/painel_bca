<?php

use Slim\Routing\RouteCollectorProxy;
use Controller\ClientesController;

return function (RouteCollectorProxy $group) {
    // Legacy search/all routes
    $group->get('/search/all/{limit}', [ClientesController::class, "getClientes"]);
    $group->get('/search/for-id[/{i_cd}]', [ClientesController::class, "getClientesForId"]);
    $group->get('/search/to-search[/{search}[/{limit}]]', [ClientesController::class, "getClientesForSearch"]);

    // New dashboard and query routes
    $group->get('/dashboard/new-count', [ClientesController::class, "getNewClientsCount"]);
    $group->get('/inactive[/{area}]', [ClientesController::class, "getInactiveClients"]);
    $group->get('/classes[/{area}]', [ClientesController::class, "getClientClasses"]);
    $group->get('/comparison[/{area}]', [ClientesController::class, "getClientsComparison"]);
};
