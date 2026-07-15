<?php

namespace Model;

use Config\RequestsDatabase;

class ClientModel
{
    private RequestsDatabase $db;

    public function __construct()
    {
        $this->db = $db ?? new RequestsDatabase();
    }

    /**
     * Count of new clients registered today (clientes_novos)
     */
    public function getNewClientsToday(?int $areaId = null): int
    {
        $params = [];
        $filter = '';
        if ($areaId !== null) {
            $filter = " AND i_cdarea = :areaId";
            $params[':areaId'] = $areaId;
        }
        $sql = "SELECT COUNT(*) FROM cliente c WHERE DATE(d_cadastro) = CURRENT_DATE{$filter}";
        return (int) $this->db->fetchColumn($sql, $params);
    }

    /**
     * Count of inactive clients in a specific area (painel_rca_clientes_inativos)
     */
    public function getInactiveClientsCount(int $areaId): int
    {
        $sql = "SELECT count(i_cdcliente) as total from cliente c 
                where d_ultcompra is not null 
                and i_cdarea = :areaId 
                and d_ultcompra <= CURRENT_DATE - INTERVAL '6 months'";
        return (int) $this->db->fetchColumn($sql, [':areaId' => $areaId]);
    }

    /**
     * List inactive clients in a specific area, with optional search and ordering (painel_rca_lista_clientes_inativos, pesquisa, ordenar_por)
     */
    public function getInactiveClientsList(int $areaId, ?string $search = null, string $orderBy = 'cidade'): array
    {
        // Whitelist order by fields to prevent SQL injection
        $allowedOrderColumns = [
            'cidade' => 'cidade',
            'area' => 'area',
            'data_ultima_compra' => 'data_ultima_compra',
            'nome_cliente' => 'nome_cliente',
            'c_classe' => 'c_classe',
            'cpd_cliente' => 'cpd_cliente',
            'nome_fantasia' => 'nome_fantasia'
        ];

        $order = isset($allowedOrderColumns[$orderBy]) ? $allowedOrderColumns[$orderBy] : 'cidade';

        $params = [':areaId' => $areaId];
        $searchClause = '';

        if (!empty($search)) {
            $searchClause = " and (c_nomecidade like upper(:search) 
                              or c_nome like upper(:search) 
                              or c_uf like upper(:search) 
                              or c_cnpj like :search 
                              or c_cpf like :search)";
            $params[':search'] = "%{$search}%";
        }

        $sql = "SELECT
                    c.i_cdclasse,
                    c_classe, 
                    c_cnpj as cnpj,
                    c_cpf as cpf,
                    c_nome as nome_cliente,
                    c_nomefantas as nome_fantasia,
                    i_cdarea as area,
                    i_cdcliente as cpd_cliente,
                    c_nomecidade as cidade,
                    date(d_ultcompra) as data_ultima_compra, 
                    cd.c_uf 
                FROM cliente c
                JOIN classe cl on cl.i_cdclasse = c.i_cdclasse
                JOIN cidade cd on cd.i_cdcidade = c.i_cdcidade
                WHERE date(d_ultcompra) is not null 
                AND i_cdarea = :areaId 
                AND d_ultcompra <= CURRENT_DATE - INTERVAL '6 months'
                {$searchClause}
                ORDER BY {$order} ASC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Count of clients by class in a specific area (puxar_dados_de_clientes_classes)
     */
    public function getClientClassesCount(int $areaId): array
    {
        $sql = "SELECT
                    SUM(CASE WHEN i_cdclasse = 1 THEN 1 ELSE 0 END) AS novos,
                    SUM(CASE WHEN i_cdclasse = 2 THEN 1 ELSE 0 END) AS ativos,
                    SUM(CASE WHEN i_cdclasse = 4 THEN 1 ELSE 0 END) AS baixados,
                    SUM(CASE WHEN i_cdclasse = 5 THEN 1 ELSE 0 END) AS naohabilitados,
                    SUM(CASE WHEN i_cdclasse = 6 THEN 1 ELSE 0 END) AS cpfinativos
                FROM cliente c
                WHERE i_cdarea = :areaId";

        $result = $this->db->fetch($sql, [':areaId' => $areaId]);
        return $result ?: [
            'novos' => 0,
            'ativos' => 0,
            'baixados' => 0,
            'naohabilitados' => 0,
            'cpfinativos' => 0
        ];
    }

