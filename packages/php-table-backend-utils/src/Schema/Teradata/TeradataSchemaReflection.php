<?php

declare(strict_types=1);

namespace Keboola\TableBackendUtils\Schema\Teradata;

use Doctrine\DBAL\Connection;
use Keboola\TableBackendUtils\Collection;
use Keboola\TableBackendUtils\Escaping\Teradata\TeradataQuote;
use Keboola\TableBackendUtils\Schema\SchemaReflectionInterface;

final class TeradataSchemaReflection implements SchemaReflectionInterface
{
    /** @var Connection */
    private $connection;

    /** @var string */
    private $databaseName;

    public function __construct(Connection $connection, string $databaseName)
    {
        $this->databaseName = $databaseName;
        $this->connection = $connection;
    }

    public function dbExists(): bool
    {
        try {
            $this->connection->executeQuery('HELP DATABASE ' . $this->databaseName);
            return true;
        } catch (\Doctrine\DBAL\Exception $e) {
            // TODO nejaka chytrejsi exception
            return false;
        }
    }

    public function getTablesNames(): array
    {
        $database = TeradataQuote::quote($this->databaseName);
        $tables = $this->connection->fetchAllAssociative(
            <<< EOT
SELECT TableName 
FROM DBC.TablesV 
WHERE TableKind = 'T' AND databasename=$database
EOT
        );

        return Collection::extractByKey($tables, 'TableName');
    }

    public function getViewsNames(): array
    {
        $database = TeradataQuote::quote($this->databaseName);
        $tables = $this->connection->fetchAllAssociative(
            <<< EOT
SELECT TableName 
FROM DBC.TablesV 
WHERE TableKind = 'V' AND databasename=$database
EOT
        );

        return array_map(static function ($table) {
            return $table['TableName'];
        }, $tables);
    }
}