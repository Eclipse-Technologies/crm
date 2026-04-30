<?php
require_once 'db_mysql.php';

function calculateForecasts() {
    $schema = require __DIR__ . '/opportunity_schema.php';
    $conn = get_mysql_connection();
    $fields = implode(',', array_map(function($f) { return '`' . $f . '`'; }, $schema));
    $sql = "SELECT $fields FROM opportunities";
    $result = $conn->query($sql);
    $opportunities = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $opportunities[] = $row;
        }
        $result->free();
    }
    $conn->close();
    $results = [];
    $stageTotals = [];
    foreach ($opportunities as $opp) {
        $value = floatval($opp['value']);
        $probability = floatval($opp['probability']);
        $forecast = round($value * ($probability / 100), 2);
        $stage = $opp['stage'] ?? 'Unspecified';
        // Add to grouped stage totals
        if (!isset($stageTotals[$stage])) {
            $stageTotals[$stage] = ['count' => 0, 'total_forecast' => 0];
        }
        $stageTotals[$stage]['count']++;
        $stageTotals[$stage]['total_forecast'] += $forecast;
        // Add to individual forecast list
        $results[] = [
            'id' => $opp['id'] ?? '',
            'contact_id' => $opp['contact_id'] ?? '',
            'stage' => $stage,
            'value' => $value,
            'forecast' => $forecast
        ];
    }
    return [
        'individual' => $results,
        'by_stage' => $stageTotals
    ];
}
?>