    /**
     * Get registration counts month-by-month for current year (clientes_ano_atual / clientes_ano_atual_painelRCA)
     */
    public function getClientsCurrentYearCount(?int $areaId = null): array
    {
        $params = [];
        $areaFilter = '';
        if ($areaId !== null) {
            $areaFilter = " and i_cdarea = :areaId";
            $params[':areaId'] = $areaId;
        }

        $sql = "SELECT
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 1 THEN 1 END) AS janeiro,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 2 THEN 1 END) AS fevereiro,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 3 THEN 1 END) AS marco,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 4 THEN 1 END) AS abril,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 5 THEN 1 END) AS maio,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 6 THEN 1 END) AS junho,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 7 THEN 1 END) AS julho,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 8 THEN 1 END) AS agosto,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 9 THEN 1 END) AS setembro,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 10 THEN 1 END) AS outubro,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 11 THEN 1 END) AS novembro,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 12 THEN 1 END) AS dezembro
                FROM cliente 
                WHERE extract(year from d_cadastro) = extract(YEAR from CURRENT_DATE)
                {$areaFilter}";

        return $this->db->fetch($sql, $params) ?: [];
    }

    /**
     * Get registration counts month-by-month for last year (clientes_ano_passado / clientes_ano_passado_painelRCA)
     */
    public function getClientsLastYearCount(?int $areaId = null): array
    {
        $params = [];
        $areaFilter = '';
        if ($areaId !== null) {
            $areaFilter = " and i_cdarea = :areaId";
            $params[':areaId'] = $areaId;
        }

        $sql = "SELECT
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 1 THEN 1 END) AS janeiro,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 2 THEN 1 END) AS fevereiro,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 3 THEN 1 END) AS marco,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 4 THEN 1 END) AS abril,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 5 THEN 1 END) AS maio,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 6 THEN 1 END) AS junho,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 7 THEN 1 END) AS julho,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 8 THEN 1 END) AS agosto,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 9 THEN 1 END) AS setembro,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 10 THEN 1 END) AS outubro,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 11 THEN 1 END) AS novembro,
                    COUNT(CASE WHEN EXTRACT(MONTH FROM d_cadastro) = 12 THEN 1 END) AS dezembro
                FROM cliente 
                WHERE extract(year from d_cadastro) = extract(YEAR from CURRENT_DATE) - 1
                {$areaFilter}";

        return $this->db->fetch($sql, $params) ?: [];
    }

    /**
     * Get general report data for a specific area, class, city, and dates (used in relatorio_clientes.php)
     */
    public function getReportClientsList(int $areaId, ?int $classId = null, ?int $cityId = null, ?string $dateStart = null, ?string $dateEnd = null, string $orderBy = 'cidade'): array
    {
        $allowedOrderColumns = [
            'cidade' => 'cidade',
            'nome_fantasia' => 'nome_fantasia',
            'nome_cliente' => 'nome_cliente',
            'cpd_cliente' => 'cpd_cliente'
        ];
        $order = isset($allowedOrderColumns[$orderBy]) ? $allowedOrderColumns[$orderBy] : 'cidade';

        $params = [':areaId' => $areaId];
        $clauses = "where i_cdarea = :areaId";

        if ($classId !== null && $classId > 0) {
            // Remap values as in old code:
            // case 2: class 2, case 3: class 3, case 4: class 1, case 5: class 4, case 6: class 6, case 7: class 5
            $mappedClass = null;
            switch ($classId) {
                case 2:
                    $mappedClass = 2;
                    break;
                case 3:
                    $mappedClass = 3;
                    break;
                case 4:
                    $mappedClass = 1;
                    break;
                case 5:
                    $mappedClass = 4;
                    break;
                case 6:
                    $mappedClass = 6;
                    break;
                case 7:
                    $mappedClass = 5;
                    break;
            }
            if ($mappedClass !== null) {
                $clauses .= " and c.i_cdclasse = :classId";
                $params[':classId'] = $mappedClass;
            }
        }

        if ($cityId !== null && $cityId > 0) {
            $clauses .= " and c.i_cdcidade = :cityId";
            $params[':cityId'] = $cityId;
        }

        if (!empty($dateStart)) {
            if (!empty($dateEnd)) {
                $clauses .= " and c.d_cadastro between date(:dateStart) and date(:dateEnd)";
                $params[':dateStart'] = $dateStart;
                $params[':dateEnd'] = $dateEnd;
            } else {
                $clauses .= " and c.d_cadastro = date(:dateStart)";
                $params[':dateStart'] = $dateStart;
            }
        }

        $sql = "SELECT 
                    c.i_cdclasse, 
                    c_classe, 
                    c_cnpj as cnpj, 
                    c_cpf as cpf, 
                    c_nome as nome_cliente, 
                    c_nomefantas as nome_fantasia, 
                    i_cdarea as area, 
                    i_cdcliente as cpd_cliente, 
                    c_nomecidade as cidade, 
                    date(d_ultcompra) as data_ultima_compra, 
                    cd.c_uf 
                FROM cliente c 
                JOIN classe cl on cl.i_cdclasse = c.i_cdclasse 
                JOIN cidade cd on cd.i_cdcidade = c.i_cdcidade 
                {$clauses} 
                ORDER BY {$order} ASC";

        return $this->db->fetchAll($sql, $params);
    }
}
