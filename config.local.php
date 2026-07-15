<?php
// config.local.php - Non-secret fallback configuration.
// Prefer .env for all real credentials.
function crm_config_env_value(string $name, string $fallback = ''): string {
    $value = getenv($name);
    if ($value === false) {
        return $fallback;
    }

    $trimmed = trim((string) $value);
    if ($trimmed === '') {
        return $fallback;
    }

    if (preg_match('/^<[^>]+>$/i', $trimmed) === 1) {
        return $fallback;
    }

    return $trimmed;
}

return [
    'local' => [
        'host' => crm_config_env_value('DB_HOST', 'localhost'),
        'dbname' => crm_config_env_value('DB_NAME', 'crmdb'),
        'user' => crm_config_env_value('DB_USER', 'root'),
        'password' => crm_config_env_value('DB_PASSWORD', ''),
    ],
    'production' => [
        'host' => crm_config_env_value('PROD_DB_HOST', 'localhost'),
        'dbname' => crm_config_env_value('PROD_DB_NAME', 'cmrdb'),
        'user' => crm_config_env_value('PROD_DB_USER', 'crm_admin'),
        'password' => crm_config_env_value('PROD_DB_PASSWORD', ''),
    ],
    // Centralized config values
    'backup_dir' => __DIR__ . '/backups',
    'backup_retention_days' => 30,
    'backup_retention_count' => 50,
    'audit_backup_dir' => 'backups/audit/',
];
