<?php

return [
    'up' => function (PDO $pdo): void
    {
        $columnExists = static function (PDO $pdo, string $columnName): bool
        {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name
                   AND COLUMN_NAME = :column_name'
            );
            $stmt->execute([
                'table_name' => 'users',
                'column_name' => $columnName,
            ]);

            return (int)$stmt->fetchColumn() > 0;
        };

        $indexExists = static function (PDO $pdo, string $indexName): bool
        {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name
                   AND INDEX_NAME = :index_name'
            );
            $stmt->execute([
                'table_name' => 'users',
                'index_name' => $indexName,
            ]);

            return (int)$stmt->fetchColumn() > 0;
        };

        $constraintExists = static function (PDO $pdo, string $constraintName): bool
        {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name
                   AND CONSTRAINT_NAME = :constraint_name'
            );
            $stmt->execute([
                'table_name' => 'users',
                'constraint_name' => $constraintName,
            ]);

            return (int)$stmt->fetchColumn() > 0;
        };

        if (!$columnExists($pdo, 'is_admin'))
        {
            return;
        }

        $pdo->exec("UPDATE users SET role = 'admin' WHERE is_admin = 1 AND role != 'admin'");

        if ($constraintExists($pdo, 'chk_users_is_admin'))
        {
            $pdo->exec('ALTER TABLE users DROP CONSTRAINT chk_users_is_admin');
        }

        if ($indexExists($pdo, 'idx_users_is_admin'))
        {
            $pdo->exec('ALTER TABLE users DROP INDEX idx_users_is_admin');
        }

        $pdo->exec('ALTER TABLE users DROP COLUMN is_admin');
    },
];
