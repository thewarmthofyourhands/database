<?php

declare(strict_types=1);

namespace Eva\Database\Migrations\Generator;

use Eva\Database\Schema\Schema;
use Eva\Database\Schema\Table\Enums\KeySchemaTypeEnum;
use Eva\Database\Schema\Table\Key\Foreign\DeleteRuleEnum;
use Eva\Database\Schema\Table\Key\Foreign\UpdateRuleEnum;
use Eva\Database\Schema\Table\Key\ForeignKeySchema;
use Eva\Database\Schema\Table\Key\IndexKeySchema;
use Eva\Database\Schema\Table\Key\PrimaryKeySchema;
use Eva\Database\Schema\Table\Key\UniqueKeySchema;
use Eva\Database\Schema\TableSchema;

class MigrationGenerator
{
    public function generateNew(string $class, string $namespace): string
    {
        return <<<EOD
        <?php

        declare(strict_types=1);

        namespace $namespace;

        use Eva\Database\Migrations\AbstractMigration;

        class $class extends AbstractMigration
        {
            public function up(): void
            {
                
            }

            public function down(): void
            {
                
            }
        }

        EOD;
    }

    public function generateFromSchemas(string $class, string $namespace, Schema $configSchema, Schema $dbSchema): string
    {
        $up = $this->generateUp($configSchema, $dbSchema);
        $down = $this->generateDown($configSchema, $dbSchema);

        return <<<EOD
        <?php

        declare(strict_types=1);

        namespace $namespace;

        use Eva\Database\Migrations\AbstractMigration;

        class $class extends AbstractMigration
        {
            public function up(): void
            {
        $up
            }

            public function down(): void
            {
        $down
            }
        }

        EOD;
    }

