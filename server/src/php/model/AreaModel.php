<?php

namespace Model;

use Config\RequestsDatabase;

class AreaModel
{
    private RequestsDatabase $db;

    public function __construct()
    {
        $this->db = $db ?? new RequestsDatabase();
    }

    /**
     * Lists active areas (puxarArea)
     */
    public function listActiveAreas(): array
    {
        $sql = "SELECT i_cdarea, c_nomearea FROM area WHERE f_situacao = 'A'";
        return $this->db->fetchAll($sql);
    }

    /**
     * Gets the name of a specific area (nomearea)
     */
    public function getAreaName(int $areaId): ?string
    {
        $sql = "SELECT c_nomearea FROM area WHERE i_cdarea = :areaId";
        $name = $this->db->fetchColumn($sql, [':areaId' => $areaId]);
        return $name ?: null;
    }
}
