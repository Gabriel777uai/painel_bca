<?php

namespace Model;

use Config\RequestsDatabase;

class SalesModel
{
    private RequestsDatabase $db;

    public function __construct(RequestsDatabase $db = null)
    {
        $this->db = $db ?? new RequestsDatabase();
    }

    /**
     * Total orders created today (pedidos_dia)
     */
    public function getTodayOrdersCount(): int
    {
        $sql = "SELECT count(i_nrpedido) from pedidovenda p where DATE(d_cadastro) = CURRENT_DATE and f_cancelado = 'N'";
        return (int) $this->db->fetchColumn($sql);
    }

    /**
     * Total orders registered this month (pedidos_mensais)
     */
    public function getMonthOrdersCount(): int
    {
        $sql = "SELECT count(i_nrpedido) from pedidovenda p where d_cadastro between date_trunc('month', CURRENT_DATE) and CURRENT_DATE and f_cancelado = 'N'";
        return (int) $this->db->fetchColumn($sql);
    }

    /**
     * Top 10 sellers of the week (melhores_vendadeores_semana)
     */
    public function getBestSellersOfWeek(): array
    {
        $sql = "SELECT
                    p.i_cdarea AS area_vendedor,
                    sum(n_vlrfaturamento) AS valor_digitado,
                    count(i_nrpedido) AS quantidade_pedidos,
                    c_nomearea
                from pedidovenda p 
                join area on area.i_cdarea = p.i_cdarea
                where f_cancelado = 'N' and d_cadastro between date_trunc('week', date(CURRENT_DATE)) and CURRENT_DATE + interval '1 month' - interval '1 second'
                group by p.i_cdarea, area.c_nomearea
                order by valor_digitado desc limit 10";
        return $this->db->fetchAll($sql);
    }

    /**
     * Monthly faturamento for the current year (result_mensal_tot)
     */
    public function getMonthlySalesTotal(): array
    {
        $sql = "SELECT SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 1 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_01
                , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 2 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_02
                , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 3 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_03
                , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 4 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_04
                , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 5 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_05
                , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 6 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_06
                , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 7 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_07
                , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 8 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_08
                , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 9 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_09
                , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 10 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_10
                , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 11 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_11
                , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 12 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_12
                FROM dados.pedidovenda p
                where p.f_cancelado = 'N' and date_trunc('year', p.d_cadastro) = date_trunc('year', CURRENT_DATE)
                GROUP BY EXTRACT(YEAR FROM p.d_cadastro)";

        return $this->db->fetch($sql) ?: [];
    }

    /**
     * Orders count and value total grouped by supervisor for current month (pedidos_por_supervisor)
     */
    public function getSupervisorOrdersTotal(): array
    {
        $sql = "SELECT distinct
                    vendedor.i_cdvendedor AS codigo_supervisor,
                    c_nome AS nome_supervisor,
                    COUNT(i_nrpedido) as quantidade_pedidos,
                    sum(n_vlrdigitado) AS valor_digitado
                from
                    vendedor
                JOIN 
                    area ON vendedor.i_cdvendedor = area.i_cdsupervisor
                join
                    pedidovenda p on area.i_cdarea = p.i_cdarea
                where
                    vendedor.f_situacao = 'A'
                    and p.f_cancelado = 'N'
                    and p.d_cadastro between date_trunc('month', date(CURRENT_DATE)) and date(CURRENT_DATE) + interval '1 month' - interval '1 second'
                group by vendedor.i_cdvendedor, vendedor.c_nome order by valor_digitado desc";

        return $this->db->fetchAll($sql);
    }

