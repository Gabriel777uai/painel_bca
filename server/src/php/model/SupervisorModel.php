<?php

namespace Model;

use Config\RequestsDatabase;

class SupervisorModel
{
    private RequestsDatabase $db;

    public function __construct()
    {
        $this->db = $db ?? new RequestsDatabase();
    }

    /**
     * List all active supervisors (supervisores)
     */
    public function listSupervisores(): array
    {
        $sql = "SELECT DISTINCT i_cdvendedor AS codigo_supervisor, c_nome AS nome_supervisor 
                FROM vendedor 
                JOIN area ON vendedor.i_cdvendedor = area.i_cdsupervisor 
                WHERE vendedor.f_situacao = 'A'";
        return $this->db->fetchAll($sql);
    }

    /**
     * Get supervisor name by ID (supervisores_codi)
     */
    public function getSupervisorName(int $supervisorId): ?string
    {
        $sql = "SELECT c_nome FROM dados.vendedor WHERE i_cdvendedor = :supervisorId";
        $name = $this->db->fetchColumn($sql, [':supervisorId' => $supervisorId]);
        return $name ?: null;
    }

    /**
     * Get active representative/vendedor data (getVendedorData)
     */
    public function getSellersData(bool $isGerente = false, int $codeGerente = 0): array
    {
        $sql = "SELECT 
                    v.c_nome, 
                    v.i_cdvendedor, 
                    v.i_cdparceiro, 
                    a.i_cdarea as area_de_venda
                FROM vendedor v
                JOIN area_vendedor av on v.i_cdvendedor = av.i_cdvendedor 
                JOIN area a on av.i_cdarea = a.i_cdarea
                WHERE v.f_vendedor = 'R' and v.f_situacao = 'A' and a.f_situacao = 'A'";

        $params = [];
        if ($isGerente) {
            $sql .= " AND a.i_cdarea IN (SELECT av2.i_cdarea FROM area_vendedor av2 WHERE av2.i_cdvendedor = :codeGerente)";
            $params[':codeGerente'] = $codeGerente;
        }

        return $this->db->fetchAll($sql, $params);
    }
}