    /**
     * @param TableSchema[] $tableSchemaListForUpdate
     * @param TableSchema[] $compareTableSchemaListForUpdate
     * @return array
     */
    private function updateTableSqlList(array $tableSchemaListForUpdate, array $compareTableSchemaListForUpdate): array
    {
        $updateTableSqlList = [];

        foreach ($tableSchemaListForUpdate as $tableSchemaForUpdate) {
            foreach ($compareTableSchemaListForUpdate as $compareTableSchemaForUpdate) {
                if ($compareTableSchemaForUpdate->getName() === $tableSchemaForUpdate->getName()) {
                    if ($tableSchemaForUpdate->getCollation() !== $compareTableSchemaForUpdate->getCollation()) {
                        $updateTableSqlList[] = "ALTER TABLE `{$tableSchemaForUpdate->getName()}` 
                        COLLATE {$tableSchemaForUpdate->getCollation()};" . PHP_EOL;
                    }

                    if ($tableSchemaForUpdate->getEngine() !== $compareTableSchemaForUpdate->getEngine()) {
                        $updateTableSqlList[] = "ALTER TABLE `{$tableSchemaForUpdate->getName()}`
                        ENGINE = {$tableSchemaForUpdate->getEngine()};" . PHP_EOL;
                    }

                    if (($tableSchemaForUpdate->getComment() ?? '') !== ($compareTableSchemaForUpdate->getComment() ?? '') && $tableSchemaForUpdate->getComment() !== $compareTableSchemaForUpdate->getComment()) {
                        $updateTableSqlList[] = "ALTER TABLE `{$tableSchemaForUpdate->getName()}`
                        COMMENT = {$tableSchemaForUpdate->getComment()};" . PHP_EOL;
                    }

                    $columnSchemaForCreateList = $tableSchemaForUpdate
                        ->diffColumnSchemaListWithTable($compareTableSchemaForUpdate);
                    $columnSchemaForDropList = $tableSchemaForUpdate
                        ->diffColumnSchemaListWithTable($compareTableSchemaForUpdate);
                    $columnSchemaForUpdateList = $tableSchemaForUpdate
                        ->intersectColumnSchemaListWithTable($compareTableSchemaForUpdate);

                    foreach ($columnSchemaForUpdateList as $columnSchemaForUpdate) {
                        $dbColumnSchema = $compareTableSchemaForUpdate->getColumnSchema($columnSchemaForUpdate->getName());

                        if (
                            $columnSchemaForUpdate->isAutoincrement() !== $dbColumnSchema->isAutoincrement()
                            || $columnSchemaForUpdate->isNullable() !== $dbColumnSchema->isNullable()
                            || $columnSchemaForUpdate->getCollate() !== $dbColumnSchema->getCollate()
                            || $columnSchemaForUpdate->getType() !== $dbColumnSchema->getType()
                            || $columnSchemaForUpdate->getDefault() !== $dbColumnSchema->getDefault()
                            || ($columnSchemaForUpdate->getComment() ?? '') !== ($dbColumnSchema->getComment() ?? '')
                        ) {
                            $autoincrementSql = false === $columnSchemaForUpdate->isAutoincrement() ? '' : "AUTO_INCREMENT";
                            $collateSql = null === $columnSchemaForUpdate->getCollate() ?
                                '' :
                                "COLLATE {$columnSchemaForUpdate->getCollate()}";
                            $nullableSql = $columnSchemaForUpdate->isNullable() ? 'NULL' : 'NOT NULL';

                            if (is_string($columnSchemaForUpdate->getDefault())) {
                                $defaultSql = 'DEFAULT \'' . $columnSchemaForUpdate->getDefault() . '\'';
                            } else {
                                $defaultSql = $columnSchemaForUpdate->getDefault() ?? '';
                            }

                            $commentSql =
                                null === $columnSchemaForUpdate->getComment() ||
                                ''  === $columnSchemaForUpdate->getComment() ?
                                    '' :
                                    "COMMENT '{$columnSchemaForUpdate->getComment()}'";
                            $updateTableSqlList[] = <<<EOD
                            ALTER TABLE `{$tableSchemaForUpdate->getName()}`
                            MODIFY `{$columnSchemaForUpdate->getName()}` {$columnSchemaForUpdate->getType()}
                            $collateSql $autoincrementSql $defaultSql $nullableSql $commentSql;
                            EOD;
                        }
                    }

                    foreach ($columnSchemaForCreateList as $columnSchemaForCreate) {
                        $autoincrementSql = false === $columnSchemaForCreate->isAutoincrement() ? '' : "AUTO_INCREMENT";
                        $collateSql = null === $columnSchemaForCreate->getCollate() ?
                            '' :
                            "COLLATE {$columnSchemaForCreate->getCollate()}";
                        $nullableSql = $columnSchemaForCreate->isNullable() ? 'NULL' : 'NOT NULL';

                        if (true === is_string($columnSchemaForCreate->getDefault())) {
                            $defaultSql = 'DEFAULT \'' . $columnSchemaForCreate->getDefault() . '\'';
                        } else {
                            $defaultSql = $columnSchemaForCreate->getDefault() ?? '';
                        }

                        $commentSql = null === $columnSchemaForCreate->getComment() ?
                            '' :
                            "COMMENT '{$columnSchemaForCreate->getComment()}'";
                        $sql = "ALTER TABLE `{$tableSchemaForUpdate->getName()}`
                            ADD {$columnSchemaForCreate->getName()} {$columnSchemaForCreate->getType()} $collateSql
                            $autoincrementSql $defaultSql $nullableSql $commentSql;";
                        $updateTableSqlList[] = $sql;
                    }

                    foreach ($columnSchemaForDropList as $columnSchemaForDrop) {
                        $updateTableSqlList[] = <<<EOD
                        ALTER TABLE `{$tableSchemaForUpdate->getName()}`
                        DROP COLUMN `{$columnSchemaForDrop->getName()}`;
                        EOD;
                    }

                    $keySchemaForCreateList = $tableSchemaForUpdate
                        ->diffKeySchemaListWithTable($compareTableSchemaForUpdate);
                    $keySchemaForDropList = $compareTableSchemaForUpdate
                        ->diffKeySchemaListWithTable($tableSchemaForUpdate);

                    foreach ($keySchemaForDropList as $keySchemaForDrop) {
                        if ($keySchemaForDrop->getType() !== KeySchemaTypeEnum::PRIMARY) {
                            $updateTableSqlList[] = <<<EOD
                            ALTER TABLE `{$tableSchemaForUpdate->getName()}`
                            DROP KEY `{$keySchemaForDrop->getName()}`;
                            EOD;
                        } else {
                            $updateTableSqlList[] = <<<EOD
                            ALTER TABLE `{$tableSchemaForUpdate->getName()}`
                            DROP PRIMARY KEY;
                            EOD;
                        }
                    }

                    foreach ($keySchemaForCreateList as $keySchemaForCreate) {
                        if ($keySchemaForCreate->getType() === KeySchemaTypeEnum::PRIMARY) {
                            if (!$keySchemaForCreate instanceof PrimaryKeySchema) {
                                throw new \RuntimeException();
                            }

                            $updateTableSqlList[] = "ALTER TABLE `{$tableSchemaForUpdate->getName()}` 
                                    ADD CONSTRAINT {$tableSchemaForUpdate->getName()}_pk 
                                    PRIMARY KEY ({$keySchemaForCreate->getColumn()});";
                        } else if ($keySchemaForCreate->getType() === KeySchemaTypeEnum::UNIQUE) {
                            if (!$keySchemaForCreate instanceof UniqueKeySchema) {
                                throw new \RuntimeException();
                            }

                            $sql = "ALTER TABLE `{$tableSchemaForUpdate->getName()}` 
                                    ADD CONSTRAINT {$keySchemaForCreate->getName()}";
                            $columnSchemaList = $keySchemaForCreate->getColumnSchemaList();
                            $columnStrList = [];

                            foreach ($columnSchemaList as $columnSchema) {
                                $columnStrList[] = $columnSchema->getName() . ' ' . $columnSchema->getOrder()->value;
                            }

                            $columnListStr = implode(', ', $columnStrList);
                            $sql .= " UNIQUE KEY ($columnListStr)";
                            $sql .= " USING {$keySchemaForCreate->getEngine()->value};";
                            $updateTableSqlList[] = $sql;
                        } else if ($keySchemaForCreate->getType() === KeySchemaTypeEnum::FOREIGN) {
                            if (!$keySchemaForCreate instanceof ForeignKeySchema) {
                                throw new \RuntimeException();
                            }

                            $sql = "ALTER TABLE `{$tableSchemaForUpdate->getName()}` 
                                    ADD CONSTRAINT {$keySchemaForCreate->getName()}
                                    FOREIGN KEY ({$keySchemaForCreate->getColumn()})
                                    REFERENCES `{$keySchemaForCreate->getReferenceTableName()}` 
                                    ({$keySchemaForCreate->getReferenceColumnName()})";
                            if (UpdateRuleEnum::CASCADE === $keySchemaForCreate->getUpdateRule()) {
                                $sql .= ' ON UPDATE CASCADE';
                            }
                            if (UpdateRuleEnum::NULL === $keySchemaForCreate->getUpdateRule()) {
                                $sql .= ' ON UPDATE SET NULL';
                            }
                            if (DeleteRuleEnum::CASCADE === $keySchemaForCreate->getDeleteRule()) {
                                $sql .= ' ON DELETE CASCADE';
                            }
                            if (DeleteRuleEnum::NULL === $keySchemaForCreate->getDeleteRule()) {
                                $sql .= ' ON DELETE SET NULL';
                            }
                            $sql .= ";";
                            $updateTableSqlList[] = $sql;
                        } else if ($keySchemaForCreate->getType() === KeySchemaTypeEnum::INDEX) {
                            if (!$keySchemaForCreate instanceof IndexKeySchema) {
                                throw new \RuntimeException();
                            }

                            $sql = "ALTER TABLE `{$tableSchemaForUpdate->getName()}` 
                                    ADD CONSTRAINT {$keySchemaForCreate->getName()}";
                            $columnSchemaList = $keySchemaForCreate->getColumnSchemaList();
                            $columnStrList = [];

                            foreach ($columnSchemaList as $columnSchema) {
                                $columnStrList[] = $columnSchema->getName() . ' ' . $columnSchema->getOrder()->value;
                            }

                            $columnListStr = implode(', ', $columnStrList);
                            $sql .= " KEY ($columnListStr)";
                            $sql .= " USING {$keySchemaForCreate->getEngine()->value};";
                            $updateTableSqlList[] = $sql;
                        }
                    }
                }
            }
        }

        return $updateTableSqlList;
    }

