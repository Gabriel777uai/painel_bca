<?php

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use Slim\Routing\RouteCollectorProxy;

class AppBuilder
{
    public static function createApp(): \Slim\App
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->safeLoad();

        $app = AppFactory::create();
        $app->addRoutingMiddleware();
        
        $errorMiddleware = $app->addErrorMiddleware(true, true, true);
        $errorMiddleware->setDefaultErrorHandler(function (
            \Psr\Http\Message\ServerRequestInterface $request,
            \Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails
        ) use ($app) {
            \Service\ErrorLoggerService::log($exception, $request);

            $response = $app->getResponseFactory()->createResponse();
            $data = [
                'error' => true,
                'message' => $exception->getMessage(),
                'file' => str_replace('\\', '/', $exception->getFile()),
                'line' => $exception->getLine()
            ];
            $response->getBody()->write(json_encode($data));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                ->withStatus(500);
        });

        $app->group('/painel_bca/server/api/v1', function (RouteCollectorProxy $v1) {
            $v1->group('/wellcome', require __DIR__ . '/routes/hello.php');
            $v1->group('/sales', require __DIR__ . '/routes/vendas.php');
            $v1->group('/area', require __DIR__ . '/routes/area.php');
            $v1->group('/clientes', require __DIR__ . '/routes/clientes.php');
            $v1->group('/supervisores', require __DIR__ . '/routes/supervisores.php');
            $v1->group('/inadimplencia', require __DIR__ . '/routes/inadimplencia.php');
            $v1->group('/relatorios', require __DIR__ . '/routes/relatorios.php');
            $v1->group('/logs', require __DIR__ . '/routes/logs.php');
        })->add(function ($request, $handler) {
            $response = $handler->handle($request);
            return $response
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        });

        return $app;
    }
}
