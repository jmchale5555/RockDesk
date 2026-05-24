<?php

return [
    'run' => function (PDO $pdo): void
    {
        $passwordHash = password_hash('password', PASSWORD_BCRYPT);

        $stmt = $pdo->prepare(
            'INSERT INTO users (name, username, email, password, role, auth_provider, is_active, must_reset_password, created_at)
             VALUES (:name, :username, :email, :password, :role, :auth_provider, :is_active, :must_reset_password, NOW())
             ON DUPLICATE KEY UPDATE
                 name = VALUES(name),
                 username = VALUES(username),
                 password = VALUES(password),
                 role = VALUES(role),
                 auth_provider = VALUES(auth_provider),
                 is_active = VALUES(is_active),
                 must_reset_password = VALUES(must_reset_password),
                 updated_at = NOW()'
        );

        $stmt->execute([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => $passwordHash,
            'role' => 'admin',
            'auth_provider' => 'local',
            'is_active' => 1,
            'must_reset_password' => 0,
        ]);
    },
];
