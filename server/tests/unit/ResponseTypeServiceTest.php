<?php

use PHPUnit\Framework\TestCase;
use Service\ResponseTypeService;
use Slim\Psr7\Response;

class ResponseTypeServiceTest extends TestCase
{
    public function testSendResponseSetsJsonHeaderAndStatus(): void
    {
        $response = new Response();
        $result = ResponseTypeService::sendResponse($response, ['success' => true], 201);

        $this->assertSame(201, $result->getStatusCode());
        $this->assertSame(['application/json'], $result->getHeader('Content-Type'));
        $this->assertStringContainsString('"success":true', (string)$result->getBody());
    }
}
