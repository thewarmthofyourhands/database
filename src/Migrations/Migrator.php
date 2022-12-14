<?php

declare(strict_types=1);

namespace Eva\Database\Migrations;

use Eva\Database\ConnectionInterface;
use Eva\Database\Migrations\Generator\MigrationGenerator;

class Migrator
{
    private ConnectionInterface $connection;

    public function __construct(
        private readonly array $config,
        private readonly MigrationGenerator $migrationGenerator,
    ) {}

    public function setConnection(ConnectionInterface $connection): void
    {
        $this->connection = $connection;
    }

    public function migrate(null|string $migrationFile = null): void
    {
        [$expectedMigrationList, $executedMigrationList, $notExecutedMigrationList] = $this->getMigrationData();

        if (null !== $migrationFile) {
            if (str_ends_with($migrationFile, '.php')) {
                $migrationClass = substr($migrationFile, 0, -4);
            } else {
                $migrationClass = $migrationFile;
            }

            if (true === in_array($migrationClass, $executedMigrationList, true)) {
                throw new \RuntimeException('Migration has already executed');
            }

            if (false === in_array($migrationClass, $expectedMigrationList, true)) {
                throw new \RuntimeException('Migration doesn\'t exist');
            }

            $migrationObject = new $migrationClass($this->connection);
            $migrationObject->up();
            $migrationObject->execute();
            $sql = 'insert into `migrations` (`class`) values (:class)';
            $stmt = $this->connection->prepare($sql, ['class' => $migrationClass]);
            $stmt->execute();
            $stmt->closeCursor();
            print 'Ok';

            return;
        }

        foreach ($notExecutedMigrationList as $notExecutedMigrationClass) {
            $notExecutedMigrationClass = 'Migrations\\'.$notExecutedMigrationClass;
            $migrationObject = new $notExecutedMigrationClass($this->connection);
            $migrationObject->up();
            $sql = 'insert into `migrations` (`class`) values (:class)';
            $stmt = $this->connection->prepare($sql, ['class' => $notExecutedMigrationClass]);
            $stmt->execute();
            $stmt->closeCursor();
        }

        print 'Ok';
    }

    public function rollback(null|string $migrationFile = null): void
    {
        [$expectedMigrationList, $executedMigrationList, $notExecutedMigrationList] = $this->getMigrationData();

        if (null !== $migrationFile) {
            if (str_ends_with($migrationFile, '.php')) {
                $migrationClass = substr($migrationFile, 0, -4);
            } else {
                $migrationClass = $migrationFile;
            }

            if (false === in_array($migrationClass, $expectedMigrationList, true)) {
                throw new \RuntimeException('Migration doesn\'t exist');
            }

            if (true === in_array($migrationClass, $notExecutedMigrationList, true)) {
                throw new \RuntimeException('Migration hasn\'t executed');
            }

            $migrationObject = new $migrationClass($this->connection);
            $migrationObject->down();
            $migrationObject->execute();
            $sql = 'delete from `migrations` where `class` = :class';
            $stmt = $this->connection->prepare($sql, ['class' => $migrationClass]);
            $stmt->execute();
            $stmt->closeCursor();
            print 'Ok';

            return;
        }

        $lastExecutedMigrationClass = end($executedMigrationList);
        $migrationObject = new $lastExecutedMigrationClass($this->connection);
        $migrationObject->down();
        $sql = 'delete from `migrations` where `class` = :class';
        $stmt = $this->connection->prepare($sql, ['class' => $lastExecutedMigrationClass]);
        $stmt->execute();
        $stmt->closeCursor();
        print 'Ok';
    }

    public function status(): void
    {
        [, $executedMigrationList, $notExecutedMigrationList] = $this->getMigrationData();
        $output = <<<EOD
        ------------------------------------
              Migration   |      Status
        ------------------------------------
        
        EOD;

        foreach ($executedMigrationList as $executedMigration) {
            $output .= <<<EOD
            $executedMigration | OK
            
            EOD;
        }

        foreach ($notExecutedMigrationList as $notExecutedMigration) {
            $output .= <<<EOD
            $notExecutedMigration | NOT EXECUTED
            
            EOD;
        }

        echo $output;
    }

    private function createMigrationTableIfNotExist(): void
    {
        $sql = <<<EOD
        CREATE TABLE IF NOT EXISTS `migrations` (
          `id` bigint(20) AUTO_INCREMENT NOT NULL,
          `class` varchar(100) NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `migrations_class_uindex` (`class`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        EOD;

        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        $stmt->closeCursor();
    }

    private function getMigrationData(): array
    {
        $this->createMigrationTableIfNotExist();
        $expectedMigrationList = scandir($this->config['dir']);
        $expectedMigrationList = array_filter($expectedMigrationList, static function (string $file) {
            return str_ends_with($file, '.php') && str_starts_with($file, 'Migration');
        });
        $expectedMigrationList = array_map(
            static fn (string $item) => substr($item, 0, -4),
            $expectedMigrationList
        );
        $sql = 'select * from `migrations`';
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        $executedMigrationList = [];

        while($migration = $stmt->fetch()) {
            $executedMigrationList[] = $migration['class'];
        }

        $stmt->closeCursor();
        $notExecutedMigrationList = array_filter(
            $expectedMigrationList,
            static fn (string $item): bool => false === in_array('Migrations\\' . $item, $executedMigrationList, true),
        );

        return [$expectedMigrationList, $executedMigrationList, $notExecutedMigrationList];
    }

    public function create(): void
    {
        $class = 'Migration' . time();
        $namespace = 'Migrations';
        $migrationFile = $this->migrationGenerator->generateNew($class, $namespace);
        $dir = $this->config['dir'];

        if (false === str_ends_with($dir, '/')) {
            $dir .= '/';
        }

        file_put_contents($dir . $class . '.php', $migrationFile);
    }
}
