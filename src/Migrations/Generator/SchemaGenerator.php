<?php


declare(strict_types=1);

namespace Eva\Database\Migrations\Generator;

use Eva\Database\Schema\Table\ColumnSchema;
use Eva\Database\ConnectionInterface;
use Eva\Database\Schema\Table\Key\Foreign\DeleteRuleEnum;
use Eva\Database\Schema\Table\Key\ForeignKeySchema;
use Eva\Database\Schema\Table\Key\Index\Enums\IndexEngineEnum;
use Eva\Database\Schema\Table\Key\IndexKeySchema;
use Eva\Database\Schema\Table\Key\PrimaryKeySchema;
use Eva\Database\Schema\Schema;
use Eva\Database\Schema\Table\Key\Index\IndexColumnSchema;
use Eva\Database\Schema\Table\Key\Index\Enums\OrderSchemaEnum;
use Eva\Database\Schema\Table\Key\Foreign\UpdateRuleEnum;
use Eva\Database\Schema\TableSchema;
use Eva\Database\Schema\Table\Key\UniqueKeySchema;

class SchemaGenerator
{
    public function __construct(
        protected ConnectionInterface $connection,
    ) {}

    public function generateFromDatabase(): Schema
    {
        $dbName = $this->connection->getDatabaseName();
        $tableSchemaList = [];
        $sql = 'select * from `information_schema`.`TABLES` where TABLE_SCHEMA = :schema';
        $stmt = $this->connection->prepare($sql, ['schema' => $dbName]);
        $stmt->execute();
        $tableList = [];

        while ($table = $stmt->fetch()) {
            $tableList[] = $table;
        }

        $stmt->closeCursor();
        foreach ($tableList as $table) {
            $tableName = $table['TABLE_NAME'];
            if ($tableName === 'migrations') {
                continue;
            }

            $sql = 'select * from `information_schema`.`COLUMNS` where TABLE_SCHEMA = :schema and TABLE_NAME = :table_name';
            $stmt = $this->connection->prepare($sql, ['schema' => $dbName, 'table_name' => $tableName]);
            $stmt->execute();
            $columnSchemaList = [];
            $keySchemaList = [];

            while ($column = $stmt->fetch()) {
                $columnSchemaList[] = new ColumnSchema(
                    $column['COLUMN_NAME'],
                    $column['COLUMN_COMMENT'],
                    $column['COLLATION_NAME'],
                    $column['COLUMN_TYPE'],
                    'NULL' === $column['COLUMN_DEFAULT'] ? null : $column['COLUMN_DEFAULT'],
                    'YES' === $column['IS_NULLABLE'],
                );
            }

            $stmt->closeCursor();

            [$primaryKeyList, $uniqueKeyList, $foreignKeyList] = $this->getNotIndexKeyList($dbName, $tableName);
            $indexKeyList = $this->getIndexKeyList($dbName, $tableName, $uniqueKeyList, $foreignKeyList);
            $keySchemaList += $this->buildIndexKeySchemaByList($dbName, $tableName, $indexKeyList);
            $keySchemaList += $this->buildUniqueKeySchemaByList($dbName, $tableName, $uniqueKeyList);
            $keySchemaList += $this->buildPrimaryKeySchemaByList($dbName, $tableName, $primaryKeyList);
            $keySchemaList += $this->buildForeignKeySchemaByList($dbName, $tableName, $foreignKeyList);
            $tableSchemaList[] = new TableSchema(
                $tableName,
                $table['TABLE_COMMENT'],
                $table['ENGINE'],
                $table['TABLE_COLLATION'],
                $columnSchemaList,
                $keySchemaList,
            );
        }

        return new Schema($dbName, $tableSchemaList);
    }

