<?php

declare(strict_types=1);

namespace Eva\Database\Migrations\Generator;

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
}
