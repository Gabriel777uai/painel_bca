<?php

use Slim\Routing\RouteCollectorProxy;
use Controller\RelatoriosController;

return function (RouteCollectorProxy $group) {
    $group->get('/cidades', [RelatoriosController::class, 'listarCidades']);
    $group->get('/clientes', [RelatoriosController::class, 'gerarRelatorioClientes']);
    $group->post('/clientes', [RelatoriosController::class, 'gerarRelatorioClientes']);
    $group->get('/inativos', [RelatoriosController::class, 'gerarRelatorioInativos']);
    $group->post('/inativos', [RelatoriosController::class, 'gerarRelatorioInativos']);
};
