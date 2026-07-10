<?php

use Slim\Routing\RouteCollectorProxy;
use Controller\LogsController;

return function (RouteCollectorProxy $group) {
    $group->get('', [LogsController::class, 'listLogs']);
    $group->post('/analyze', [LogsController::class, 'analyzeLog']);
    $group->delete('/{id}', [LogsController::class, 'deleteLog']);
    $group->post('/clear', [LogsController::class, 'clearLogs']);
    $group->get('/test-error', [LogsController::class, 'testError']);
};
