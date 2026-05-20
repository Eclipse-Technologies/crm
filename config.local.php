<?php
// config.local.php - Non-secret fallback configuration.
// Prefer .env for all real credentials.
return [
    'local' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'dbname' => getenv('DB_NAME') ?: 'crmdb',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],
    'production' => [
        'host' => getenv('PROD_DB_HOST') ?: 'localhost',
        'dbname' => getenv('PROD_DB_NAME') ?: 'cmrdb',
        'user' => getenv('PROD_DB_USER') ?: 'crm_admin',
        'password' => getenv('PROD_DB_PASSWORD') ?: '',
    ],
    // Centralized config values
    'backup_dir' => __DIR__ . '/backups',
    'backup_retention_days' => 30,
    'backup_retention_count' => 50,
    'audit_backup_dir' => 'backups/audit/',
];
