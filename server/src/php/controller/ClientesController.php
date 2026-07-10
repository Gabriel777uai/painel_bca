<?php

namespace Controller;

use Model\ClientModel;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Service\ResponseTypeService;
use Service\IntlService;
use PDOException;
use Exception;

use Config\RequestsDatabase;

class ClientesController extends ResponseTypeService
{
    private ClientModel $clientModel;
    private RequestsDatabase $db;

    public function __construct(ClientModel $clientModel = null, RequestsDatabase $db = null)
    {
        $this->clientModel = $clientModel ?? new ClientModel();
        $this->db = $db ?? new RequestsDatabase();
    }

    /**
     * Preserved legacy endpoint: lists clients up to limit
     */
    public function getClientes(Request $request, Response $response, array $args = [])
    {
        $limit = isset($args['limit']) ? (int)$args['limit'] : 100;
        try {
            // Re-using the model logic or directly querying for compatibility
            $db = new \Config\RequestsDatabase();
            $result = $db->fetchAll("SELECT * FROM cliente LIMIT :limit", ['limit' => $limit]);
            return self::sendResponse($response, $result, 200);
        } catch (PDOException $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Preserved legacy endpoint: fetch client by ID
     */
    public function getClientesForId(Request $request, Response $response, array $args = [])
    {
        $i_cd = isset($args['i_cd']) ? (int)$args['i_cd'] : 0;
        try {
            $db = new \Config\RequestsDatabase();
            $result = $db->fetch("SELECT * FROM cliente WHERE i_cdcliente = :i_cd", ['i_cd' => $i_cd]);
            return self::sendResponse($response, $result ?: [], 200);
        } catch (PDOException $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Preserved legacy endpoint: search client by text
     */
    public function getClientesForSearch(Request $request, Response $response, array $args = [])
    {
        $search = $args['search'] ?? '';
        $limit = isset($args['limit']) ? (int)$args['limit'] : 100;
        try {
            $db = new \Config\RequestsDatabase();
            $result = $db->fetchAll("SELECT * FROM cliente WHERE cliente.*::text ILIKE :search LIMIT :limit", [
                'search' => "%$search%",
                'limit' => $limit
            ]);
            return self::sendResponse($response, $result, 200);
        } catch (PDOException $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Preserved legacy endpoint: internationalization test
     */
    public function intlTest(Request $request, Response $response, array $args = [])
    {
        $number = 123456.789;
        $cpf = '12345678901';
        $cnpj = '12345678000199';
        $phone = '118765004321';
        $cep = '88888888';

        $intlService = new IntlService();

        $formattedCurrency = $intlService->format('currency', $number);
        $formattedCpf = $intlService->format('cpf', $cpf);
        $formattedCnpj = $intlService->format('cnpj', $cnpj);
        $formattedPhone = $intlService->format('phone', $phone);
        $formattedCep = $intlService->format('cep', $cep);

        return self::sendResponse($response, [
            'currency' => $formattedCurrency,
            'cpf' => $formattedCpf,
            'cnpj' => $formattedCnpj,
            'phone' => $formattedPhone,
            'cep' => $formattedCep
        ], 200);
    }

    /**
     * Endpoint: Today's new clients count (dashboard)
     */
    public function getNewClientsCount(Request $request, Response $response): Response
    {
        try {
            $count = $this->clientModel->getNewClientsToday();
            return self::sendResponse($response, ['novos_clientes' => $count], 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint: Inactive clients info (count, search, ordering) for a given area
     */
    public function getInactiveClients(Request $request, Response $response, array $args = []): Response
    {
        $queryParams = $request->getQueryParams();

        $areaId = isset($args['area']) ? (int)$args['area'] : (isset($queryParams['area']) ? (int)$queryParams['area'] : null);
        if ($areaId === null) {
            return self::sendResponse($response, ['error' => 'Area parameter is required'], 400);
        }

        $search = $queryParams['search'] ?? null;
        $orderBy = $queryParams['order_by'] ?? 'cidade';

        try {
            $count = $this->clientModel->getInactiveClientsCount($areaId);
            $list = $this->clientModel->getInactiveClientsList($areaId, $search, $orderBy);

            return self::sendResponse($response, [
                'total_inativos' => $count,
                'clientes' => $list
            ], 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint: Client counts by class for a given area
     */
    public function getClientClasses(Request $request, Response $response, array $args = []): Response
    {
        $queryParams = $request->getQueryParams();
        $areaId = isset($args['area']) ? (int)$args['area'] : (isset($queryParams['area']) ? (int)$queryParams['area'] : null);
        if ($areaId === null) {
            return self::sendResponse($response, ['error' => 'Area parameter is required'], 400);
        }

        try {
            $classes = $this->clientModel->getClientClassesCount($areaId);
            return self::sendResponse($response, $classes, 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint: Yearly comparison of client registrations (current vs last year)
     */
    public function getClientsComparison(Request $request, Response $response, array $args = []): Response
    {
        $queryParams = $request->getQueryParams();
        $areaId = isset($args['area']) ? (int)$args['area'] : (isset($queryParams['area']) ? (int)$queryParams['area'] : null);

        try {
            $currYear = $this->clientModel->getClientsCurrentYearCount($areaId);
            $lastYear = $this->clientModel->getClientsLastYearCount($areaId);

            return self::sendResponse($response, [
                'ano_atual' => $currYear,
                'ano_passado' => $lastYear
            ], 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }
}
