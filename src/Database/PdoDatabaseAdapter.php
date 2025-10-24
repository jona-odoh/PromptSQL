<?php

declare(strict_types=1);

namespace PromptSQL\Database;

use PDO;
use PDOException;
use PDOStatement;
use PromptSQL\Contracts\DatabaseAdapterInterface;
use PromptSQL\Enums\DatabaseDriver;
use PromptSQL\Exceptions\ExecutionException;

final class PdoDatabaseAdapter implements DatabaseAdapterInterface
{
    private readonly PDO $pdo;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly DatabaseDriver $driver,
        string $dsn,
        string $username,
        string $password,
        array $options = []
    ) {
        $defaultOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, // safer
        ];

        $opts = $options + $defaultOptions;

        try {
            $this->pdo = new PDO($dsn, $username, $password, $opts);
        } catch (PDOException $e) {
            throw new ExecutionException('Failed to connect to database: ' . $e->getMessage());
        }
    }

    public function getDriver(): DatabaseDriver
    {
        return $this->driver;
    }

    public function execute(string $sql, array $params = []): array
    {
        try {
            [$sqlExpanded, $paramsExpanded] = $this->expandArrayParameters($sql, $params);
            $stmt = $this->pdo->prepare($sqlExpanded);
            $this->bindAll($stmt, $paramsExpanded);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new ExecutionException('Query execution failed: ' . $e->getMessage());
        }
    }

    public function getSchemaOverview(): array
    {
        try {
            return match ($this->driver) {
                DatabaseDriver::MySQL => $this->mysqlSchemaOverview(),
                DatabaseDriver::PostgreSQL => $this->postgresSchemaOverview(),
            };
        } catch (\Throwable) {
            // Non-fatal; return empty on failure
            return ['tables' => [], 'columns' => []];
        }
    }

    /**
     * Expand array parameters used in IN (:ids) into (:ids_0, :ids_1, ...)
     *
     * @param string $sql
     * @param array<string, mixed> $params
     * @return array{0:string,1:array<string,mixed>}
     */
    private function expandArrayParameters(string $sql, array $params): array
    {
        $newParams = $params;
        foreach ($params as $name => $value) {
            if (is_array($value)) {
                $placeholders = [];
                foreach (array_values($value) as $i => $val) {
                    $ph = ':' . $name . '_' . $i;
                    $placeholders[] = $ph;
                    $newParams[$name . '_' . $i] = $val;
                }
                // Replace single :name with (:name_0,:name_1,...)
                $sql = preg_replace('/:' . preg_quote($name, '/') . '\b/', implode(',', $placeholders), $sql, 1) ?? $sql;
                unset($newParams[$name]);
            }
        }
        return [$sql, $newParams];
    }

    /**
     * @param PDOStatement $stmt
     * @param array<string, mixed> $params
     */
    private function bindAll(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name => $value) {
            $paramType = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default => PDO::PARAM_STR
            };
            $stmt->bindValue(is_string($name) && !str_starts_with($name, ':') ? ':' . $name : $name, $value, $paramType);
        }
    }

    private function mysqlSchemaOverview(): array
    {
        $tables = [];
        $columns = [];
        $stmt = $this->pdo->query("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE()");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $t = $row['TABLE_NAME'];
            $tables[] = $t;
            $columns[$t] = [];
        }

        $stmt = $this->pdo->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE()");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
        }

        return ['tables' => $tables, 'columns' => $columns];
    }

    private function postgresSchemaOverview(): array
    {
        $tables = [];
        $columns = [];

        $stmt = $this->pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $t = $row['table_name'];
            $tables[] = $t;
            $columns[$t] = [];
        }

        $stmt = $this->pdo->query("SELECT table_name, column_name FROM information_schema.columns WHERE table_schema = 'public'");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns[$row['table_name']][] = $row['column_name'];
        }

        return ['tables' => $tables, 'columns' => $columns];
    }
}