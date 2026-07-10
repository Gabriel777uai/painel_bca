<?php

namespace Service;

use Throwable;
use Exception;
use PDO;

class ErrorLoggerService
{
    /**
     * Initializes and returns the SQLite PDO connection.
     * Creates the table if it does not exist.
     */
    private static function getPdo(): PDO
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $dbFile = $logDir . '/errors.db';
        $pdo = new PDO("sqlite:" . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Initialize SQLite table schema
        $pdo->exec("CREATE TABLE IF NOT EXISTS app_errors (
            id TEXT PRIMARY KEY,
            timestamp TEXT NOT NULL,
            message TEXT,
            file TEXT,
            line INTEGER,
            trace TEXT,
            request_method TEXT,
            request_uri TEXT,
            status TEXT DEFAULT 'pending',
            ai_analysis TEXT
        )");
        
        return $pdo;
    }

    /**
     * Logs a throwable exception into the SQLite database.
     */
    public static function log(Throwable $exception, $request = null): void
    {
        try {
            $pdo = self::getPdo();
            $stmt = $pdo->prepare("INSERT INTO app_errors (id, timestamp, message, file, line, trace, request_method, request_uri, status, ai_analysis) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $id = uniqid('err_', true);
            $timestamp = date('c');
            $message = $exception->getMessage();
            $file = str_replace('\\', '/', $exception->getFile());
            $line = $exception->getLine();
            $trace = substr($exception->getTraceAsString(), 0, 4000); // Truncate trace if it's too long
            
            $request_method = $request ? (is_string($request) ? 'N/A' : (method_exists($request, 'getMethod') ? $request->getMethod() : 'N/A')) : 'N/A';
            $request_uri = $request ? (is_string($request) ? 'N/A' : (method_exists($request, 'getUri') ? (string)$request->getUri() : 'N/A')) : 'N/A';
            $status = 'pending';
            $ai_analysis = null;
            
            $stmt->execute([$id, $timestamp, $message, $file, $line, $trace, $request_method, $request_uri, $status, $ai_analysis]);
        } catch (Throwable $e) {
            // Silently fail so that error logging does not trigger secondary crashes
        }
    }

    /**
     * Reads all log entries from the SQLite database.
     * Returns them sorted from newest to oldest.
     */
    public static function getLogs(): array
    {
        try {
            $pdo = self::getPdo();
            $stmt = $pdo->query("SELECT * FROM app_errors ORDER BY rowid DESC");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format line to integer type
            return array_map(function($log) {
                if (isset($log['line'])) {
                    $log['line'] = (int)$log['line'];
                }
                return $log;
            }, $results);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Updates an existing log entry by ID in SQLite.
     */
    public static function updateLog(string $id, array $updatedData): bool
    {
        try {
            $pdo = self::getPdo();
            
            $fields = [];
            $values = [];
            foreach ($updatedData as $key => $val) {
                $fields[] = "{$key} = ?";
                $values[] = $val;
            }
            
            if (empty($fields)) {
                return false;
            }
            
            $values[] = $id;
            $stmt = $pdo->prepare("UPDATE app_errors SET " . implode(', ', $fields) . " WHERE id = ?");
            return $stmt->execute($values);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Deletes a log entry by ID.
     */
    public static function deleteLog(string $id): bool
    {
        try {
            $pdo = self::getPdo();
            $stmt = $pdo->prepare("DELETE FROM app_errors WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Clears all recorded logs.
     */
    public static function clearLogs(): bool
    {
        try {
            $pdo = self::getPdo();
            $pdo->exec("DELETE FROM app_errors");
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Sends the log entry details to the n8n webhook and returns the response analysis.
     */
    public static function sendToN8n(array $logEntry, ?string $customWebhookUrl = null): string
    {
        $webhookUrl = !empty($customWebhookUrl) ? $customWebhookUrl : ($_ENV['N8N_WEBHOOK_URL'] ?? '');
        
        if (empty($webhookUrl)) {
            throw new Exception("A URL do webhook n8n não está configurada. Defina N8N_WEBHOOK_URL no .env ou informe-a no painel.");
        }

        $ch = curl_init($webhookUrl);
        if ($ch === false) {
            throw new Exception("Falha ao inicializar a requisição cURL.");
        }

        $payload = json_encode([
            'error_id' => $logEntry['id'],
            'message' => $logEntry['message'],
            'file' => $logEntry['file'],
            'line' => $logEntry['line'],
            'trace' => $logEntry['trace'],
            'request_method' => $logEntry['request_method'],
            'request_uri' => $logEntry['request_uri'],
            'timestamp' => $logEntry['timestamp']
        ]);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 seconds timeout
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Erro cURL ao conectar ao n8n: " . $error);
        }

        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $decoded = json_decode($response, true);
            if ($decoded !== null) {
                if (isset($decoded['analysis'])) {
                    return (string)$decoded['analysis'];
                }
                if (isset($decoded['cause'])) {
                    return (string)$decoded['cause'];
                }
                if (isset($decoded['response'])) {
                    return (string)$decoded['response'];
                }
                if (isset($decoded['output'])) {
                    return (string)$decoded['output'];
                }
                return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
            return (string)$response;
        } else {
            throw new Exception("O webhook n8n retornou código HTTP {$httpCode}. Resposta: " . ($response ?: 'Sem conteúdo'));
        }
    }
}
