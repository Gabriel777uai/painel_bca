<?php

use Slim\Routing\RouteCollectorProxy;
use Controller\InadimplenciaController;

return function (RouteCollectorProxy $group) {
    $group->get('/get', [InadimplenciaController::class, 'getInadimplencia']);
};
