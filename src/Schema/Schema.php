<?php

declare(strict_types=1);

namespace Eva\Database\Schema;

class Schema
{
    public function __construct(
        protected string $name,
        /** @var TableSchema[] $tableSchemaList */
        protected array $tableSchemaList = [],
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getTableSchema(string $name): null|TableSchema
    {
        foreach ($this->tableSchemaList as $tableSchema) {
            /** @var TableSchema $tableSchema */
            if ($name === $tableSchema->getName()) {
                return $tableSchema;
            }
        }

        return null;
    }

    /**
     * @return TableSchema[]
     */
    public function getTableSchemaList(): array
    {
        return $this->tableSchemaList;
    }

    public function getTableNameList(): array
    {
        $tableNameList = [];

        foreach ($this->tableSchemaList as $tableSchema) {
           $tableNameList[] = $tableSchema->getName();
        }

        return $tableNameList;
    }

    public function diffTableListWithSchema(Schema $schema): array
    {
        return array_diff($this->getTableNameList(), $schema->getTableNameList());
    }

    /**
     * @return TableSchema[]
     */
    public function diffWithSchema(self $schemaCompare): array
    {
        $diffTableSchemaList = [];

        foreach ($this->getTableSchemaList() as $tableSchema) {
            foreach ($schemaCompare->getTableSchemaList() as $tableSchemaCompare) {
                if ($tableSchema->getName() === $tableSchemaCompare->getName()) {
                    $tableSchema = null;
                    break;
                }
            }

            if (null !== $tableSchema) {
                $diffTableSchemaList[] = $tableSchema;
            }
        }

        return $diffTableSchemaList;
    }

    public function intersectWithSchema(self $comparedSchema): array
    {
        $intersectTableSchemaList = [];

        foreach ($this->getTableSchemaList() as $tableSchema) {
            foreach ($comparedSchema->getTableSchemaList() as $tableComparedSchema) {
                if ($tableSchema->getName() === $tableComparedSchema->getName()) {
                    $intersectTableSchemaList[] = $tableSchema;
                }
            }
        }

        return $intersectTableSchemaList;
    }
}