    /**
     * @return string[]
     */
    private function dropTableSqlList(array $tableSchemaListForDrop): array
    {
        $dropTableSqlList = [];

        if (count($tableSchemaListForDrop) > 0) {
            foreach ($tableSchemaListForDrop as $tableSchemaForDrop) {
                $dropTableSqlList[] = "DROP TABLE `{$tableSchemaForDrop->getName()}`; ";
            }
        }

        return $dropTableSqlList;
    }

    /**
     * @return string[]
     */
    private function createTableSqlList(array $tableSchemaListForCreate): array
    {
        $createTableSqlList = [];

        if (count($tableSchemaListForCreate) > 0) {
            foreach ($tableSchemaListForCreate as $tableSchemaForCreate) {
                /** @var TableSchema $tableSchemaForCreate */
                $columnSchemaForCreateList = $tableSchemaForCreate->getColumnSchemaList();
                $columnSqlList = [];
                foreach ($columnSchemaForCreateList as $columnSchemaForCreate) {
                    $autoincrementSql = false === $columnSchemaForCreate->isAutoincrement() ? '' : "AUTO_INCREMENT";
                    $collateSql = null === $columnSchemaForCreate->getCollate() ?
                        '' :
                        "COLLATE `{$columnSchemaForCreate->getCollate()}`";
                    $nullableSql = $columnSchemaForCreate->isNullable() ? 'NULL' : 'NOT NULL';

                    if (is_string($columnSchemaForCreate->getDefault())) {
                        $defaultSql = 'DEFAULT \'' . $columnSchemaForCreate->getDefault() . '\'';
                    } else {
                        $defaultSql = $columnSchemaForCreate->getDefault() ?? '';
                    }

                    $commentSql = null === $columnSchemaForCreate->getComment() ?
                        '' :
                        "COMMENT '{$columnSchemaForCreate->getComment()}'";

                    $columnSqlList[] = <<<EOD
                    {$columnSchemaForCreate->getName()} {$columnSchemaForCreate->getType()} $collateSql $autoincrementSql $defaultSql $nullableSql $commentSql
                    EOD;
                }

                $keySchemaForCreateList = $tableSchemaForCreate->getKeySchemaList();
                $createKeyTableSqlList = [];

                foreach ($keySchemaForCreateList as $keySchemaForCreate) {
                    if ($keySchemaForCreate->getType() === KeySchemaTypeEnum::PRIMARY) {
                        if (!$keySchemaForCreate instanceof PrimaryKeySchema) {
                            throw new \RuntimeException();
                        }

                        $createKeyTableSqlList[] = <<<EOD
                        CONSTRAINT {$tableSchemaForCreate->getName()}_pk PRIMARY KEY ({$keySchemaForCreate->getColumn()})
                        EOD;
                    } else if ($keySchemaForCreate->getType() === KeySchemaTypeEnum::UNIQUE) {
                        if (!$keySchemaForCreate instanceof UniqueKeySchema) {
                            throw new \RuntimeException();
                        }

                        $sql = <<<EOD
                        CONSTRAINT {$keySchemaForCreate->getName()}
                        EOD;
                        $columnSchemaList = $keySchemaForCreate->getColumnSchemaList();
                        $columnStrList = [];

                        foreach ($columnSchemaList as $columnSchema) {
                            $columnStrList[] = $columnSchema->getName() . ' ' . $columnSchema->getOrder()->value;
                        }

                        $columnListStr = implode(', ', $columnStrList);
                        $sql .= " UNIQUE KEY ($columnListStr)";
                        $sql .= " USING {$keySchemaForCreate->getEngine()->value}";
                        $createKeyTableSqlList[] = $sql;
                    } else if ($keySchemaForCreate->getType() === KeySchemaTypeEnum::FOREIGN) {
                        if (!$keySchemaForCreate instanceof ForeignKeySchema) {
                            throw new \RuntimeException();
                        }

                        $sql = <<<EOD
                        CONSTRAINT {$keySchemaForCreate->getName()} FOREIGN KEY ({$keySchemaForCreate->getColumn()}) REFERENCES `{$keySchemaForCreate->getReferenceTableName()}` ({$keySchemaForCreate->getReferenceColumnName()})
                        EOD;

                        if (UpdateRuleEnum::CASCADE === $keySchemaForCreate->getUpdateRule()) {
                            $sql .= ' ON UPDATE CASCADE';
                        }
                        if (UpdateRuleEnum::NULL === $keySchemaForCreate->getUpdateRule()) {
                            $sql .= ' ON UPDATE SET NULL';
                        }
                        if (DeleteRuleEnum::CASCADE === $keySchemaForCreate->getDeleteRule()) {
                            $sql .= ' ON DELETE CASCADE';
                        }
                        if (DeleteRuleEnum::NULL === $keySchemaForCreate->getDeleteRule()) {
                            $sql .= ' ON DELETE SET NULL';
                        }
                        $createKeyTableSqlList[] = $sql;
                    } else if ($keySchemaForCreate->getType() === KeySchemaTypeEnum::INDEX) {
                        if (!$keySchemaForCreate instanceof IndexKeySchema) {
                            throw new \RuntimeException();
                        }

                        $sql = <<<EOD
                        KEY {$keySchemaForCreate->getName()}
                        EOD;
                        $columnSchemaList = $keySchemaForCreate->getColumnSchemaList();
                        $columnStrList = [];

                        foreach ($columnSchemaList as $columnSchema) {
                            $columnStrList[] = $columnSchema->getName() . ' ' . $columnSchema->getOrder()->value;
                        }

                        $columnListStr = implode(', ', $columnStrList);
                        $sql .= " ($columnListStr)";
                        $sql .= " USING {$keySchemaForCreate->getEngine()->value}";
                        $createKeyTableSqlList[] = $sql;
                    }
                }

                $keySql = implode(', '.PHP_EOL, $createKeyTableSqlList);
                $keySql = str_replace(PHP_EOL, '', $keySql);
                $columnSql = implode(', ' . PHP_EOL, $columnSqlList);
                $columnSql = str_replace(PHP_EOL, '', $columnSql);
                $createTableSqlList[] = <<<EOD
                CREATE TABLE `{$tableSchemaForCreate->getName()}` ($columnSql,$keySql) ENGINE={$tableSchemaForCreate->getEngine()} COLLATE {$tableSchemaForCreate->getCollation()};
                EOD;
            }
        }

        return $createTableSqlList;
    }

