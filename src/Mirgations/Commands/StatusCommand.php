<?php

declare(strict_types=1);

namespace Eva\Database\Migrations\Commands;

use Eva\Console\ArgvInput;
use Eva\Database\ConnectionStore;

class StatusCommand
{
    public function __construct(
        protected readonly ConnectionStore $connectionStore,
        protected readonly Migrator $migrator,
    ) {}

    public function execute(ArgvInput $argvInput): void
    {
        $options = $argvInput->getOptions();

        if (array_key_exists('connection', $options)) {
            $this->migrator->setConnection($this->connectionStore->get($options['connection']));
        }

        $this->migrator->status();
    }
}
