<?php
/**
 * Output Sanitization & Escaping Helpers
 * Prevents XSS attacks by properly escaping output
 */

/**
 * Safely escape HTML special characters
 */
function escapeHtml($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Short alias for escapeHtml()
 */
function e($str) {
    return escapeHtml($str);
}

/**
 * Escape for use in HTML attributes
 */
function escapeAttr($str) {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Escape for use in JavaScript context (use with caution)
 */
function escapeJs($str) {
    // JSON encoding provides safe JavaScript escaping
    return json_encode($str, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG);
}

/**
 * Escape for JSON output
 */
function escapeJson($data) {
    return json_encode($data, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG);
}

/**
 * Sanitize user input (remove HTML tags but keep data intact)
 */
function sanitizeInput($str) {
    return trim(strip_tags((string)$str));
}

/**
 * Format currency safely
 */
if (!function_exists('formatCurrency')) {
    function formatCurrency($value) {
        return '$' . number_format((float)$value, 0);
    }
}

/**
 * Format date safely
 */
function formatDate($date) {
    if (empty($date)) {
        return '';
    }
    try {
        $dt = new DateTime($date);
        return $dt->format('M d, Y');
    } catch (Exception $e) {
        return escapeHtml($date);
    }
}
?>
