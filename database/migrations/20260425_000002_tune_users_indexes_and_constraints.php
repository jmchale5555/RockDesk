<?php

return [
    'up' => function (PDO $pdo): void
    {
        $indexExists = static function (PDO $pdo, string $indexName): bool
        {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) AS count
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

        if (!$indexExists($pdo, 'idx_users_created_at'))
        {
            $pdo->exec('ALTER TABLE users ADD INDEX idx_users_created_at (created_at)');
        }
    },
];
