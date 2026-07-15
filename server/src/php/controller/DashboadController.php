<?php

namespace Controller;

use Model\SalesModel;
use Model\ClientModel;
use Model\AreaModel;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Service\ResponseTypeService;
use Exception;

use Config\RequestsDatabase;

class DashboadController extends ResponseTypeService
{
    private SalesModel $salesModel;
    private ClientModel $clientModel;
    private AreaModel $areaModel;
    private RequestsDatabase $db;

    public function __construct()
    {
        $this->salesModel = $salesModel ?? new SalesModel();
        $this->clientModel = $clientModel ?? new ClientModel();
        $this->areaModel = $areaModel ?? new AreaModel();
        $this->db = $db ?? new RequestsDatabase();
    }

    /**
     * Preserved legacy endpoint: monthly faturamento totals for current year
     */
    public function getMonthSalesAll(Request $request, Response $response)
    {
        try {
            $result = $this->salesModel->getMonthlySalesTotal();
            return self::sendResponse($response, $result, 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Preserved legacy endpoint: best sellers of the week
     */
    public function weeklySales(Request $request, Response $response)
    {
        try {
            $result = $this->salesModel->getBestSellersOfWeek();
            return self::sendResponse($response, $result, 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Preserved legacy endpoint: faturamento and orders count by supervisor
     */
    public function ordersBySupervisor(Request $request, Response $response)
    {
        try {
            $result = $this->salesModel->getSupervisorOrdersTotal();
            return self::sendResponse($response, $result, 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Preserved legacy endpoint: get specific area sales / details
     */
    public function getAreaSales(Request $request, Response $response, array $args = [])
    {
        $areaId = $args['icdarea'] ?? 9;
        if (is_numeric($areaId)) {
            $areaId = (int) $areaId;
        } else {
            return self::sendResponse($response, ['error' => 'Invalid area ID'], 400);
        }
        try {
            $db = new \Config\RequestsDatabase();
            $result = $db->fetch("SELECT * FROM area WHERE i_cdarea = :areaId", ['areaId' => $areaId]);
            return self::sendResponse($response, $result ?: [], 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Preserved legacy endpoint: compare faturamento in target year vs previous year for supervisor's team
     */
    public function getDataRcaForDate(Request $request, Response $response)
    {
        $body = $request->getParsedBody();
        $dateStart = $body['data_inicio'] ?? null;
        $dateEnd = $body['data_fim'] ?? null;
        $area = $body['area'] ?? null;

        if (empty($dateStart) || empty($dateEnd) || empty($area)) {
            return self::sendResponse($response, ['error' => 'Invalid input data'], 400);
        }

        try {
            $result = $this->salesModel->getRcaFaturamentoComparison((int)$area, $dateStart, $dateEnd);
            return self::sendResponse($response, $result, 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Preserved legacy endpoint: faturamento heat map state-by-state
     */
    public function getMapOverview(Request $request, Response $response, array $args = [])
    {
        $fistDate = $args['fist-date'] ?? null;
        $lastDate = $args['last-date'] ?? null;
        $and = "and p.d_cadastro between date_trunc('month', date(:fist-date)) and date(:last-date)";
        $execute_array = [
            ':fist-date' => $fistDate,
            ':last-date' => $lastDate
        ];
        if (empty($fistDate) || empty($lastDate)) {
            $and = "and p.d_cadastro between date_trunc('month', date(CURRENT_DATE)) and date(CURRENT_DATE)";
            $execute_array = [];
        }
        $sql = "select lower(es.c_uf) as estado_uf, count(p.i_nrpedido) as quantidade_vendas, sum(p.n_vlrdigitado) as vlr_digitado 
                from pedidovenda p
                join cliente c on p.i_cdcliente = c.i_cdcliente 
                join cidade c2 on c.i_cdcidade = c2.i_cdcidade 
                join estado es on c2.i_cduf = es.i_cduf 
                where p.f_separadocoletor = 'T' and p.i_cdarea != 308 and p.f_cancelado = 'N' and p.f_faturou = 'S' $and 
                group by es.c_uf";

        try {
            $db = new \Config\RequestsDatabase();
            $result = $db->fetchAll($sql, $execute_array);
            return self::sendResponse($response, $result, 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint: General dashboard overview metrics combined
     */
    public function getDashboardOverview(Request $request, Response $response): Response
    {
        try {
            $todayOrders = $this->salesModel->getTodayOrdersCount();
            $monthOrders = $this->salesModel->getMonthOrdersCount();
            $newClients = $this->clientModel->getNewClientsToday();
            $bestSellers = $this->salesModel->getBestSellersOfWeek();
            $monthlySales = $this->salesModel->getMonthlySalesTotal();
            $supervisorOrders = $this->salesModel->getSupervisorOrdersTotal();

            return self::sendResponse($response, [
                'pedidos_dia' => $todayOrders,
                'pedidos_mes' => $monthOrders,
                'clientes_novos_hoje' => $newClients,
                'melhores_semana' => $bestSellers,
                'grafico_mensal' => $monthlySales,
                'pedidos_por_supervisor' => $supervisorOrders
            ], 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint: Weekly sales count and faturamento totals day-by-day (semanal)
     */
    public function getWeeklySales(Request $request, Response $response, array $args = []): Response
    {
        $queryParams = $request->getQueryParams();

        $areaId = isset($args['area']) ? (int)$args['area'] : (isset($queryParams['area']) ? (int)$queryParams['area'] : null);
        $date = $args['date'] ?? ($queryParams['date'] ?? null);

        if ($areaId === null || empty($date)) {
            return self::sendResponse($response, ['error' => 'Area and date parameters are required'], 400);
        }

        try {
            $sales = $this->salesModel->getWeeklySales($areaId, $date);
            $areaName = $this->areaModel->getAreaName($areaId);

            return self::sendResponse($response, [
                'area_id' => $areaId,
                'area_nome' => $areaName,
                'weekly_sales' => $sales
            ], 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint: Monthly sales breakdown (mensal / mensal_por_periodo)
     */
    public function getMonthlySales(Request $request, Response $response, array $args = []): Response
    {
        $queryParams = $request->getQueryParams();

        $areaId = isset($args['area']) ? (int)$args['area'] : (isset($queryParams['area']) ? (int)$queryParams['area'] : null);
        $dateStart = $queryParams['date_start'] ?? null;
        $dateEnd = $queryParams['date_end'] ?? null;

        if ($areaId === null || empty($dateStart)) {
            return self::sendResponse($response, ['error' => 'Area and date_start are required'], 400);
        }

        try {
            $areaName = $this->areaModel->getAreaName($areaId);

            if (!empty($dateEnd)) {
                $sales = $this->salesModel->getMonthlySalesByPeriod($areaId, $dateStart, $dateEnd);
            } else {
                $sales = $this->salesModel->getMonthlySales($areaId, $dateStart);
            }

            return self::sendResponse($response, [
                'area_id' => $areaId,
                'area_nome' => $areaName,
                'monthly_sales' => $sales
            ], 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint: Team sales under supervisor by date or date range (select_geral_equipe / select_geral_equipe_data)
     */
    public function getTeamSales(Request $request, Response $response, array $args = []): Response
    {
        $queryParams = $request->getQueryParams();

        $supervisorId = isset($args['supervisor']) ? (int)$args['supervisor'] : (isset($queryParams['supervisor']) ? (int)$queryParams['supervisor'] : null);
        if ($supervisorId === null) {
            return self::sendResponse($response, ['error' => 'Supervisor parameter is required'], 400);
        }

        $dateStart = $queryParams['date_start'] ?? null;
        $dateEnd = $queryParams['date_end'] ?? null;

        try {
            if (!empty($dateStart) && !empty($dateEnd)) {
                $sales = $this->salesModel->getTeamSalesByDateRange($supervisorId, $dateStart, $dateEnd);
            } else {
                $date = !empty($dateStart) ? $dateStart : date('Y-m-d');
                $sales = $this->salesModel->getTeamSalesByDate($supervisorId, $date);
            }

            return self::sendResponse($response, $sales, 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint: Paginated pending orders list with search filters (lista_pedidos / buscar)
     */
    public function getPendingOrders(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();

        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 15;

        $dateStart = $queryParams['date_start'] ?? null;
        $dateEnd = $queryParams['date_end'] ?? null;
        $areaId = isset($queryParams['area']) ? (int)$queryParams['area'] : null;

        try {
            if (!empty($dateStart) && !empty($dateEnd) && $areaId !== null) {
                // Search filter mode
                $orders = $this->salesModel->getPendingOrdersSearch($dateStart, $dateEnd, $areaId);
                return self::sendResponse($response, [
                    'mode' => 'search',
                    'pedidos' => $orders
                ], 200);
            } else {
                // Paginated mode
                $orders = $this->salesModel->getPendingOrders($page, $limit);
                $total = $this->salesModel->getPendingOrdersCount();
                $totalPages = ceil($total / $limit);

                return self::sendResponse($response, [
                    'mode' => 'paginated',
                    'pagina_atual' => $page,
                    'limite' => $limit,
                    'total_registros' => $total,
                    'total_paginas' => $totalPages,
                    'pedidos' => $orders
                ], 200);
            }
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint: RCA Dashboard metrics (today's orders, monthly orders, today's new clients, inactive clients count)
     */
    public function getPainelRcaMetrics(Request $request, Response $response, array $args = []): Response
    {
        $queryParams = $request->getQueryParams();
        $areaId = isset($args['area']) ? (int)$args['area'] : (isset($queryParams['area']) ? (int)$queryParams['area'] : null);
        if ($areaId === null) {
            return self::sendResponse($response, ['error' => 'Area parameter is required'], 400);
        }

        try {
            $todayClients = $this->clientModel->getNewClientsToday($areaId);
            $todayOrders = $this->salesModel->getPainelRcaPedidosHoje($areaId);
            $inactiveClients = $this->clientModel->getInactiveClientsCount($areaId);
            $monthOrders = $this->salesModel->getPainelRcaPedidosMensal($areaId);

            return self::sendResponse($response, [
                'area_id' => $areaId,
                'clientes_novos_hoje' => $todayClients,
                'pedidos_dia' => $todayOrders,
                'clientes_inativos' => $inactiveClients,
                'pedidos_mensal' => $monthOrders
            ], 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }
}
