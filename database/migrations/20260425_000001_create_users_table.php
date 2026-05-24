<?php

return [
    'up' => function (PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS users (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(120) NOT NULL,
                username VARCHAR(100) NOT NULL,
                email VARCHAR(190) NULL,
                password VARCHAR(255) NULL,
                role ENUM('user', 'staff', 'admin') NOT NULL DEFAULT 'user',
                auth_provider ENUM('local', 'ldap') NOT NULL DEFAULT 'local',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                must_reset_password TINYINT(1) NOT NULL DEFAULT 0,
                directory_guid VARCHAR(190) NULL,
                directory_domain VARCHAR(190) NULL,
                directory_username VARCHAR(190) NULL,
                directory_dn VARCHAR(500) NULL,
                directory_synced_at DATETIME NULL,
                last_login_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_users_email (email),
                UNIQUE KEY uq_users_username (username),
                KEY idx_users_role (role),
                KEY idx_users_auth_provider (auth_provider),
                KEY idx_users_directory_guid (directory_guid),
                KEY idx_users_directory_username (directory_username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    },
];
