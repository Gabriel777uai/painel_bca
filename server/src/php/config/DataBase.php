<?php

namespace Config;

use PDO;
use PDOException;

class DataBase
{
    private String $host;
    private String $user;
    private String $port;
    private String $pass;
    private String $db;

    public function __construct()
    {
        $this->host = $_ENV['HOST'];
        $this->user = $_ENV['USER'];
        $this->port = $_ENV['PORT'];
        $this->pass = $_ENV['PASSWORLD'];
        $this->db   = $_ENV['DATABASE'];
    }

    public function connect()
    {
        try {
            if (!empty($_ENV['DB_SQLITE_PATH'])) {
                $dsn = "sqlite:{$_ENV['DB_SQLITE_PATH']}";
                $pdo = new PDO($dsn);
            } else {
                $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->db}";
                $pdo = new PDO($dsn, $this->user, $this->pass);
            }
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
}
