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

        if (!$columnExists($pdo, 'username'))
        {
            $pdo->exec('ALTER TABLE users ADD COLUMN username VARCHAR(100) NULL AFTER name');
        }

        if (!$columnExists($pdo, 'role'))
        {
            $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('user', 'staff', 'admin') NOT NULL DEFAULT 'user' AFTER password");
            $pdo->exec("UPDATE users SET role = CASE WHEN is_admin = 1 THEN 'admin' ELSE 'user' END");
        }

        if (!$columnExists($pdo, 'auth_provider'))
        {
            $pdo->exec("ALTER TABLE users ADD COLUMN auth_provider ENUM('local', 'ldap') NOT NULL DEFAULT 'local' AFTER role");
        }

        if (!$columnExists($pdo, 'is_active'))
        {
            $pdo->exec('ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER auth_provider');
        }

        if (!$columnExists($pdo, 'must_reset_password'))
        {
            $pdo->exec('ALTER TABLE users ADD COLUMN must_reset_password TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active');
        }

        if (!$columnExists($pdo, 'directory_guid'))
        {
            $pdo->exec('ALTER TABLE users ADD COLUMN directory_guid VARCHAR(190) NULL AFTER must_reset_password');
        }

        if (!$columnExists($pdo, 'directory_domain'))
        {
            $pdo->exec('ALTER TABLE users ADD COLUMN directory_domain VARCHAR(190) NULL AFTER directory_guid');
        }

        if (!$columnExists($pdo, 'directory_username'))
        {
            $pdo->exec('ALTER TABLE users ADD COLUMN directory_username VARCHAR(190) NULL AFTER directory_domain');
        }

        if (!$columnExists($pdo, 'directory_dn'))
        {
            $pdo->exec('ALTER TABLE users ADD COLUMN directory_dn VARCHAR(500) NULL AFTER directory_username');
        }

        if (!$columnExists($pdo, 'directory_synced_at'))
        {
            $pdo->exec('ALTER TABLE users ADD COLUMN directory_synced_at DATETIME NULL AFTER directory_dn');
        }

        if (!$columnExists($pdo, 'last_login_at'))
        {
            $pdo->exec('ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER directory_synced_at');
        }

        $users = $pdo->query('SELECT id, name, email, username FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $usedUsernames = [];

        foreach ($users as $user)
        {
            if (!empty($user['username']))
            {
                $usedUsernames[strtolower($user['username'])] = true;
                continue;
            }

            $source = $user['email'] ?: $user['name'] ?: 'user' . $user['id'];
            $source = explode('@', $source)[0];
            $base = strtolower((string)preg_replace('/[^a-zA-Z0-9._-]+/', '.', $source));
            $base = trim($base, '.-_');
            $base = substr($base, 0, 80) ?: 'user' . $user['id'];
            $username = $base;
            $counter = 2;

            while (isset($usedUsernames[strtolower($username)]))
            {
                $username = substr($base, 0, 90) . $counter;
                $counter++;
            }

            $stmt = $pdo->prepare('UPDATE users SET username = :username WHERE id = :id');
            $stmt->execute([
                'username' => $username,
                'id' => $user['id'],
            ]);

            $usedUsernames[strtolower($username)] = true;
        }

        $pdo->exec('ALTER TABLE users MODIFY username VARCHAR(100) NOT NULL');
        $pdo->exec('ALTER TABLE users MODIFY email VARCHAR(190) NULL');
        $pdo->exec('ALTER TABLE users MODIFY password VARCHAR(255) NULL');

        if (!$indexExists($pdo, 'uq_users_username'))
        {
            $pdo->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_username (username)');
        }

        if (!$indexExists($pdo, 'idx_users_role'))
        {
            $pdo->exec('ALTER TABLE users ADD INDEX idx_users_role (role)');
        }

        if (!$indexExists($pdo, 'idx_users_auth_provider'))
        {
            $pdo->exec('ALTER TABLE users ADD INDEX idx_users_auth_provider (auth_provider)');
        }

        if (!$indexExists($pdo, 'idx_users_directory_guid'))
        {
            $pdo->exec('ALTER TABLE users ADD INDEX idx_users_directory_guid (directory_guid)');
        }

        if (!$indexExists($pdo, 'idx_users_directory_username'))
        {
            $pdo->exec('ALTER TABLE users ADD INDEX idx_users_directory_username (directory_username)');
        }
    },
];
