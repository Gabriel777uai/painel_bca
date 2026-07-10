<?php
namespace Service;
use Psr\Http\Message\ResponseInterface as Response;
use Config\Http;
class ResponseTypeService
{
    public static function sendResponse(Response $response, array $data = [], int $statusCode = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', Http::headerJson())->withStatus($statusCode);
    }
}