<?php

use Slim\Routing\RouteCollectorProxy;
use Controller\SupervisoresController;

return function (RouteCollectorProxy $group) {
    $group->get('/list', [SupervisoresController::class, 'listSupervisores']);
    $group->get('/name/{code}', [SupervisoresController::class, 'getSupervisorName']);
    $group->get('/sellers', [SupervisoresController::class, 'getSellers']);
};
