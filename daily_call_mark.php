<?php
require_once __DIR__ . '/db_mysql.php';
require_once __DIR__ . '/daily_call_list_helper.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$contactId = trim((string) ($_REQUEST['contact_id'] ?? ''));
$ts = trim((string) ($_REQUEST['ts'] ?? ''));
$sig = trim((string) ($_REQUEST['sig'] ?? ''));
$maxAgeSeconds = daily_call_mark_link_max_age_seconds();

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function render_page(string $title, string $message, string $tone = 'info', string $extra = ''): void {
    $color = '#1f2937';
    $bg = '#f3f4f6';
    $border = '#d1d5db';

    if ($tone === 'error') {
        $color = '#842029';
        $bg = '#f8d7da';
        $border = '#f5c2c7';
    } elseif ($tone === 'success') {
        $color = '#0f5132';
        $bg = '#d1e7dd';
        $border = '#badbcc';
    }

    echo '<!doctype html><html lang="en-CA"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . h($title) . '</title>';
    echo '<style>body{font-family:Segoe UI,Arial,sans-serif;background:#f9fafb;padding:20px;margin:0;}';
    echo '.card{max-width:560px;margin:24px auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px;box-shadow:0 8px 24px rgba(0,0,0,.05);}';
    echo '.alert{padding:12px 14px;border-radius:8px;border:1px solid ' . h($border) . ';background:' . h($bg) . ';color:' . h($color) . ';margin:12px 0;}';
    echo 'button{background:#0d6efd;color:#fff;border:none;border-radius:8px;padding:12px 14px;font-size:16px;font-weight:600;width:100%;cursor:pointer;}';
    echo 'button:hover{background:#0b5ed7;}small{color:#6b7280;display:block;margin-top:10px;}</style></head><body>';
    echo '<div class="card"><h2 style="margin-top:0;">' . h($title) . '</h2>';
    echo '<div class="alert">' . h($message) . '</div>';
    echo $extra;
    echo '</div></body></html>';
}

if (!verify_daily_call_mark_signature($contactId, $ts, $sig, $maxAgeSeconds)) {
    http_response_code(403);
    render_page('Link Invalid', 'This call update link is invalid or expired. Request a new daily call email.', 'error');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $conn = get_mysql_connection();
    ensure_daily_call_tracking_table($conn);
    $ok = mark_daily_call_contact_called($conn, $contactId, 'email_link');
    $conn->close();

    if ($ok) {
        render_page('Call Marked', 'This contact was marked as called successfully.', 'success');
    } else {
        http_response_code(500);
        render_page('Update Failed', 'The contact could not be marked as called. Please try again later.', 'error');
    }
    exit;
}

$extra = '';
$extra .= '<form method="post" action="">';
$extra .= '<input type="hidden" name="contact_id" value="' . h($contactId) . '">';
$extra .= '<input type="hidden" name="ts" value="' . h($ts) . '">';
$extra .= '<input type="hidden" name="sig" value="' . h($sig) . '">';
$extra .= '<button type="submit">Mark Called</button>';
$extra .= '</form>';
$extra .= '<small>This action updates your CRM call tracking.</small>';

render_page('Confirm Call Update', 'Tap the button below to mark this contact as called.', 'info', $extra);
