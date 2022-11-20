<?php

declare(strict_types=1);

namespace Eva\Database\Schema\Table;

class ColumnSchema
{
    public function __construct(
        private readonly string $name,
        private readonly null|string $comment,
        private readonly string $collate,
        private readonly string $type,
        private readonly null|string $default,
        private readonly bool $nullable = true,
        private readonly bool $autoincrement = false,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getComment(): null|string
    {
        return $this->comment;
    }

    public function getCollate(): string
    {
        return $this->collate;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDefault(): null|string
    {
        return $this->default;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function isAutoincrement(): bool
    {
        return $this->autoincrement;
    }
}
