<?php

namespace Controller;

use Model\AreaModel;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Service\ResponseTypeService;
use Exception;

class AreaController extends ResponseTypeService
{
    private AreaModel $areaModel;

    public function __construct(AreaModel $areaModel = null)
    {
        $this->areaModel = $areaModel ?? new AreaModel();
    }

    /**
     * Endpoint to list all active areas
     */
    public function listAreas(Request $request, Response $response): Response
    {
        try {
            $areas = $this->areaModel->listActiveAreas();
            return self::sendResponse($response, $areas, 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint to get a specific area's name
     */
    public function getAreaName(Request $request, Response $response, array $args): Response
    {
        $areaId = $args['icdarea'] ?? null;
        if ($areaId === null) {
            return self::sendResponse($response, ['error' => 'Area ID is required'], 400);
        }

        try {
            $name = $this->areaModel->getAreaName((int)$areaId);
            if ($name === null) {
                return self::sendResponse($response, ['error' => 'Area not found'], 404);
            }
            return self::sendResponse($response, ['c_nomearea' => $name], 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }
}
