<?php

return [
    'up' => function (PDO $pdo): void
    {
        $columns = [];
        $stmt = $pdo->query(
            "SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'tickets'"
        );

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $column)
        {
            $columns[$column] = true;
        }

        if (!isset($columns['source']))
        {
            $pdo->exec("ALTER TABLE tickets ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'web' AFTER body");
        }

        if (!isset($columns['email_requester_name']))
        {
            $pdo->exec('ALTER TABLE tickets ADD COLUMN email_requester_name VARCHAR(190) NULL AFTER source');
        }

        if (!isset($columns['email_requester_email']))
        {
            $pdo->exec('ALTER TABLE tickets ADD COLUMN email_requester_email VARCHAR(190) NULL AFTER email_requester_name');
        }

        if (!isset($columns['is_pending_requester']))
        {
            $pdo->exec('ALTER TABLE tickets ADD COLUMN is_pending_requester TINYINT(1) NOT NULL DEFAULT 0 AFTER email_requester_email');
        }

        $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, username, email, password, role, auth_provider, is_active, must_reset_password, created_at)
             VALUES (:name, :username, :email, :password, :role, :auth_provider, :is_active, :must_reset_password, NOW())
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                role = VALUES(role),
                auth_provider = VALUES(auth_provider),
                is_active = VALUES(is_active),
                must_reset_password = VALUES(must_reset_password),
                updated_at = NOW()'
        );
        $stmt->execute([
            'name' => 'Email Guest',
            'username' => 'email_guest',
            'email' => 'email-guest@example.invalid',
            'password' => $passwordHash,
            'role' => 'user',
            'auth_provider' => 'local',
            'is_active' => 0,
            'must_reset_password' => 0,
        ]);
    },
];
