<?php

require_once __DIR__ . '/db_mysql.php';

function get_customer_type_defaults(): array {
    $defaults = require __DIR__ . '/customer_type_options.php';
    return is_array($defaults) ? $defaults : [];
}

function get_customer_type_options(): array {
    $options = [];

    foreach (get_customer_type_defaults() as $type) {
        $type = trim((string) $type);
        if ($type !== '') {
            $options[] = $type;
        }
    }

    $conn = get_mysql_connection();
    $sql = "SELECT DISTINCT tags FROM contacts WHERE tags IS NOT NULL AND TRIM(tags) <> '' ORDER BY tags ASC";
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $type = trim((string) ($row['tags'] ?? ''));
            if ($type !== '') {
                $options[] = $type;
            }
        }
        $result->free();
    }

    $conn->close();

    $options = array_values(array_unique($options));
    sort($options, SORT_NATURAL | SORT_FLAG_CASE);

    return $options;
}
