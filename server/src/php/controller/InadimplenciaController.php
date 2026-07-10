<?php

namespace Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Service\ResponseTypeService;
use Exception;

class InadimplenciaController extends ResponseTypeService
{
    /**
     * Proxies requests to RecebeOnline API for payment default data (getInadimplencia)
     */
    public function getInadimplencia(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $parceiro = $queryParams['i_cdparceiro'] ?? '';
        $limite = $queryParams['d_limite'] ?? '';
        $final = $queryParams['d_final'] ?? '';
        $type = $queryParams['type'] ?? 'titulos';

        if (empty($parceiro) || empty($limite) || empty($final)) {
            return self::sendResponse($response, ["error" => "Parâmetros 'i_cdparceiro', 'd_limite' e 'd_final' são obrigatórios."], 400);
        }

        $token = "1xq1kka0";
        $endpoint = ($type === 'percentual') ? "vendedor_percentual_indice_inadimplencia" : "vendedor_titulos_vencidos";

        // Format dates from YYYY-MM-DD to DD/MM/YYYY if necessary
        if (strpos($limite, '-') !== false) {
            $limite = date('d/m/Y', strtotime($limite));
        }
        if (strpos($final, '-') !== false) {
            $final = date('d/m/Y', strtotime($final));
        }

        $url = "http://bca.recebeonline.com.br/web_services/{$endpoint}?token={$token}&i_cdparceiro=" . urlencode($parceiro) . "&d_limite=" . urlencode($limite) . "&d_final=" . urlencode($final);

        try {
            if (function_exists('curl_version')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $apiResponse = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($apiResponse === false) {
                    return self::sendResponse($response, ["error" => "Erro de conexão ao servidor de faturamento via cURL."], 502);
                }
                
                if ($httpCode !== 200) {
                    return self::sendResponse($response, ["error" => "O servidor de faturamento retornou o código HTTP {$httpCode}."], 502);
                }
                
                $data = json_decode($apiResponse, true);
                return self::sendResponse($response, $data ?: ["raw" => $apiResponse], 200);
            } else {
                $apiResponse = @file_get_contents($url);
                if ($apiResponse === false) {
                    return self::sendResponse($response, ["error" => "Erro de conexão ao servidor de faturamento via file_get_contents."], 502);
                }
                $data = json_decode($apiResponse, true);
                return self::sendResponse($response, $data ?: ["raw" => $apiResponse], 200);
            }
        } catch (Exception $e) {
            return self::sendResponse($response, ["error" => "Falha ao consultar inadimplência: " . $e->getMessage()], 500);
        }
    }
}
