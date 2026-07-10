<?php

use PHPUnit\Framework\TestCase;
use Service\ErrorLoggerService;

class ErrorLoggerServiceTest extends TestCase
{
    protected function setUp(): void
    {
        ErrorLoggerService::clearLogs();
    }

    protected function tearDown(): void
    {
        ErrorLoggerService::clearLogs();
    }

    public function testLogWritingAndReading(): void
    {
        $exception = new Exception("Teste de log do sistema", 418);
        ErrorLoggerService::log($exception);

        $logs = ErrorLoggerService::getLogs();
        $this->assertCount(1, $logs);

        $log = $logs[0];
        $this->assertNotEmpty($log['id']);
        $this->assertSame("Teste de log do sistema", $log['message']);
        $this->assertSame("pending", $log['status']);
        $this->assertNull($log['ai_analysis']);
        $this->assertSame(__FILE__, str_replace('/', DIRECTORY_SEPARATOR, $log['file']));
    }

    public function testLogUpdate(): void
    {
        $exception = new Exception("Erro de atualização");
        ErrorLoggerService::log($exception);

        $logs = ErrorLoggerService::getLogs();
        $this->assertCount(1, $logs);
        $id = $logs[0]['id'];

        $updateSuccess = ErrorLoggerService::updateLog($id, [
            'status' => 'analyzed',
            'ai_analysis' => 'Esta é uma análise de IA simulada.'
        ]);

        $this->assertTrue($updateSuccess);

        $logsAfter = ErrorLoggerService::getLogs();
        $this->assertCount(1, $logsAfter);
        $this->assertSame("analyzed", $logsAfter[0]['status']);
        $this->assertSame("Esta é uma análise de IA simulada.", $logsAfter[0]['ai_analysis']);
    }

    public function testLogDeletion(): void
    {
        ErrorLoggerService::log(new Exception("Erro 1"));
        ErrorLoggerService::log(new Exception("Erro 2"));

        $logs = ErrorLoggerService::getLogs();
        $this->assertCount(2, $logs);
        
        // logs are returned in reverse order, so index 0 is "Erro 2" and index 1 is "Erro 1"
        $idToDelete = $logs[0]['id'];

        $deleteSuccess = ErrorLoggerService::deleteLog($idToDelete);
        $this->assertTrue($deleteSuccess);

        $logsAfter = ErrorLoggerService::getLogs();
        $this->assertCount(1, $logsAfter);
        $this->assertSame("Erro 1", $logsAfter[0]['message']);
    }
}
