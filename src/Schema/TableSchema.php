<?php

declare(strict_types=1);

namespace Eva\Database\Schema;

use Eva\Database\Schema\Table\ColumnSchema;
use Eva\Database\Schema\Table\KeySchema;

class TableSchema
{
    public function __construct(
        private string $name,
        private null|string $comment,
        private string $engine,
        private string $collation,
        /** @var ColumnSchema[] $columnSchemaList */
        private array $columnSchemaList,
        /** @var KeySchema[] $keySchemaList */
        private array $keySchemaList,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getComment(): null|string
    {
        return $this->comment;
    }

    public function getEngine(): string
    {
        return $this->engine;
    }

    public function getCollation(): string
    {
        return $this->collation;
    }

    public function getColumnSchema(string $columnName): ColumnSchema
    {
        foreach ($this->columnSchemaList as $columnSchema) {
            /** @var ColumnSchema $columnSchema */
            if ($columnName === $columnSchema->getName()) {
                return $columnSchema;
            }
        }

        throw new \RuntimeException();
    }
//
//    public function getKeySchema(string $keyName): null|KeySchema
//    {
//        foreach ($this->keySchemaList as $keySchema) {
//            /** @var KeySchema $keySchema */
//            if ($keyName === $keySchema->getName()) {
//                return $keySchema;
//            }
//        }
//
//        return null;
//    }
//
//    public function getIndexSchema(string $indexName): null|IndexSchema
//    {
//        foreach ($this->indexSchemaList as $indexSchema) {
//            /** @var IndexSchema $indexSchema */
//            if ($indexName === $indexSchema->getName()) {
//                return $indexSchema;
//            }
//        }
//
//        return null;
//    }

    /**
     * @return ColumnSchema[]
     */
    public function getColumnSchemaList(): array
    {
        return $this->columnSchemaList;
    }

    /**
     * @return KeySchema[]
     */
    public function getKeySchemaList(): array
    {
        return $this->keySchemaList;
    }

    /**
     * @return ColumnSchema[]
     */
    public function diffColumnSchemaListWithTable(self $comparedTableSchema): array
    {
        $diffColumnSchemaList = [];

        foreach ($this->getColumnSchemaList() as $columnSchema) {
            foreach ($comparedTableSchema->getColumnSchemaList() as $comparedColumnSchema) {
                if ($columnSchema->getName() === $comparedColumnSchema->getName()) {
                    $columnSchema = null;
                    break;
                }
            }

            if (null !== $columnSchema) {
                $diffColumnSchemaList[] = $columnSchema;
            }
        }

        return $diffColumnSchemaList;
    }

    /**
     * @return ColumnSchema[]
     */
    public function intersectColumnSchemaListWithTable(self $comparedTableSchema): array
    {
        $intersectColumnSchemaList = [];

        foreach ($this->getColumnSchemaList() as $columnSchema) {
            foreach ($comparedTableSchema->getColumnSchemaList() as $comparedColumnSchema) {
                if ($columnSchema->getName() === $comparedColumnSchema->getName()) {
                    $intersectColumnSchemaList[] = $columnSchema;
                }
            }
        }

        return $intersectColumnSchemaList;
    }

    /**
     * @return KeySchema[]
     */
    public function diffKeySchemaListWithTable(self $comparedTableSchema): array
    {
        $diffKeySchemaList = [];

        foreach ($this->getKeySchemaList() as $keySchema) {
            foreach ($comparedTableSchema->getKeySchemaList() as $comparedKeySchema) {
                if ($keySchema->getName() === $comparedKeySchema->getName()) {
                    $keySchema = null;
                    break;
                }
            }

            if (null !== $keySchema) {
                $diffKeySchemaList[] = $keySchema;
            }
        }

        return $diffKeySchemaList;
    }

//    /**
//     * @return KeySchema[]
//     */
//    public function intersectKeySchemaListWithTable(self $comparedTableSchema): array
//    {
//        $intersectKeySchemaList = [];
//
//        foreach ($this->getKeySchemaList() as $keySchema) {
//            foreach ($comparedTableSchema->getColumnSchemaList() as $comparedKeySchema) {
//                if ($keySchema->getName() === $comparedKeySchema->getName()) {
//                    $intersectKeySchemaList[] = $keySchema;
//                }
//            }
//        }
//
//        return $intersectKeySchemaList;
//    }
}