    /**
     * Daily sales count & sum for a given week (semanal)
     */
    public function getWeeklySales(int $areaId, string $date): array
    {
        $sql = "SELECT SUM(CASE WHEN EXTRACT(DOW FROM p.d_cadastro) = 1 then 1 else 0 end) as count_pedido_02
        , SUM(CASE WHEN EXTRACT(DOW FROM p.d_cadastro) = 1 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_02
        , SUM(CASE WHEN EXTRACT(DOW FROM p.d_cadastro) = 2 then 1 else 0 end) as count_pedido_03
        , SUM(CASE WHEN EXTRACT(DOW FROM p.d_cadastro) = 2 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_03
        , SUM(CASE WHEN EXTRACT(DOW FROM p.d_cadastro) = 3 then 1 else 0 end) as count_pedido_04
        , SUM(CASE WHEN EXTRACT(DOW FROM p.d_cadastro) = 3 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_04
        , SUM(CASE WHEN EXTRACT(DOW FROM p.d_cadastro) = 4 then 1 else 0 end) as count_pedido_05
        , SUM(CASE WHEN EXTRACT(DOW FROM p.d_cadastro) = 4 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_05
        , SUM(CASE WHEN EXTRACT(DOW FROM p.d_cadastro) = 5 then 1 else 0 end) as count_pedido_06
        , SUM(CASE WHEN EXTRACT(DOW FROM p.d_cadastro) = 5 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_06
        , SUM(CASE WHEN EXTRACT(DOW FROM p.d_cadastro) = 6 then 1 else 0 end) as count_pedido_07
        , SUM(CASE WHEN EXTRACT(DOW FROM p.d_cadastro) = 6 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_07
        , SUM(CASE WHEN EXTRACT(DOW FROM p.d_cadastro) = 0 then 1 else 0 end) as count_pedido_01
        , SUM(CASE WHEN EXTRACT(DOW FROM p.d_cadastro) = 0 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_01
        FROM dados.pedidovenda p
        where p.f_cancelado = 'N' and p.i_cdarea = :areaId and date_trunc('week', p.d_cadastro) = date_trunc('week', DATE(:dateVal))";

        return $this->db->fetch($sql, [':areaId' => $areaId, ':dateVal' => $date]) ?: [];
    }

    /**
     * Monthly faturamento & count breakdown for a year (mensal)
     */
    public function getMonthlySales(int $areaId, string $date): array
    {
        $sql = "SELECT 
        SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 1 then 1 else 0 end) as count_pedido_01
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 1 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_01
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 2 then 1 else 0 end) as count_pedido_02
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 2 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_02
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 3 then 1 else 0 end) as count_pedido_03
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 3 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_03
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 4 then 1 else 0 end) as count_pedido_04
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 4 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_04
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 5 then 1 else 0 end) as count_pedido_05
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 5 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_05
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 6 then 1 else 0 end) as count_pedido_06
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 6 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_06
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 7 then 1 else 0 end) as count_pedido_07
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 7 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_07
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 8 then 1 else 0 end) as count_pedido_08
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 8 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_08
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 9 then 1 else 0 end) as count_pedido_09
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 9 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_09
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 10 then 1 else 0 end) as count_pedido_10
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 10 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_10
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 11 then 1 else 0 end) as count_pedido_11
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 11 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_11
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 12 then 1 else 0 end) as count_pedido_12
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 12 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_12
        FROM dados.pedidovenda p
        where p.f_cancelado = 'N' and p.i_cdarea = :areaId and date_trunc('year', p.d_cadastro) = date_trunc('year', DATE(:dateVal))
        GROUP BY EXTRACT(YEAR FROM p.d_cadastro)";

        return $this->db->fetch($sql, [':areaId' => $areaId, ':dateVal' => $date]) ?: [];
    }

    /**
     * Monthly breakdown between two dates (mensal_por_periodo)
     */
    public function getMonthlySalesByPeriod(int $areaId, string $dateStart, string $dateEnd): array
    {
        $sql = "SELECT 
        SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 1 then 1 else 0 end) as count_pedido_01
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 1 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_01
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 2 then 1 else 0 end) as count_pedido_02
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 2 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_02
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 3 then 1 else 0 end) as count_pedido_03
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 3 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_03
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 4 then 1 else 0 end) as count_pedido_04
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 4 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_04
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 5 then 1 else 0 end) as count_pedido_05
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 5 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_05
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 6 then 1 else 0 end) as count_pedido_06
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 6 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_06
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 7 then 1 else 0 end) as count_pedido_07
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 7 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_07
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 8 then 1 else 0 end) as count_pedido_08
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 8 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_08
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 9 then 1 else 0 end) as count_pedido_09
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 9 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_09
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 10 then 1 else 0 end) as count_pedido_10
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 10 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_10
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 11 then 1 else 0 end) as count_pedido_11
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 11 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_11
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 12 then 1 else 0 end) as count_pedido_12
        , SUM(CASE WHEN EXTRACT(MONTH FROM p.d_cadastro) = 12 then round(p.n_vlrdigitado, 0) else 0 end) as soma_pedido_digitado_12
        FROM dados.pedidovenda p
        where p.f_cancelado = 'N' and p.i_cdarea = :areaId and p.d_cadastro between date(:dateStart) and date(:dateEnd)
        GROUP BY EXTRACT(YEAR FROM p.d_cadastro)";

        return $this->db->fetch($sql, [':areaId' => $areaId, ':dateStart' => $dateStart, ':dateEnd' => $dateEnd]) ?: [];
    }

    /**
     * Team sales up to date under a supervisor (select_geral_equipe)
     */
    public function getTeamSalesByDate(int $supervisorId, string $date): array
    {
        $sql = "SELECT
                    pv.i_cdarea AS area_vendedor,
                    sum(n_vlrdigitado) AS valor_digitado,
                    count(i_nrpedido) AS quantidade_pedidos,
                    c_nomearea
                FROM
                    pedidovenda pv
                JOIN
                    area_vendedor av ON pv.i_cdarea = av.i_cdarea
                JOIN
                    area ON area.i_cdarea = av.i_cdarea
                WHERE
                    d_cadastro BETWEEN date_trunc('year', DATE(:dateVal)) AND DATE(:dateVal) 
                    AND av.i_cdvendedor = :supervisorId
                    AND f_cancelado = 'N'
                GROUP BY
                    area.c_nomearea, pv.i_cdarea";

        return $this->db->fetchAll($sql, [':supervisorId' => $supervisorId, ':dateVal' => $date]);
    }

    /**
     * Team faturamento in date range under a supervisor (select_geral_equipe_data)
     */
    public function getTeamSalesByDateRange(int $supervisorId, string $dateStart, string $dateEnd): array
    {
        $sql = "SELECT
                    pv.i_cdarea AS area_vendedor,
                    sum(n_vlrfaturamento) AS valor_digitado,
                    count(i_nrpedido) AS quantidade_pedidos,
                    c_nomearea
                FROM
                    pedidovenda pv
                JOIN
                    area_vendedor av ON pv.i_cdarea = av.i_cdarea
                JOIN
                    area ON area.i_cdarea = av.i_cdarea
                WHERE
                    d_faturamento BETWEEN DATE(:dateStart) AND DATE(:dateEnd) 
                    AND av.i_cdvendedor = :supervisorId
                    AND f_cancelado = 'N'
                    AND f_faturou = 'S'
                GROUP BY
                    area.c_nomearea, pv.i_cdarea";

        return $this->db->fetchAll($sql, [':supervisorId' => $supervisorId, ':dateStart' => $dateStart, ':dateEnd' => $dateEnd]);
    }

    /**
     * Paginated pending/non-invoiced orders (lista_pedidos)
     */
    public function getPendingOrders(int $page = 1, int $limit = 15): array
    {
        $offset = ($page - 1) * $limit;
        $sql = "SELECT c_nomearea, i_nrpedido, date(d_cadastro) as d_cadastro, p.i_cdarea , n_vlrdigitado , n_vlrseparado 
                from pedidovenda p 
                join area on area.i_cdarea =  p.i_cdarea 
                where d_cadastro between date_trunc('month', CURRENT_DATE) and CURRENT_DATE 
                and f_cancelado = 'N' and f_faturou = 'N' 
                order by d_cadastro desc 
                offset :offset limit :limit";

        return $this->db->fetchAll($sql, [':offset' => $offset, ':limit' => $limit]);
    }

    /**
     * Total count of pending orders in month (for link/page calculation)
     */
    public function getPendingOrdersCount(): int
    {
        $sql = "SELECT count(i_nrpedido) as conta 
                from pedidovenda p 
                where d_cadastro between date_trunc('month', CURRENT_DATE) and CURRENT_DATE 
                and f_cancelado = 'N' and f_faturou = 'N'";
        return (int) $this->db->fetchColumn($sql);
    }

    /**
     * Search/filter pending orders by date range and area (buscar)
     */
    public function getPendingOrdersSearch(string $dateStart, string $dateEnd, int $areaId): array
    {
        $sql = "SELECT c_nomearea, i_nrpedido, date(d_cadastro) as d_cadastro, p.i_cdarea , n_vlrdigitado , n_vlrseparado 
                from pedidovenda p 
                join area on area.i_cdarea =  p.i_cdarea 
                where d_cadastro between date(:dateStart) and date(:dateEnd) 
                and f_cancelado = 'N' and f_faturou = 'N' 
                and p.i_cdarea = :areaId 
                order by d_cadastro desc";

        return $this->db->fetchAll($sql, [':dateStart' => $dateStart, ':dateEnd' => $dateEnd, ':areaId' => $areaId]);
    }

    /**
     * Today's orders for a specific area (painel_rca_pedidos_diac)
     */
    public function getPainelRcaPedidosHoje(int $areaId): int
    {
        $sql = "SELECT COUNT(i_nrpedido) FROM pedidovenda WHERE date(d_cadastro) = CURRENT_DATE AND f_cancelado = 'N' and i_cdarea = :areaId";
        return (int) $this->db->fetchColumn($sql, [':areaId' => $areaId]);
    }

    /**
     * Monthly orders for a specific area (painel_rca_pedidos_mensal)
     */
    public function getPainelRcaPedidosMensal(int $areaId): int
    {
        $sql = "SELECT COUNT(i_nrpedido) FROM pedidovenda WHERE date(d_cadastro) between DATE_TRUNC('MONTH', CURRENT_DATE) AND (DATE_TRUNC('month', CURRENT_DATE::date) + INTERVAL '1 month' - INTERVAL '1 second')::timestamp AND f_cancelado = 'N' and i_cdarea = :areaId";
        return (int) $this->db->fetchColumn($sql, [':areaId' => $areaId]);
    }

    /**
     * Compare sales for a supervisor's team in target year vs. previous year (dados_RCA_por_data)
     */
    public function getRcaFaturamentoComparison(int $supervisorId, string $dateStart, string $dateEnd): array
    {
        $sql = "SELECT
                    av.i_cdarea AS area_vendedor,
                    ar.c_nomearea AS nome_area,
                    COALESCE(a23.valor_faturado, 0) AS valor_faturado_prev,
                    COALESCE(a24.valor_faturado, 0) AS valor_faturado_curr
                FROM
                    area_vendedor av
                JOIN
                    area ar ON av.i_cdarea = ar.i_cdarea AND ar.f_situacao = 'A'
                LEFT JOIN
                    (SELECT
                        pv.i_cdarea AS area_vendedor,
                        SUM(n_vlrfaturamento) AS valor_faturado
                    FROM
                        pedidovenda pv
                        join area a on a.i_cdarea = pv.i_cdarea
                    WHERE
                        pv.d_cadastro >= DATE(:dateStart) - interval '1 year' AND pv.d_cadastro <= DATE(:dateEnd) - interval '1 year'
                        AND pv.f_cancelado = 'N'
                        AND pv.f_faturou = 'S'
                        and a.f_situacao = 'A'
                    GROUP BY
                        pv.i_cdarea
                    ) AS a23 ON av.i_cdarea = a23.area_vendedor
                LEFT JOIN
                    (SELECT
                        pv.i_cdarea AS area_vendedor,
                        SUM(n_vlrfaturamento) AS valor_faturado
                    FROM
                        pedidovenda pv
                        join area a on a.i_cdarea = pv.i_cdarea
                    WHERE
                        pv.d_cadastro BETWEEN DATE(:dateStart) AND DATE(:dateEnd) + interval '1 month' - interval '1 second'
                        AND pv.f_cancelado = 'N'
                         and a.f_situacao = 'A'
                    GROUP BY
                        pv.i_cdarea
                    ) AS a24 ON av.i_cdarea = a24.area_vendedor
                WHERE
                    av.i_cdvendedor = :supervisorId
                ORDER BY
                    av.i_cdarea asc";

        return $this->db->fetchAll($sql, [
            ':dateStart' => $dateStart,
            ':dateEnd' => $dateEnd,
            ':supervisorId' => $supervisorId
        ]);
    }
}
