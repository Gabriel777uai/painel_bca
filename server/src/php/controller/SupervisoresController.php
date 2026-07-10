<?php

namespace Controller;

use Model\SupervisorModel;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Service\ResponseTypeService;
use Exception;

class SupervisoresController extends ResponseTypeService
{
    private SupervisorModel $supervisorModel;

    public function __construct()
    {
        $this->supervisorModel = new SupervisorModel();
    }

    /**
     * Endpoint to list active supervisors
     */
    public function listSupervisores(Request $request, Response $response): Response
    {
        try {
            $supervisores = $this->supervisorModel->listSupervisores();
            return self::sendResponse($response, $supervisores, 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint to get supervisor name by code/id
     */
    public function getSupervisorName(Request $request, Response $response, array $args): Response
    {
        $supervisorId = $args['code'] ?? null;
        if ($supervisorId === null) {
            return self::sendResponse($response, ['error' => 'Supervisor ID/code is required'], 400);
        }

        try {
            $name = $this->supervisorModel->getSupervisorName((int)$supervisorId);
            if ($name === null) {
                return self::sendResponse($response, ['error' => 'Supervisor not found'], 404);
            }
            return self::sendResponse($response, ['c_nome' => $name], 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint to list sellers/representatives, optionally filtered by manager (gerente) code
     */
    public function getSellers(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $code = $queryParams['code'] ?? null;
        $isGerente = ($code !== null && $code !== '' && (int)$code > 0);

        try {
            $sellers = $this->supervisorModel->getSellersData($isGerente, (int)$code);
            return self::sendResponse($response, $sellers, 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }
}
