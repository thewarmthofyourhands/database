<?php

declare(strict_types=1);

namespace Eva\Database\Migrations\Commands;

use Eva\Console\ArgvInput;
use Eva\Database\ConnectionStoreInterface;
use Eva\Database\Migrations\Migrator;

class RollbackCommand
{
    public function __construct(
        protected readonly ConnectionStoreInterface $connectionStore,
        protected readonly Migrator $migrator,
    ) {}

    public function execute(ArgvInput $argvInput): void
    {
        $options = $argvInput->getOptions();

        if (array_key_exists('connection', $options)) {
            $this->migrator->setConnection($this->connectionStore->get($options['connection']));
        } else {
            $this->migrator->setConnection($this->connectionStore->get());
        }

        if (array_key_exists('filename', $options)) {
            $filename = $options['filename'];
            $this->migrator->rollback($filename);
        } else if (array_key_exists('class', $options)) {
            $class = $options['class'];
            $this->migrator->rollback($class);
        } else {
            $this->migrator->rollback();
        }
    }
}
