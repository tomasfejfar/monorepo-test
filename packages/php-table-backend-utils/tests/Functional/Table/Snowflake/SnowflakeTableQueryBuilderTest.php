<?php

declare(strict_types=1);

namespace Tests\Keboola\TableBackendUtils\Functional\Table\Snowflake;

use Doctrine\DBAL\Exception as DBALException;
use Keboola\TableBackendUtils\Column\ColumnCollection;
use Keboola\TableBackendUtils\Column\Snowflake\SnowflakeColumn;
use Keboola\TableBackendUtils\Table\Snowflake\SnowflakeTableDefinition;
use Keboola\TableBackendUtils\Table\Snowflake\SnowflakeTableQueryBuilder;
use Keboola\TableBackendUtils\Table\Snowflake\SnowflakeTableReflection;
use Tests\Keboola\TableBackendUtils\Functional\Connection\Snowflake\SnowflakeBaseCase;

// TODO we dont use DEFAULT values in columns.
/**
 * @covers SnowflakeTableQueryBuilder
 * @uses   ColumnCollection
 */
class SnowflakeTableQueryBuilderTest extends SnowflakeBaseCase
{
    /** @var SnowflakeTableQueryBuilder */
    private $qb;

    public function setUp(): void
    {
        $this->qb = new SnowflakeTableQueryBuilder();
        parent::setUp();

        $this->cleanSchema(self::TEST_SCHEMA);
    }

    public function testGetRenameTableCommand(): void
    {
        $testDb = self::TEST_SCHEMA;
        $testTable = self::TABLE_GENERIC;
        $testTableNew = 'newName';
        $this->initTable();

        // reflection to old table
        $refOld = new SnowflakeTableReflection($this->connection, $testDb, $testTable);

        // get, test and run command
        $sql = $this->qb->getRenameTableCommand(self::TEST_SCHEMA, self::TABLE_GENERIC, $testTableNew);
        self::assertEquals(
            "ALTER TABLE \"{$testDb}\".\"{$testTable}\" RENAME TO \"{$testDb}\".\"{$testTableNew}\"",
            $sql
        );
        $this->connection->executeQuery($sql);

        // reflection to new table and check the existence via counting
        $refNew = new SnowflakeTableReflection($this->connection, $testDb, $testTableNew);
        $refNew->getRowsCount();

        // test NON existence of old table via counting
        $this->expectException(DBALException::class);
        $refOld->getRowsCount();
    }

    public function testGetTruncateTableCommand(): void
    {
        $testDb = self::TEST_SCHEMA;
        $testTable = self::TABLE_GENERIC;
        $this->initTable();

        // reflection to the table
        $ref = new SnowflakeTableReflection($this->connection, $testDb, $testTable);

        // check that table is empty
        self::assertEquals(0, $ref->getRowsCount());

        // insert some data, table wont be empty
        $this->insertRowToTable($testDb, $testTable, 1, 'franta', 'omacka');
        self::assertEquals(1, $ref->getRowsCount());

        // get, test and run query
        $sql = $this->qb->getTruncateTableCommand(self::TEST_SCHEMA, self::TABLE_GENERIC);
        self::assertEquals("TRUNCATE TABLE \"{$testDb}\".\"{$testTable}\"", $sql);
        $this->connection->executeQuery($sql);

        // check that table is empty again
        self::assertEquals(0, $ref->getRowsCount());
    }

    public function testGetDropTableCommand(): void
    {
        $testDb = self::TEST_SCHEMA;
        $testTable = self::TABLE_GENERIC;
        $this->initTable();

        // reflection to the table
        $ref = new SnowflakeTableReflection($this->connection, $testDb, $testTable);

        // get, test and run query
        $sql = $this->qb->getDropTableCommand(self::TEST_SCHEMA, self::TABLE_GENERIC);
        self::assertEquals("DROP TABLE \"{$testDb}\".\"{$testTable}\"", $sql);
        $this->connection->executeQuery($sql);

        // test NON existence of old table via counting
        $this->expectException(DBALException::class);
        $ref->getRowsCount();
    }

    /**
     * @param SnowflakeColumn[] $columns
     * @param string[] $primaryKeys
     * @param string[] $expectedColumnNames
     * @param string[] $expectedPKs
     * @param string $expectedSql
     * @throws DBALException
     * @dataProvider createTableTestSqlProvider
     */
    public function testGetCreateCommand(
        array $columns,
        array $primaryKeys,
        array $expectedColumnNames,
        array $expectedPKs,
        string $expectedSql
    ): void {
        $this->cleanSchema(self::TEST_SCHEMA);
        $this->createSchema(self::TEST_SCHEMA);
        $sql = $this->qb->getCreateTableCommand(
            self::TEST_SCHEMA,
            self::TABLE_GENERIC,
            new ColumnCollection($columns),
            $primaryKeys
        );
        self::assertSame($expectedSql, $sql);
        $this->connection->executeQuery($sql);

        // test table properties
        $tableReflection = new SnowflakeTableReflection($this->connection, self::TEST_SCHEMA, self::TABLE_GENERIC);
        self::assertSame($expectedColumnNames, $tableReflection->getColumnsNames());
        self::assertSame($expectedPKs, $tableReflection->getPrimaryKeysNames());
    }

