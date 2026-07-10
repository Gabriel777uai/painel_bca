<?php

namespace Service;

use NumberFormatter;
use InvalidArgumentException;

class IntlService
{
    public function format(String $style, mixed $param, String $locale = "pt_br")
    {
        $index = [
            'sum' => fn(int $x, int $y) => $x + $y,
            'multiplication' => fn(int $x, int $y) => $x * $y,
            'division' => fn($x, $y) => $x / $y
        ];

        switch ($style) {
            case 'currency':
                $fmt = new NumberFormatter($locale, NumberFormatter::CURRENCY);
                $currency = ($locale === 'pt_br') ? 'BRL' : 'USD';
                return $fmt->formatCurrency((float) $param, $currency);
            case 'cpf':
                $cpf = preg_replace('/\D/', '', $param);

                return preg_replace(
                    '/(\d{3})(\d{3})(\d{3})(\d{2})/',
                    '$1.$2.$3-$4',
                    $cpf
                );
            case 'cnpj':
                $cnpj = preg_replace('/\D/', '', $param);

                return preg_replace(
                    '/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/',
                    '$1.$2.$3/$4-$5',
                    $cnpj
                );
            case 'phone':
                $telefone = preg_replace('/\D/', '', $param);

                $length = strlen($telefone);
                if ($length === 11) {
                    return preg_replace(
                        '/(\d{2})(\d{5})(\d{4})/',
                        '($1) $2-$3',
                        $telefone
                    );
                }

                if ($length === 10) {
                    return preg_replace(
                        '/(\d{2})(\d{4})(\d{4})/',
                        '($1) $2-$3',
                        $telefone
                    );
                }
                return $telefone;
                case "cep": 
                    $cep = preg_replace('/\D/', '', $param);
                    return preg_replace('/(\d{4})(\d{4})/', '$1-$2', $cep);
                case "emal_mask":
                    $email = preg_replace('/\a-zA-Z0-9]/', '', $param);

            default:
                throw new InvalidArgumentException("Invalid style: $style");
        }
    }
}
