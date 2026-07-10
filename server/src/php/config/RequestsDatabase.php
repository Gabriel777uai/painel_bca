<?php

namespace Config;

use Config\DataBase;
use PDOException;
use PDO;
use Service\CacheService;

class RequestsDatabase extends DataBase
{
    private CacheService $cache;

    public function __construct()
    {
        parent::__construct();
        $this->cache = new CacheService();
    }

    public function execute(String $sql, array $params = [])
    {
        $pdo = $this->connect();
        try {
            $pdo->beginTransaction();

            $result = $pdo->prepare($sql);
            $result->execute($params);

            $pdo->commit();
            return $result;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function fetchColumn(String $sql, array $params = [])
    {
        $cacheKey = 'query_col_' . md5($sql . json_encode($params));
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->execute($sql, $params);
        $val = $result->fetchColumn();

        $this->cache->set($cacheKey, $val);
        return $val;
    }

    public function fetchAll(String $sql, array $params = [], bool $assoc = true)
    {
        $fetchStyle = $assoc ? PDO::FETCH_ASSOC : PDO::FETCH_NUM;
        $cacheKey = 'query_all_' . ($assoc ? 'assoc_' : 'num_') . md5($sql . json_encode($params));
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->execute($sql, $params);
        $val = $result->fetchAll($fetchStyle);

        $this->cache->set($cacheKey, $val);
        return $val;
    }

    public function fetch(String $sql, array $params = [], bool $assoc = true)
    {
        $fetchStyle = $assoc ? PDO::FETCH_ASSOC : PDO::FETCH_NUM;
        $cacheKey = 'query_row_' . ($assoc ? 'assoc_' : 'num_') . md5($sql . json_encode($params));
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->execute($sql, $params);
        $val = $result->fetch($fetchStyle);

        $this->cache->set($cacheKey, $val);
        return $val;
    }
}
