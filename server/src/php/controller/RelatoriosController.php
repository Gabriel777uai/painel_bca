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

    public function __construct(ClientModel $clientModel = null, AreaModel $areaModel = null, RequestsDatabase $db = null)
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
            $dateReal = DateTime::createFromFormat('Y-m-d', $row['data_ultima_compra']);
            $dataFormatted = $dateReal ? $dateReal->format('d/m/Y') : 'N/A';

            $rowsHtml .= "<tr>
                <td>{$row['cpd_cliente']}</td>
                <td>{$row['nome_cliente']}</td>
                <td>{$row['cnpj']}</td>
                <td>{$row['cpf']}</td>
                <td>{$row['cidade']}</td>
                <td>{$row['c_classe']}</td>
                <td>{$row['c_uf']}</td>
                <td>{$dataFormatted}</td>
            </tr>";
        }

        return "<html lang='pt-br'>
        <head>
            <meta charset='utf-8'>
            <title>Relatório</title>
            <style>
                body { font-family: 'dejavusans', sans-serif; font-size: 10pt; color: #333; }
                h1 { font-size: 16pt; margin-bottom: 20px; color: #003399; text-align: center; border-bottom: 2px solid #003399; padding-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; font-size: 9pt; }
                th { background-color: #f2f2f2; color: #000; font-weight: bold; }
                tr:nth-child(even) { background-color: #fafdff; }
            </style>
        </head>
        <body>
            <h1>{$title}</h1>
            <table>
                <thead>
                    <tr>
                        <th style='width: 8%;'>Código</th>
                        <th style='width: 32%;'>Nome Cliente</th>
                        <th style='width: 14%;'>CNPJ</th>
                        <th style='width: 12%;'>CPF</th>
                        <th style='width: 14%;'>Cidade</th>
                        <th style='width: 8%;'>Classe</th>
                        <th style='width: 4%;'>UF</th>
                        <th style='width: 8%;'>Últ. Compra</th>
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
        $tempDir = __DIR__ . '/../../../../server/src/php/logs';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'default_font' => 'dejavusans',
            'tempDir' => $tempDir,
            'ignore_invalid_utf8' => true,
            'showImageErrors' => true,
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_header' => 5,
            'margin_footer' => 5,
            'setAutoTopMargin' => 'stretch',
            'setAutoBottomMargin' => 'stretch',
        ]);

        ob_clean();
        $mpdf->WriteHTML($html);

        $pdfContent = $mpdf->Output('', 'S');
        $response->getBody()->write($pdfContent);

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->withStatus(200);
    }
}
