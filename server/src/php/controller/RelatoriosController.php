<?php

namespace Controller;

use Model\ClientModel;
use Model\AreaModel;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Service\ResponseTypeService;
use Mpdf\Mpdf;
use DateTime;
use Exception;

use Config\RequestsDatabase;

class RelatoriosController extends ResponseTypeService
{
    private ClientModel $clientModel;
    private AreaModel $areaModel;
    private RequestsDatabase $db;

    public function __construct()
    {
        $this->clientModel = $clientModel ?? new ClientModel();
        $this->areaModel = $areaModel ?? new AreaModel();
        $this->db = $db ?? new RequestsDatabase();
    }

    /**
     * Fetch all cities
     */
    public function listarCidades(Request $request, Response $response): Response
    {
        try {
            $db = new \Config\RequestsDatabase();
            $cidades = $db->fetchAll("SELECT i_cdcidade, c_nomecidade FROM cidade ORDER BY c_nomecidade ASC");
            return self::sendResponse($response, $cidades ?: [], 200);
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generates general clients report (relatorio_clientes.php)
     */
    public function gerarRelatorioClientes(Request $request, Response $response): Response
    {
        $params = array_merge((array)$request->getParsedBody(), $request->getQueryParams());

        $areaRep = isset($params['area_rep']) ? (int)$params['area_rep'] : null;
        if ($areaRep === null) {
            return self::sendResponse($response, ['error' => 'Parâmetro area_rep é obrigatório'], 400);
        }

        $cidadeRep = !empty($params['cidade_rep']) ? (int)$params['cidade_rep'] : null;
        $ordenRelatorio = isset($params['orden_relatorio']) ? (int)$params['orden_relatorio'] : 1;
        $classCliente = isset($params['class_cliente']) ? (int)$params['class_cliente'] : null;
        $dateStart = !empty($params['date_informada']) ? $params['date_informada'] : null;
        $dateEnd = !empty($params['secontDate']) ? $params['secontDate'] : null;

        // Map sorting field
        $orderBy = 'cidade';
        switch ($ordenRelatorio) {
            case 1:
                $orderBy = 'cidade';
                break;
            case 2:
                $orderBy = 'nome_fantasia';
                break;
            case 3:
                $orderBy = 'nome_cliente';
                break;
            case 4:
                $orderBy = 'cpd_cliente';
                break;
        }

        try {
            ini_set('memory_limit', '512M');
            set_time_limit(300);

            $clients = $this->clientModel->getReportClientsList($areaRep, $classCliente, $cidadeRep, $dateStart, $dateEnd, $orderBy);
            $areaName = $this->areaModel->getAreaName($areaRep) ?: '';

            $html = $this->renderTemplate("Lista de clientes da área {$areaRep} - {$areaName}", $clients);

            return $this->generatePdfResponse($response, $html, "relatorio_clientes_area_{$areaRep}.pdf");
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => 'Falha ao gerar relatório: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Generates inactive clients report (relatorio_inativo.php)
     */
    public function gerarRelatorioInativos(Request $request, Response $response): Response
    {
        $params = array_merge((array)$request->getParsedBody(), $request->getQueryParams());

        $areaId = isset($params['area']) ? (int)$params['area'] : null;
        if ($areaId === null) {
            return self::sendResponse($response, ['error' => 'Parâmetro area é obrigatório'], 400);
        }

        $orderBy = isset($params['ordem']) ? $params['ordem'] : 'cidade';

        try {
            ini_set('memory_limit', '512M');
            set_time_limit(300);

            $clients = $this->clientModel->getInactiveClientsList($areaId, null, $orderBy);
            $areaName = $this->areaModel->getAreaName($areaId) ?: '';

            $html = $this->renderTemplate("Lista de clientes inativos da área {$areaId} - {$areaName}", $clients);

            return $this->generatePdfResponse($response, $html, "relatorio_inativos_area_{$areaId}.pdf");
        } catch (Exception $e) {
            return self::sendResponse($response, ['error' => 'Falha ao gerar relatório: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Helper to render HTML template for the PDF report
     */
    private function renderTemplate(string $title, array $clients): string
    {
        $rowsHtml = '';
        foreach ($clients as $row) {
            $cpd = $row['cpd_cliente'] ?? '';
            $nome = $row['nome_cliente'] ?? '';
            $fantasia = $row['nome_fantasia'] ?? '';
            $cnpj = $row['cnpj'] ?? '';
            $cpf = $row['cpf'] ?? '';
            $cidade = $row['cidade'] ?? '';
            $uf = $row['c_uf'] ?? '';
            $classe = $row['c_classe'] ?? '';
            $ultCompra = $row['data_ultima_compra'] ?? null;

            $dataFormatted = 'N/A';
            if (!empty($ultCompra)) {
                $dateReal = DateTime::createFromFormat('Y-m-d', $ultCompra);
                $dataFormatted = $dateReal ? $dateReal->format('d/m/Y') : 'N/A';
            }
            
            $cnpjOrCpf = !empty($cnpj) ? $cnpj : (!empty($cpf) ? $cpf : 'N/A');
            $cleanName = mb_strimwidth($nome, 0, 48, '...');

            $rowsHtml .= "<tr>
                <td style='font-weight: bold; color: #4A5568;'>{$cpd}</td>
                <td><strong>{$cleanName}</strong>" . (!empty($fantasia) ? "<br><span style='font-size: 7.5pt; color: #718096;'>{$fantasia}</span>" : "") . "</td>
                <td>{$cnpjOrCpf}</td>
                <td>{$cidade}</td>
                <td style='text-align: center;'><span class='badge-uf'>{$uf}</span></td>
                <td style='text-align: center;'><span class='badge-classe'>{$classe}</span></td>
                <td>{$dataFormatted}</td>
            </tr>";
        }

        $totalClients = count($clients);
        $statusLabel = (strpos(strtolower($title), 'inativo') !== false) ? 'Inativos (> 6 Meses)' : 'Ativos na Carteira';

        return "<html lang='pt-br'>
        <head>
            <meta charset='utf-8'>
            <title>Relatório Executivo</title>
            <style>
                @page {
                    header: html_reportHeader;
                    footer: html_reportFooter;
                    margin-top: 38mm;
                    margin-bottom: 20mm;
                    margin-left: 10mm;
                    margin-right: 10mm;
                }
                body {
                    font-family: 'helvetica', 'arial', sans-serif;
                    color: #2D3748;
                    font-size: 8.5pt;
                }
                .header-table {
                    width: 100%;
                    border-bottom: 3px solid #0fa40b;
                    padding-bottom: 10px;
                    margin-bottom: 20px;
                }
                .header-title {
                    font-size: 18pt;
                    font-weight: bold;
                    color: #0fa40b;
                }
                .header-subtitle {
                    font-size: 9pt;
                    color: #718096;
                    margin-top: 2px;
                }
                .header-meta {
                    text-align: right;
                    font-size: 8.5pt;
                    color: #4A5568;
                    line-height: 1.4;
                }
                table.data-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }
                table.data-table th {
                    background-color: #1A202C;
                    color: #FFFFFF;
                    font-weight: bold;
                    text-transform: uppercase;
                    font-size: 7.5pt;
                    padding: 8px 10px;
                    border: 1px solid #1A202C;
                    text-align: left;
                }
                table.data-table td {
                    padding: 8px 10px;
                    border-bottom: 1px solid #E2E8F0;
                    font-size: 8pt;
                    vertical-align: middle;
                }
                table.data-table tr:nth-child(even) td {
                    background-color: #F8FAFC;
                }
                .badge-uf {
                    background-color: #EDF2F7;
                    color: #4A5568;
                    padding: 2px 5px;
                    border-radius: 4px;
                    font-weight: bold;
                    font-size: 7pt;
                }
                .badge-classe {
                    background-color: #EBF8FF;
                    color: #2B6CB0;
                    padding: 2px 5px;
                    border-radius: 4px;
                    font-weight: bold;
                    font-size: 7pt;
                }
            </style>
        </head>
        <body>

            <!-- Header definition for @page -->
            <htmlpageheader name=\"reportHeader\">
                <table class=\"header-table\" style=\"width: 100%; border: none;\">
                    <tr>
                        <td style=\"width: 60%; border: none; padding: 0;\">
                            <div class=\"header-title\">Painel BRC <span style=\"color: #068238;\">Analytics</span></div>
                            <div class=\"header-subtitle\">Relatório Executivo de Carteira de Clientes</div>
                        </td>
                        <td style=\"width: 40%; text-align: right; border: none; padding: 0;\" class=\"header-meta\">
                            <strong>Gerado em:</strong> " . date('d/m/Y H:i') . "<br>
                            <strong>Filtro:</strong> {$title}
                        </td>
                    </tr>
                </table>
            </htmlpageheader>
            
            <htmlpagefooter name=\"reportFooter\">
                <table style=\"width: 100%; border: none; border-top: 1px solid #E2E8F0; padding-top: 8px;\">
                    <tr>
                        <td style=\"width: 33%; border: none; font-size: 7.5pt; color: #A0AEC0; padding: 0;\">
                            Brasil Componentes Automotivos
                        </td>
                        <td style=\"width: 34%; text-align: center; border: none; font-size: 7.5pt; color: #A0AEC0; padding: 0;\">
                            Confidencial - Uso Interno
                        </td>
                        <td style=\"width: 33%; text-align: right; border: none; font-size: 7.5pt; color: #A0AEC0; padding: 0;\">
                            Página {PAGENO} de {nbpg}
                        </td>
                    </tr>
                </table>
            </htmlpagefooter>

            <!-- KPI Summary Section -->
            <table style=\"width: 100%; margin-bottom: 15px; border: none; background: #F8FAFC; padding: 12px; border-radius: 8px; border: 1px solid #E2E8F0;\">
                <tr>
                    <td style=\"border: none; width: 33%; padding: 0;\">
                        <div style=\"font-size: 7.5pt; color: #718096; text-transform: uppercase; font-weight: bold; margin-bottom: 3px;\">Total de Clientes</div>
                        <div style=\"font-size: 15pt; font-weight: bold; color: #0fa40b;\">{$totalClients}</div>
                    </td>
                    <td style=\"border: none; width: 33%; border-left: 1px solid #E2E8F0; padding: 0 0 0 15px;\">
                        <div style=\"font-size: 7.5pt; color: #718096; text-transform: uppercase; font-weight: bold; margin-bottom: 3px;\">Status da Carteira</div>
                        <div style=\"font-size: 11pt; font-weight: bold; color: #2D3748;\">{$statusLabel}</div>
                    </td>
                    <td style=\"border: none; width: 33%; border-left: 1px solid #E2E8F0; padding: 0 0 0 15px;\">
                        <div style=\"font-size: 7.5pt; color: #718096; text-transform: uppercase; font-weight: bold; margin-bottom: 3px;\">Documento</div>
                        <div style=\"font-size: 11pt; font-weight: bold; color: #2D3748;\">PDF Gerado Via Sistema</div>
                    </td>
                </tr>
            </table>

            <table class=\"data-table\">
                <thead>
                    <tr>
                        <th style=\"width: 8%;\">Código</th>
                        <th style=\"width: 38%;\">Nome Cliente / Razão Social</th>
                        <th style=\"width: 16%;\">CNPJ / CPF</th>
                        <th style=\"width: 18%;\">Cidade</th>
                        <th style=\"width: 6%; text-align: center;\">UF</th>
                        <th style=\"width: 6%; text-align: center;\">Classe</th>
                        <th style=\"width: 8%;\">Últ. Compra</th>
                    </tr>
                </thead>
                <tbody>
                    {$rowsHtml}
                </tbody>
            </table>

        </body>
        </html>";
    }

    /**
     * Helper to instantiate mPDF, write HTML, and write PDF to response stream
     */
    private function generatePdfResponse(Response $response, string $html, string $filename): Response
    {
        // Suppress any PHP warnings/notices/deprecations from corrupting the PDF binary stream
        ini_set('display_errors', '0');
        error_reporting(0);

        $tempDir = __DIR__ . '/../../../../server/src/php/logs';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'default_font' => 'helvetica',
            'tempDir' => $tempDir,
            'ignore_invalid_utf8' => true,
            'showImageErrors' => true,
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 38,
            'margin_bottom' => 20,
            'margin_header' => 10,
            'margin_footer' => 10,
            'setAutoTopMargin' => false,
            'setAutoBottomMargin' => false,
        ]);

        if (ob_get_length() > 0) {
            ob_clean();
        }
        $mpdf->WriteHTML($html);

        $pdfContent = $mpdf->Output('', 'S');
        $response->getBody()->write($pdfContent);

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->withStatus(200);
    }
}