    public function generateUp(Schema $configSchema, Schema $dbSchema): string
    {
        $tableSchemaListForCreate = $configSchema->diffWithSchema($dbSchema);
        $tableSchemaListForDrop = $dbSchema->diffWithSchema($configSchema);
        $tableConfigSchemaListForUpdate = $configSchema->intersectWithSchema($dbSchema);
        $tableDbSchemaListForUpdate = $dbSchema->intersectWithSchema($configSchema);

        $createTableSqlList = $this->createTableSqlList($tableSchemaListForCreate);
        $dropTableSqlList = $this->dropTableSqlList($tableSchemaListForDrop);
        $updateTableSqlList = $this->updateTableSqlList($tableConfigSchemaListForUpdate, $tableDbSchemaListForUpdate);

        return $this->buildMigrationExecution($dropTableSqlList, $createTableSqlList, $updateTableSqlList);
    }

    public function generateDown(Schema $configSchema, Schema $dbSchema): string
    {
        $tableSchemaListForCreate = $dbSchema->diffWithSchema($configSchema);
        $tableSchemaListForDrop = $configSchema->diffWithSchema($dbSchema);
        $tableConfigSchemaListForUpdate = $configSchema->intersectWithSchema($dbSchema);
        $tableDbSchemaListForUpdate = $dbSchema->intersectWithSchema($configSchema);

        $createTableSqlList = $this->createTableSqlList($tableSchemaListForCreate);
        $dropTableSqlList = $this->dropTableSqlList($tableSchemaListForDrop);
        $updateTableSqlList = $this->updateTableSqlList($tableDbSchemaListForUpdate, $tableConfigSchemaListForUpdate);

        return $this->buildMigrationExecution($dropTableSqlList, $createTableSqlList, $updateTableSqlList);
    }

    protected function buildMigrationExecution(
        array $dropTableSqlList,
        array $createTableSqlList,
        array $updateTableSqlList
    ): string {
        $execStrList = [];

        foreach ($dropTableSqlList as $dropTableSql) {
            $dropTableSql = addcslashes($dropTableSql, "'");
            $dropTableSql = str_replace("\n",' ', $dropTableSql);
            $execStrList[] = '        ' . '$this->execute(\'' . $dropTableSql . '\');';
        }

        foreach ($createTableSqlList as $createTableSql) {
            $createTableSql = addcslashes($createTableSql, "'");
            $createTableSql = str_replace("\n",' ', $createTableSql);
            $execStrList[] = '        ' . '$this->execute(\'' . $createTableSql . '\');';
        }

        foreach ($updateTableSqlList as $updateTableSql) {
            $updateTableSql = addcslashes($updateTableSql, "'");
            $updateTableSql = str_replace("\n",' ', $updateTableSql);
            $execStrList[] = '        ' . '$this->execute(\'' . $updateTableSql . '\');';
        }

        return implode(PHP_EOL, $execStrList);
    }
}
