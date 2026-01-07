<?php

namespace CharlGottschalk\LaravelRelix\Schema;

use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\DriverManager;
use Illuminate\Database\Connection;
use RuntimeException;

class Introspector
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function introspect(): DatabaseSchema
    {
        $schemaManager = $this->schemaManager();
        if (method_exists($schemaManager, 'listTableNames')) {
            $tableNames = $schemaManager->listTableNames();
        } elseif (method_exists($schemaManager, 'listTables')) {
            $tableNames = array_map(fn ($t) => $t->getName(), $schemaManager->listTables());
        } else {
            throw new RuntimeException('Doctrine schema manager does not support listing tables.');
        }

        sort($tableNames);

        $tables = [];

        foreach ($tableNames as $tableName) {
            if (method_exists($schemaManager, 'introspectTable')) {
                $details = $schemaManager->introspectTable($tableName);
            } elseif (method_exists($schemaManager, 'listTableDetails')) {
                $details = $schemaManager->listTableDetails($tableName);
            } else {
                throw new RuntimeException('Doctrine schema manager does not support table introspection.');
            }

            $primaryColumns = [];
            $primaryKey = $details->getPrimaryKey();
            if ($primaryKey) {
                $primaryColumns = $primaryKey->getColumns();
            }

            $foreignKeysByLocalColumn = [];
            foreach ($details->getForeignKeys() as $foreignKey) {
                $localColumns = $foreignKey->getLocalColumns();
                $foreignColumns = $foreignKey->getForeignColumns();
                $foreignTable = $foreignKey->getForeignTableName();

                foreach ($localColumns as $idx => $localColumn) {
                    $foreignColumn = $foreignColumns[$idx] ?? $foreignColumns[0] ?? 'id';
                    $foreignKeysByLocalColumn[$localColumn] = new ForeignKeySchema($foreignTable, $foreignColumn);
                }
            }

            $columns = [];
            foreach ($details->getColumns() as $col) {
                $name = $col->getName();
                $type = $this->typeName($col->getType());

                $columns[] = new ColumnSchema(
                    name: $name,
                    type: $type,
                    nullable: ! $col->getNotnull(),
                    autoIncrement: (bool) ($col->getAutoincrement() ?? false),
                    isPrimaryKey: in_array($name, $primaryColumns, true),
                    foreignKey: $foreignKeysByLocalColumn[$name] ?? null,
                );
            }

            $tables[] = new TableSchema($tableName, $columns);
        }

        return new DatabaseSchema($tables);
    }

    private function typeName(object $type): string
    {
        if (method_exists($type, 'getName')) {
            /** @var mixed $name */
            $name = $type->getName();
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        $class = $type::class;

        if (defined($class . '::NAME')) {
            $name = constant($class . '::NAME');
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return strtolower((new \ReflectionClass($type))->getShortName());
    }

    /**
     * @return object Doctrine DBAL schema manager (DBAL 3/4 compatible)
     */
    private function schemaManager(): object
    {
        $doctrine = $this->doctrineConnection();

        if (method_exists($doctrine, 'createSchemaManager')) {
            return $doctrine->createSchemaManager();
        }

        if (method_exists($doctrine, 'getSchemaManager')) {
            return $doctrine->getSchemaManager();
        }

        throw new RuntimeException('Doctrine schema manager is not available. Unsupported doctrine/dbal version.');
    }

    private function doctrineConnection(): DoctrineConnection
    {
        if (method_exists($this->connection, 'getDoctrineConnection')) {
            $doctrine = $this->connection->getDoctrineConnection();
            if ($doctrine instanceof DoctrineConnection) {
                return $doctrine;
            }
        }

        $params = $this->doctrineParamsFromIlluminateConnection();

        try {
            return DriverManager::getConnection($params);
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to create Doctrine connection for schema introspection: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function doctrineParamsFromIlluminateConnection(): array
    {
        $driver = $this->connection->getDriverName();
        $config = $this->connectionConfig();

        if ($driver === 'mysql') {
            $params = [
                'driver' => 'pdo_mysql',
                'host' => $config['host'] ?? null,
                'dbname' => $config['database'] ?? null,
                'user' => $config['username'] ?? null,
                'password' => $config['password'] ?? null,
                'port' => $config['port'] ?? null,
                'charset' => $config['charset'] ?? null,
            ];

            if (! empty($config['unix_socket'])) {
                $params['unix_socket'] = $config['unix_socket'];
            }

            return array_filter($params, fn ($v) => $v !== null && $v !== '');
        }

        if ($driver === 'pgsql') {
            $params = [
                'driver' => 'pdo_pgsql',
                'host' => $config['host'] ?? null,
                'dbname' => $config['database'] ?? null,
                'user' => $config['username'] ?? null,
                'password' => $config['password'] ?? null,
                'port' => $config['port'] ?? null,
            ];

            return array_filter($params, fn ($v) => $v !== null && $v !== '');
        }

        if ($driver === 'sqlite') {
            $database = $config['database'] ?? null;
            if (! is_string($database) || $database === '') {
                throw new RuntimeException('SQLite connection is missing a database path.');
            }

            if ($database === ':memory:') {
                return ['driver' => 'pdo_sqlite', 'memory' => true];
            }

            return ['driver' => 'pdo_sqlite', 'path' => $database];
        }

        throw new RuntimeException('Unsupported driver for Relix schema introspection: ' . $driver);
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionConfig(): array
    {
        if (! method_exists($this->connection, 'getConfig')) {
            return [];
        }

        try {
            $all = $this->connection->getConfig();
            return is_array($all) ? $all : [];
        } catch (\ArgumentCountError|\TypeError) {
            // Older/newer illuminate versions may require a key; we only need a few.
            $keys = ['host', 'database', 'username', 'password', 'port', 'charset', 'unix_socket'];
            $config = [];
            foreach ($keys as $key) {
                try {
                    $config[$key] = $this->connection->getConfig($key);
                } catch (\Throwable) {
                    // ignore
                }
            }
            return $config;
        }
    }
}
