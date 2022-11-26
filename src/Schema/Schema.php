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

    /**
     * @return TableSchema[]
     */
    public function getTableSchemaList(): array
    {
        return $this->tableSchemaList;
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