    /**
     * @param string[] $foreignKeyList
     * @return PrimaryKeySchema[]
     */
    private function buildForeignKeySchemaByList(string $schema, string $table, array $foreignKeyList): array
    {
        $foreignKeySchemaList = [];

        $sql = 'select kcu.*, rc.DELETE_RULE, rc.UPDATE_RULE
                from `information_schema`.`KEY_COLUMN_USAGE` as kcu
                left join `information_schema`.REFERENTIAL_CONSTRAINTS as rc on rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                where kcu.TABLE_SCHEMA = :schema
                and kcu.TABLE_NAME = :table_name
                and rc.CONSTRAINT_SCHEMA = :schema_rc
                and rc.TABLE_NAME = :table_name_rc
                  ';
        $stmt = $this->connection->prepare($sql, [
            'schema' => $schema,
            'table_name' => $table,
            'schema_rc' => $schema,
            'table_name_rc' => $table,
        ]);
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            foreach ($foreignKeyList as $foreignKey) {
                if ($foreignKey === $row['CONSTRAINT_NAME']) {
                    $deleteRule = DeleteRuleEnum::RESTRICT;

                    if ($row['DELETE_RULE'] === 'CASCADE') {
                        $deleteRule = DeleteRuleEnum::CASCADE;
                    }

                    if ($row['DELETE_RULE'] === 'NULL') {
                        $deleteRule = DeleteRuleEnum::NULL;
                    }

                    $updateRule = UpdateRuleEnum::RESTRICT;

                    if ($row['UPDATE_RULE'] === 'CASCADE') {
                        $updateRule = UpdateRuleEnum::CASCADE;
                    }

                    if ($row['UPDATE_RULE'] === 'NULL') {
                        $updateRule = UpdateRuleEnum::NULL;
                    }

                    $foreignKeySchemaList[] = new ForeignKeySchema(
                        $row['CONSTRAINT_NAME'],
                        $row['COLUMN_NAME'],
                        $row['REFERENCED_TABLE_NAME'],
                        $row['REFERENCED_COLUMN_NAME'],
                        $deleteRule,
                        $updateRule,
                    );
                }
            }
        }