    /**
     * @return \Generator<string, mixed, mixed, mixed>
     */
    public function createTableTestSqlProvider(): \Generator
    {
        $testDb = self::TEST_SCHEMA;
        $tableName = self::TABLE_GENERIC;

        yield 'no keys' => [
            'cols' => [
                SnowflakeColumn::createGenericColumn('col1'),
                SnowflakeColumn::createGenericColumn('col2'),
            ],
            'primaryKeys' => [],
            'expectedColumnNames' => ['col1', 'col2'],
            'expectedPrimaryKeys' => [],
            'query' => <<<EOT
CREATE TABLE "$testDb"."$tableName"
(
"col1" VARCHAR NOT NULL,
"col2" VARCHAR NOT NULL
);
EOT
            ,
        ];
        yield 'with single pk' => [
            'cols' => [
                SnowflakeColumn::createGenericColumn('col1'),
                SnowflakeColumn::createGenericColumn('col2'),
            ],
            'primaryKeys' => ['col1'],
            'expectedColumnNames' => ['col1', 'col2'],
            'expectedPrimaryKeys' => ['col1'],
            'query' => <<<EOT
CREATE TABLE "$testDb"."$tableName"
(
"col1" VARCHAR NOT NULL,
"col2" VARCHAR NOT NULL,
PRIMARY KEY ("col1")
);
EOT
            ,
        ];
        yield 'with multiple pks' => [
            'cols' => [
                SnowflakeColumn::createGenericColumn('col1'),
                SnowflakeColumn::createGenericColumn('col2'),
            ],
            'primaryKeys' => ['col1', 'col2'],
            'expectedColumnNames' => ['col1', 'col2'],
            'expectedPrimaryKeys' => ['col1', 'col2'],
            'query' => <<<EOT
CREATE TABLE "$testDb"."$tableName"
(
"col1" VARCHAR NOT NULL,
"col2" VARCHAR NOT NULL,
PRIMARY KEY ("col1","col2")
);
EOT
            ,
        ];
    }

    /**
     * @return \Generator<string, array{
     *     definition: SnowflakeTableDefinition,
     *     query: string,
     *     createPrimaryKeys: bool
     * }>
     */
    public function createTableTestFromDefinitionSqlProvider(): \Generator
    {
        $testDb = self::TEST_SCHEMA;
        $tableName = self::TABLE_GENERIC;

        yield 'no keys' => [
            'definition' => new SnowflakeTableDefinition(
                $testDb,
                $tableName,
                false,
                new ColumnCollection(
                    [
                        SnowflakeColumn::createGenericColumn('col1'),
                        SnowflakeColumn::createGenericColumn('col2'),
                    ]
                ),
                []
            ),
            'query' => <<<EOT
CREATE TABLE "$testDb"."$tableName"
(
"col1" VARCHAR NOT NULL,
"col2" VARCHAR NOT NULL
);
EOT
            ,
            'createPrimaryKeys' => true,
        ];
        yield 'with single pk' => [
            'definition' => new SnowflakeTableDefinition(
                $testDb,
                $tableName,
                false,
                new ColumnCollection(
                    [
                        SnowflakeColumn::createGenericColumn('col1'),
                        SnowflakeColumn::createGenericColumn('col2'),
                    ]
                ),
                ['col1']
            ),
            'query' => <<<EOT
CREATE TABLE "$testDb"."$tableName"
(
"col1" VARCHAR NOT NULL,
"col2" VARCHAR NOT NULL,
PRIMARY KEY ("col1")
);
EOT
            ,
            'createPrimaryKeys' => true,
        ];
        yield 'with multiple pks' => [
            'definition' => new SnowflakeTableDefinition(
                $testDb,
                $tableName,
                false,
                new ColumnCollection(
                    [
                        SnowflakeColumn::createGenericColumn('col1'),
                        SnowflakeColumn::createGenericColumn('col2'),
                    ]
                ),
                ['col1', 'col2']
            ),
            'query' => <<<EOT
CREATE TABLE "$testDb"."$tableName"
(
"col1" VARCHAR NOT NULL,
"col2" VARCHAR NOT NULL,
PRIMARY KEY ("col1","col2")
);
EOT
            ,
            'createPrimaryKeys' => true,
        ];

        yield 'with multiple pks no definition' => [
            'definition' => new SnowflakeTableDefinition(
                $testDb,
                $tableName,
                false,
                new ColumnCollection(
                    [
                        SnowflakeColumn::createGenericColumn('col1'),
                        SnowflakeColumn::createGenericColumn('col2'),
                    ]
                ),
                ['col1', 'col2']
            ),
            'query' => <<<EOT
CREATE TABLE "$testDb"."$tableName"
(
"col1" VARCHAR NOT NULL,
"col2" VARCHAR NOT NULL
);
EOT
            ,
            'createPrimaryKeys' => false,
        ];
    }

    /**
     * @dataProvider createTableTestFromDefinitionSqlProvider
     */
    public function testGetCreateTableCommandFromDefinition(
        SnowflakeTableDefinition $definition,
        string $expectedSql,
        bool $createPrimaryKeys
    ): void {
        $this->cleanSchema(self::TEST_SCHEMA);
        $this->createSchema(self::TEST_SCHEMA);
        $sql = $this->qb->getCreateTableCommandFromDefinition($definition, $createPrimaryKeys);
        self::assertSame($expectedSql, $sql);
        $this->connection->executeQuery($sql);

        // test table properties
        $tableReflection = new SnowflakeTableReflection($this->connection, self::TEST_SCHEMA, self::TABLE_GENERIC);
        self::assertSame($definition->getColumnsNames(), $tableReflection->getColumnsNames());
        if ($createPrimaryKeys === true) {
            self::assertSame($definition->getPrimaryKeysNames(), $tableReflection->getPrimaryKeysNames());
        } else {
            self::assertSame([], $tableReflection->getPrimaryKeysNames());
        }
    }
}