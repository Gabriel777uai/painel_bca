<?php

use Slim\Routing\RouteCollectorProxy;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

return function (RouteCollectorProxy $group) {
    $group->get('/hello/user/{user}', function (Request $request, Response $response, array $args) {
        $user = $args['user'] ?? 'Guest';
        $response->getBody()->write("Hello, $user!");
        return $response;
    });
};