        return $foreignKeySchemaList;
    }

    /**
     * @param string[] $primaryKeyList
     * @return PrimaryKeySchema[]
     */
    private function buildPrimaryKeySchemaByList(string $schema, string $table, array $primaryKeyList): array
    {
        $primaryKeySchemaList = [];

        $sql = 'select * from `information_schema`.`KEY_COLUMN_USAGE` 
                where TABLE_SCHEMA = :schema
                and TABLE_NAME = :table_name
                  ';
        $stmt = $this->connection->prepare($sql, ['schema' => $schema, 'table_name' => $table]);
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            foreach ($primaryKeyList as $primaryKey) {
                if ($primaryKey === $row['CONSTRAINT_NAME']) {
                    $primaryKeySchemaList[] = new PrimaryKeySchema($row['CONSTRAINT_NAME'], $row['COLUMN_NAME']);
                }
            }
        }

        return $primaryKeySchemaList;
    }

    /**
     * @param string[] $uniqueKeyList
     * @return UniqueKeySchema[]
     */
    private function buildUniqueKeySchemaByList(string $schema, string $table, array $uniqueKeyList): array
    {
        $uniqueKeySchemaList = [];
        $sql = 'select * from `information_schema`.`STATISTICS` 
                where TABLE_SCHEMA = :schema
                and TABLE_NAME = :table_name
                and INDEX_NAME in (:index_name_list)
                group by INDEX_NAME 
                  ';
        $stmt = $this->connection->prepare($sql, [
            'schema' => $schema,
            'table_name' => $table,
            'index_name_list' => $uniqueKeyList,
        ]);
        $stmt->execute();
        $statistics = [];

        while ($row = $stmt->fetch()) {
            $statistics[] = $row;
        }

        foreach ($uniqueKeyList as $uniqueKey) {
            $columnList = array_filter($statistics, static fn (array $item) => $item['INDEX_NAME'] === $uniqueKey);
            $columnSchemaList = [];

            foreach ($columnList as $column) {
                $columnSchemaList[] = new IndexColumnSchema(
                    $column['COLUMN_NAME'],
                    'D' === $column['COLLATION'] ? OrderSchemaEnum::DESC : OrderSchemaEnum::ASC
                );
            }

            $uniqueKeySchemaList[] = new UniqueKeySchema(
                $columnList[0]['INDEX_NAME'],
                $columnSchemaList,
                IndexEngineEnum::from(strtoupper($columnList[0]['INDEX_TYPE'])),
            );
        }

        return $uniqueKeySchemaList;
    }

    /**
     * @param string[] $indexKeyList
     * @return IndexKeySchema[]
     */
    private function buildIndexKeySchemaByList(string $schema, string $table, array $indexKeyList): array
    {
        $indexKeySchemaList = [];
        $sql = 'select * from `information_schema`.`STATISTICS` 
                where TABLE_SCHEMA = :schema
                and TABLE_NAME = :table_name
                and INDEX_NAME in (:index_name_list)
                group by INDEX_NAME 
                  ';
        $stmt = $this->connection->prepare($sql,[
            'schema' => $schema,
            'table_name' => $table,
            'index_name_list' => $indexKeyList,
        ]);
        $stmt->execute();
        $statistics = [];

        while ($row = $stmt->fetch()) {
            $statistics[] = $row;
        }

        foreach ($indexKeyList as $indexKey) {
            $columnList = array_filter($statistics, static fn (array $item) => $item['INDEX_NAME'] === $indexKey);
            $columnSchemaList = [];

            foreach ($columnList as $column) {
                $columnSchemaList[] = new IndexColumnSchema(
                    $column['COLUMN_NAME'],
                    'D' === $column['COLLATION'] ? OrderSchemaEnum::DESC : OrderSchemaEnum::ASC
                );
            }

            $indexKeySchemaList[] = new IndexKeySchema(
                $columnList[0]['INDEX_NAME'],
                $columnSchemaList,
                IndexEngineEnum::from(strtoupper($columnList[0]['INDEX_TYPE'])),
            );
        }

        return $indexKeySchemaList;
    }

    private function getNotIndexKeyList(string $schema, string $table): array
    {
        $primaryKeyList = [];
        $uniqueKeyList = [];
        $foreignKeyList = [];
        $sql = 'select * from `information_schema`.`TABLE_CONSTRAINTS` where TABLE_SCHEMA = :schema and TABLE_NAME = :table_name';
        $stmt = $this->connection->prepare($sql,['schema' => $schema, 'table_name' => $table]);
        $stmt->execute();

        while ($key = $stmt->fetch()) {
            if ($key['CONSTRAINT_TYPE'] === 'PRIMARY KEY') {
                $primaryKeyList[] = $key['CONSTRAINT_NAME'];
            }

            if ($key['CONSTRAINT_TYPE'] === 'UNIQUE') {
                $uniqueKeyList[] = $key['CONSTRAINT_NAME'];
            }

            if ($key['CONSTRAINT_TYPE'] === 'FOREIGN KEY') {
                $foreignKeyList[] = $key['CONSTRAINT_NAME'];
            }
        }

        $stmt->closeCursor();

        return [$primaryKeyList, $uniqueKeyList, $foreignKeyList];
    }

    private function getIndexKeyList(string $schema, string $table, array $primaryKeyList, array $uniqueKeyList): array
    {
        $indexKeyList = [];
        $sql = 'select * from `information_schema`.`STATISTICS` 
                where TABLE_SCHEMA = :schema
                and TABLE_NAME = :table_name
                group by INDEX_NAME 
                  ';
        $stmt = $this->connection->prepare($sql, ['schema' => $schema, 'table_name' => $table]);
        $stmt->execute();

        while ($key = $stmt->fetch()) {
            if (in_array($key['INDEX_NAME'], $primaryKeyList, true) ||
                in_array($key['INDEX_NAME'], $uniqueKeyList, true)) {
                continue;
            }

            $indexKeyList[] = $key['INDEX_NAME'];
        }

        $stmt->closeCursor();

        return $indexKeyList;
    }

    public function generateFromConfig(array $yamlSchema): Schema
    {
        $tableSchemaList = [];

        foreach ($yamlSchema['tables'] as $tableName => $tableData) {
            $columnSchemaList = [];
            $keySchemaList = [];

            foreach ($tableData['columns'] as $columnName => $columnData) {
                $columnSchemaList[] = new ColumnSchema(
                    $columnName,
                    $columnData['comment'] ?? null,
                    $columnData['collate'] ?? null,
                    $columnData['type'],
                    $columnData['default'] ?? null,
                    $columnData['nullable'] ?? true,
                    $columnData['autoincrement'] ?? false,
                );
            }

            foreach ($tableData['keys'] as $keyType => $keyData) {
                if ($keyType === 'primary') {
                    $keySchemaList[] = new PrimaryKeySchema($keyData['name'], $keyData['column']);
                }

                if ($keyType === 'unique') {

                    foreach ($keyData as $keyItem) {
                        $keyColumnSchemaList = [];

                        foreach ($keyItem['columns'] as $keyColumnData) {
                            $order = OrderSchemaEnum::ASC;

                            if ($keyColumnData['order'] === 'desc') {
                                $order = OrderSchemaEnum::DESC;
                            }

                            $keyColumnSchemaList[] = new IndexColumnSchema($keyColumnData['name'], $order);
                        }

                        $keySchemaList[] = new UniqueKeySchema($keyItem['name'], $keyColumnSchemaList, IndexEngineEnum::from(strtoupper($keyItem['engine'])));
                    }
                }

                if ($keyType === 'index') {
                    foreach ($keyData as $keyItem) {
                        $keyColumnSchemaList = [];

                        foreach ($keyItem['columns'] as $keyColumnData) {
                            $order = OrderSchemaEnum::ASC;

                            if ($keyColumnData['order'] === 'desc') {
                                $order = OrderSchemaEnum::DESC;
                            }

                            $keyColumnSchemaList[] = new IndexColumnSchema($keyColumnData['name'], $order);
                        }

                        $keySchemaList[] = new IndexKeySchema($keyItem['name'], $keyColumnSchemaList, IndexEngineEnum::from(strtoupper($keyItem['engine'])));
                    }
                }

                if ($keyType === 'foreign') {
                    foreach ($keyData as $keyItem) {
                        $deleteRule = DeleteRuleEnum::RESTRICT;
                        if ($keyItem['delete_rule'] === 'cascade') {
                            $deleteRule = DeleteRuleEnum::CASCADE;
                        }
                        if ($keyItem['delete_rule'] === 'null') {
                            $deleteRule = DeleteRuleEnum::NULL;
                        }
                        $updateRule = UpdateRuleEnum::RESTRICT;
                        if ($keyItem['update_rule'] === 'cascade') {
                            $updateRule = UpdateRuleEnum::CASCADE;
                        }
                        if ($keyItem['update_rule'] === 'null') {
                            $updateRule = UpdateRuleEnum::NULL;
                        }
                        $keySchemaList[] = new ForeignKeySchema(
                            $keyItem['name'],
                            $keyItem['column'],
                            $keyItem['reference_table'],
                            $keyItem['reference_column'],
                            $deleteRule,
                            $updateRule,
                        );
                    }
                }
            }

            $tableSchema = new TableSchema(
                $tableName,
                $tableData['comment'] ?? null,
                $tableData['engine'],
                $tableData['collation'],
                $columnSchemaList,
                $keySchemaList,
            );
            $tableSchemaList[] = $tableSchema;
        }

        return new Schema(
            $yamlSchema['name'],
            $tableSchemaList,
        );
    }
}

