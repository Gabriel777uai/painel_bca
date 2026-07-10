<?php

namespace Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Service\ResponseTypeService;
use Service\ErrorLoggerService;
use Exception;

class LogsController extends ResponseTypeService
{
    /**
     * List all log entries.
     */
    public function listLogs(Request $request, Response $response): Response
    {
        try {
            $logs = ErrorLoggerService::getLogs();
            return self::sendResponse($response, $logs, 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Triggers AI Analysis using n8n webhook and updates log entry.
     */
    public function analyzeLog(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (empty($body)) {
            $rawBody = (string)$request->getBody();
            $body = json_decode($rawBody, true) ?? [];
        }
        $id = $body['id'] ?? null;
        $customWebhookUrl = $body['webhook_url'] ?? null;

        if (empty($id)) {
            return self::sendResponse($response, ['error' => 'O ID do log é obrigatório.'], 400);
        }

        try {
            $logs = ErrorLoggerService::getLogs();
            $targetLog = null;
            foreach ($logs as $log) {
                if ($log['id'] === $id) {
                    $targetLog = $log;
                    break;
                }
            }

            if (!$targetLog) {
                return self::sendResponse($response, ['error' => 'Log não encontrado.'], 404);
            }

            // Mark status as analyzing (optional, but good for n8n response tracing)
            ErrorLoggerService::updateLog($id, ['status' => 'analyzing']);

            // Send to n8n
            $analysis = ErrorLoggerService::sendToN8n($targetLog, $customWebhookUrl);

            // Update log with results
            ErrorLoggerService::updateLog($id, [
                'status' => 'analyzed',
                'ai_analysis' => $analysis
            ]);

            return self::sendResponse($response, [
                'success' => true,
                'id' => $id,
                'ai_analysis' => $analysis
            ], 200);

        } catch (Exception $e) {
            // Restore status to pending if it failed
            if (isset($id)) {
                ErrorLoggerService::updateLog($id, ['status' => 'pending']);
            }
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Deletes a specific log.
     */
    public function deleteLog(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'] ?? null;
        if (empty($id)) {
            return self::sendResponse($response, ['error' => 'O ID do log é obrigatório.'], 400);
        }

        try {
            $success = ErrorLoggerService::deleteLog($id);
            if ($success) {
                return self::sendResponse($response, ['success' => true], 200);
            } else {
                return self::sendResponse($response, ['error' => 'Falha ao deletar log ou log não encontrado.'], 404);
            }
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Clears all logs.
     */
    public function clearLogs(Request $request, Response $response): Response
    {
        try {
            $success = ErrorLoggerService::clearLogs();
            return self::sendResponse($response, ['success' => $success], 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generates a test exception to verify log catching and dashboard display.
     */
    public function testError(Request $request, Response $response): Response
    {
        try {
            throw new Exception("Erro de Simulação: O banco de dados ou a API encontrou uma falha simulada pelo desenvolvedor para testar a integração com IA.");
        } catch (Exception $e) {
            // Forcefully call logger first in case error handling is not fully initialized
            ErrorLoggerService::log($e, $request);
            // Throw so that the Slim error middleware is also triggered and returns standard 500
            throw $e;
        }
    }
}
