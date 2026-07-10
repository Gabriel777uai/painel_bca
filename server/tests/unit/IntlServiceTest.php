<?php

use PHPUnit\Framework\TestCase;
use Service\IntlService;

class IntlServiceTest extends TestCase
{
    private IntlService $intlService;

    protected function setUp(): void
    {
        $this->intlService = new IntlService();
    }

    public function testFormatCurrency(): void
    {
        $result = $this->intlService->format('currency', 1234.56);
        $this->assertStringContainsString('R$', $result);
    }

    public function testFormatCpf(): void
    {
        $result = $this->intlService->format('cpf', '12345678901');
        $this->assertSame('123.456.789-01', $result);
    }

    public function testFormatCnpj(): void
    {
        $result = $this->intlService->format('cnpj', '12345678000199');
        $this->assertSame('12.345.678/0001-99', $result);
    }

    public function testFormatPhone11Digits(): void
    {
        $result = $this->intlService->format('phone', '118765004321');
        $this->assertSame('(11) 87650-4321', $result);
    }

    public function testFormatCep(): void
    {
        $result = $this->intlService->format('cep', '88888888');
        $this->assertSame('8888-8888', $result);
    }

    public function testInvalidStyleThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->intlService->format('invalid-style', 'value');
    }
}