//
//declare(strict_types=1);
//
//namespace Eva\Database\Migrations;
//
//use Eva\Database\ConnectionInterface;
//
//class SchemaGenerator
//{
//    protected Schema $schema;
//    protected string $schemaName;
//    protected string $migrationUp = '';
//    protected string $migrationDown = '';
//
//    public function __construct(protected ConnectionInterface $connection) {}
//
//    public function generateMigration(): void
//    {
//        $this->schemaName = $this->schema->getName();
//        $currentSchema = $this->getCurrentSchema();
//
//        $currentTables = $currentSchema->getTablesName();
//        $newSchemaTables = $this->schema->getTablesName();
//        $forDeleteTables = array_filter($currentTables, fn(string $table) => false === in_array($table, $newSchemaTables));
//        $forCreateTables = array_filter($newSchemaTables, fn(string $table) => false === in_array($table, $currentTables));
//        $forAlterTables = array_intersect($newSchemaTables, $currentTables);
//
//        $this->deleteTables($forDeleteTables, $currentSchema);
//        $this->createTables($forCreateTables, $this->schema);
//        $this->alterTables($forAlterTables, $currentSchema, $this->schema);
//    }
//
//    protected function deleteTables(array $tables, Schema $currentSchema): void
//    {
//        foreach ($tables as $table) {
//            $this->migrationUp .= "
//                DROP TABLE $table;
//            ";
//
//            $columns = $currentSchema->getColumns($table);
//            $columnsSql = '';
//
//            foreach ($columns as $column => $columnData) {
//                if ($column !== '_KEYS' && $column !== '_INDEXES')
//                $columnsSql .= $column . ' ' . implode(', ', $columnData);
//            }
//
//            if (isset($columns['_KEYS']['PRIMARY'])) {
//                $columnsSql .= ', PRIMARY KEY (' . $columns['_KEYS']['PRIMARY'] . ')';
//            }
//
//            if (isset($columns['_KEYS']['FOREIGN'])) {
//                foreach ($columns['_KEYS']['FOREIGN'] as $name => $fkey) {
//                    $columnsSql .= ', constraint '.$name.' foreign key ' . $fkey;
//                }
//            }
//
//            $this->migrationDown .= "
//                CREATE TABLE $table ($columnsSql);
//            ";
//
//            if (isset($columns['_INDEXES'])) {
//                foreach ($columns['_INDEXES'] as $indexName => $index) {
//                    $this->migrationDown .= PHP_EOL.'create '.$index.';';
//                }
//            }
//        }
//    }
//
//
//    protected function createTables(array $tables, Schema $newSchema): void
//    {
//        foreach ($tables as $table) {
//            $columns = $newSchema->getColumns($table);
//            $columnsSql = '';
//
//            foreach ($columns as $column => $columnData) {
//                if ($column !== '_KEYS' && $column !== '_INDEXES')
//                    $columnsSql .= $column . ' ' . implode(', ', $columnData);
//            }
//
//            if (isset($columns['_KEYS']['PRIMARY'])) {
//                $columnsSql .= ', PRIMARY KEY (' . $columns['_KEYS']['PRIMARY'] . ')';
//            }
//
//            if (isset($columns['_KEYS']['FOREIGN'])) {
//                foreach ($columns['_KEYS']['FOREIGN'] as $name => $fkey) {
//                    $columnsSql .= ', constraint '.$name.' foreign key ' . $fkey;
//                }
//            }
//
//            $this->migrationUp .= "
//                CREATE TABLE $table ($columnsSql);
//            ";
//
//            if (isset($columns['_INDEXES'])) {
//                foreach ($columns['_INDEXES'] as $indexName => $index) {
//                    $this->migrationUp .= PHP_EOL.'create '.$index.';';
//                }
//            }
//
//            $this->migrationDown .= "
//                DROP TABLE $table;
//            ";
//        }
//    }
//
//    protected function alterTables(array $tables, Schema $currentSchema, Schema $newSchema): void
//    {
//        foreach ($tables as $table) {
//            $currentSchemaColumns = $currentSchema->getColumns($table);
//            $newSchemaColumns = $newSchema->getColumns($table);
//            $columnsSql = '';
//
//            foreach ($newSchemaColumns as $columnName => $columnData) {
//                $currentSchemaColumnData = $currentSchemaColumns[$columnName];
//                if ($columnName !== '_KEYS' && $columnName !== '_INDEXES') {
//                    preg_match('/\w+/', $currentSchemaColumnData, $match);
//                    $type = $match[0];
//                    preg_match('/not null/', $currentSchemaColumnData, $match);
//                    $nullable = !isset($match[0]);
//                    preg_match('/default \w+ /', $currentSchemaColumnData, $match);
//                    $default = $match[0] ?? null;
//                    preg_match('/auto_increment/', $currentSchemaColumnData, $match);
//                    $autoIncrement = isset($match[0]);
//
//                    if ($columnData !== $currentSchemaColumnData) {
//
//                    }
//                }
//
//            }
//
//        }
//    }
//
//    protected function getCurrentSchema(): Schema
//    {
//        $tables = $this->getTables();
//        $columns = $this->getColumnsByTableList($tables);
//        $keys = $this->getKeysByTableList($tables);
//        $indexes = $this->getIndexesByTableList($tables);
//        $schema = ['name' => $this->schemaName];
//        $schema['tables'] = $columns;
//
//        foreach ($keys as $table => $key) {
//            $schema['tables'][$table]['_KEYS'] = $key['_KEYS'];
//        }
//        foreach ($indexes as $table => $index) {
//            $schema['tables'][$table]['_INDEXES'] = $index['_INDEXES'];
//        }
//
//        return new Schema($schema);
//    }
//
//    protected function getKeysByTableList(array $tables): array
//    {
//        $tablesSql = $this->connection->prepareListParam($tables);
////        PRIMARY: id
////      FOREIGN:
////        users_cabinet_id_fk: {columns:cabinet_id, references_columns: cabinet (id), on: delete set null}
//        $stmt = $this->connection->prepare("
//                SELECT
//                    t1.`TABLE_NAME` as `table`,
//                    t1.`COLUMN_NAME` as `name`,
//                    t1.`CONSTRAINT_NAME` as `constr_name`,
//                    t2.`MATCH_OPTION` as `match`,
//                    t2.`UPDATE_RULE` as `update_rule`,
//                    t2.`DELETE_RULE` as `delete_rule`
//                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE as t1
//                LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS as t2 ON t1.CONSTRAINT_NAME = t2.CONSTRAINT_NAME
//                WHERE t1.TABLE_SCHEMA = '{$this->schemaName}' AND TABLE_NAME IN ($tablesSql)
//            ");
//        $stmt->execute();
//        $keys = [];
//
//        while ($row = $stmt->fetch()) {
//            if ($row['constr_name'] === 'PRIMARY') {
//                $keys[$row['table']]['_KEYS']['PRIMARY'] = $row['name'];
//            } else {
////                $keys[$row['table']]['_KEYS']['FOREIGN'][$row['constr_name']] =
////                    '('.$row['name'].') references '.$row['ref_table'].'('.$row['ref_column'].')' . ($row['update_rule'] === 'RESTRICT' ? ' on update '.$row['update_rule']:'').($row['delete_rule'] === 'RESTRICT' ? ' on delete '.$row['delete_rule']:'');
//                $keys[$row['table']]['_KEYS']['FOREIGN'][$row['constr_name']] =
//                    '('.$row['name'].') references '.$row['ref_table'].'('.$row['ref_column'].')' . ($row['update_rule'] === 'RESTRICT' ? ' on update '.$row['update_rule']:'').($row['delete_rule'] === 'RESTRICT' ? ' on delete '.$row['delete_rule']:'');
//            }
//        }
//
//        $stmt->closeCursor();
//
//        return $keys;
//    }
//
//    protected function getColumnsByTableList(array $tables): array
//    {
//        $tablesSql = $this->connection->prepareListParam($tables);
//        $stmt = $this->connection->prepare("
//                SELECT
//                    `TABLE_NAME` as `table`,
//                    `COLUMN_NAME` as `column`,
//                    `DATA_TYPE` as `type`,
//                    `IS_NULLABLE` as `nullable`,
//                    `EXTRA` as `auto_increment`,
//                    `COLUMN_DEFAULT` as `default`
//                FROM INFORMATION_SCHEMA.COLUMNS
//                WHERE TABLE_SCHEMA = '{$this->schemaName}' AND TABLE_NAME IN ($tablesSql)
//            ");
//        $stmt->execute();
//        $columns = [];
//
//        while ($row = $stmt->fetch()) {
////            $columns[$row['table']][$row['column']] = $row;
//            $columns[$row['table']][$row['column']] = $row['type'].' '.($row['nullable'] === 'NO' ? 'not null':'').' default '.$row['default'].' '.$row['auto_increment'];
//        }
//
//        $stmt->closeCursor();
//
//        return $columns;
//    }
//
//    protected function getIndexesByTableList(array $tables): array
//    {
//        $tablesSql = $this->connection->prepareListParam($tables);
//        $stmt = $this->connection->prepare("
//                SELECT
//                    `INDEX_NAME` as name,
//                    `COLUMN_NAME` as column,
//                    `NON_UNIQUE` as non_unique,
//                    `TABLE_NAME` as table,
//                    FROM INFORMATION_SCHEMA.STATISTICS
//                    WHERE TABLE_SCHEMA = '{$this->schemaName}' AND TABLE_NAME IN ($tablesSql)
//            ");
//        $stmt->execute();
//        $indexes = [];
//
//        while ($row = $stmt->fetch()) {
////            $indexes[$row['table']]['_INDEXES'][$row['name']] = $row;
//            $indexes[$row['table']]['_INDEXES'][$row['name']] = ($row['non_unique'] ? 'unique ':'').'index '.$row['name'].' on '.$row['table'].' ('.$row['column'].')';
//        }
//
//        $stmt->closeCursor();
//
//        return $indexes;
//    }
//
//    protected function getTables(): array
//    {
//        $stmt = $this->connection->prepare("
//                SELECT TABLE_NAME
//                    FROM INFORMATION_SCHEMA.TABLES
//                    WHERE TABLE_SCHEMA = '{$this->schemaName}'
//            ");
//        $stmt->execute();
//        $tables = [];
//
//        while ($row = $stmt->fetch()) {
//            $tables[] = $row['TABLE_NAME'];
//        }
//
//        $stmt->closeCursor();
//
//        return $tables;
//    }
//
//    public function setSchema(array $schema): void
//    {
//        $this->schema = new Schema($schema);
//    }
//}
