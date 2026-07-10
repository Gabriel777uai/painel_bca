<?php

require_once __DIR__ . '/../vendor/autoload.php';

$_ENV['HOST'] = $_ENV['HOST'] ?? '127.0.0.1';
$_ENV['USER'] = $_ENV['USER'] ?? 'user';
$_ENV['PORT'] = $_ENV['PORT'] ?? '5432';
$_ENV['PASSWORLD'] = $_ENV['PASSWORLD'] ?? 'password';
$_ENV['DATABASE'] = $_ENV['DATABASE'] ?? 'testdb';
$_ENV['REDIS_HOST'] = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
$_ENV['REDIS_PORT'] = $_ENV['REDIS_PORT'] ?? '6379';

function buildTestPdoConnection(): \PDO
{
    $pdo = new \PDO('sqlite::memory:');
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function createTestSchema(\PDO $pdo, array $sqlStatements): void
{
    foreach ($sqlStatements as $sql) {
        $pdo->exec($sql);
    }
}

class TestRequestsDatabase extends \Config\RequestsDatabase
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        parent::__construct();
    }

    public function connect()
    {
        return $this->pdo;
    }
}
