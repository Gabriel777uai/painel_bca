<?php

use Slim\Routing\RouteCollectorProxy;
use Controller\DashboadController;

return function (RouteCollectorProxy $group) {
    // Legacy routes
    $group->get('/dashboard/sales', [DashboadController::class, 'getMonthSalesAll']);
    $group->get('/dashboard/weekly', [DashboadController::class, 'weeklySales']);
    $group->get('/dashboard/orders-supervisor', [DashboadController::class, 'ordersBySupervisor']);
    $group->post('/dashboard/rca-date', [DashboadController::class, 'getDataRcaForDate']);
    $group->get('/dashboard/get-vendas-map[/{fist-date}[/{last-date}]]', [DashboadController::class, 'getMapOverview']);

    // New routes
    $group->get('/dashboard/overview', [DashboadController::class, 'getDashboardOverview']);
    $group->get('/weekly[/{area}[/{date}]]', [DashboadController::class, 'getWeeklySales']);
    $group->get('/monthly[/{area}]', [DashboadController::class, 'getMonthlySales']);
    $group->get('/team-sales[/{supervisor}]', [DashboadController::class, 'getTeamSales']);
    $group->get('/pending-orders', [DashboadController::class, 'getPendingOrders']);
    $group->get('/rca-metrics[/{area}]', [DashboadController::class, 'getPainelRcaMetrics']);
};
