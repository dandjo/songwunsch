<?php

declare(strict_types=1);

namespace Songwunsch;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Thin wrapper around PDO. The connection is opened on first use.
 */
final class Database
{
    private ?PDO $pdo = null;

    /** @param array<string,mixed> $cfg */
    public function __construct(private readonly array $cfg)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) $this->cfg['host'],
            (int) $this->cfg['port'],
            (string) $this->cfg['name'],
            (string) $this->cfg['charset'],
        );

        try {
            $this->pdo = new PDO($dsn, (string) $this->cfg['user'], (string) $this->cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Credentials must never end up in the output.
            throw new RuntimeException(t('Database connection failed: {code}', ['code' => $e->getCode()]), 0, $e);
        }

        return $this->pdo;
    }

    public function schemaName(): string
    {
        return (string) $this->cfg['name'];
    }

    /**
     * @param array<int,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    public function all(string $sql, array $params = []): array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @param array<int,mixed> $params */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->pdo()->prepare($sql);
        $row->execute($params);
        $result = $row->fetch();

        return $result === false ? null : $result;
    }

    /** @param array<int,mixed> $params */
    public function exec(string $sql, array $params = []): int
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }
}
