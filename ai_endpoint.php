<?php
/**
 * ai_endpoint.php — AJAX endpoint for AI-assisted features across all CRM pages.
 *
 * POST only. Returns JSON.
 * All requests must include a valid CSRF token.
 *
 * Supported actions:
 *   suggest_followup   — Draft a follow-up message for a contact
 *   summarise_contact  — One-paragraph summary of a contact's history
 *   suggest_next_step  — Recommended next action for an opportunity
 *   inventory_insight  — Plain-English summary of an inventory item's movement
 *   general_chat       — Freeform CRM chat via the global AI widget
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once 'simple_auth/middleware.php';
require_once 'db_mysql.php';
require_once 'csrf_helper.php';
require_once 'ai_helper.php';

// CSRF validation
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token']);
    exit;
}

$conn   = get_mysql_connection();
$action = trim($_POST['action'] ?? '');

switch ($action) {

    // ── Follow-up message draft ───────────────────────────────────────────────
    case 'suggest_followup': {
        $contactId = trim($_POST['contact_id'] ?? '');
        if ($contactId === '') {
            echo json_encode(['error' => 'Missing contact_id']); exit;
        }

        $contact = ai_fetch_contact($conn, $contactId);
        if (!$contact) {
            echo json_encode(['error' => 'Contact not found']); exit;
        }

        $discussions  = ai_fetch_recent_discussions($conn, $contactId, 5);
        $opportunities = ai_fetch_open_opportunities($conn, $contactId);

        $context = ai_build_contact_context($contact, $discussions, $opportunities);
        $system  = 'You are a helpful CRM assistant. Write professional, concise, friendly business communication. Keep responses under 200 words.';
        $prompt  = "Based on the following customer profile and recent interaction history, draft a short follow-up message (email or phone call opening). Be warm but professional.\n\n{$context}";

        $result = ai_complete($conn, $prompt, $system, 400, [
            'request_kind' => 'suggest_followup',
            'metadata' => ['contact_id' => $contactId],
        ]);
        echo json_encode($result);
        break;
    }

    // ── Contact summary ───────────────────────────────────────────────────────
    case 'summarise_contact': {
        $contactId = trim($_POST['contact_id'] ?? '');
        if ($contactId === '') {
            echo json_encode(['error' => 'Missing contact_id']); exit;
        }

        $contact      = ai_fetch_contact($conn, $contactId);
        if (!$contact) {
            echo json_encode(['error' => 'Contact not found']); exit;
        }

        $discussions   = ai_fetch_recent_discussions($conn, $contactId, 10);
        $opportunities = ai_fetch_open_opportunities($conn, $contactId);

        $context = ai_build_contact_context($contact, $discussions, $opportunities);
        $system  = 'You are a concise CRM analyst. Summarise customer profiles in 2-3 sentences covering: who they are, their current status, and key action needed.';
        $prompt  = "Summarise this customer in 2-3 sentences:\n\n{$context}";

        $result = ai_complete($conn, $prompt, $system, 200, [
            'request_kind' => 'summarise_contact',
            'metadata' => ['contact_id' => $contactId],
        ]);
        echo json_encode($result);
        break;
    }

    // ── Next step for an opportunity ─────────────────────────────────────────
    case 'suggest_next_step': {
        $oppId = (int) ($_POST['opportunity_id'] ?? 0);
        if ($oppId <= 0) {
            echo json_encode(['error' => 'Missing opportunity_id']); exit;
        }

        $stmt = $conn->prepare('SELECT * FROM opportunities WHERE opportunity_id = ? LIMIT 1');
        if (!$stmt) { echo json_encode(['error' => 'DB error']); exit; }
        $stmt->bind_param('i', $oppId);
        $stmt->execute();
        $opp = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$opp) {
            echo json_encode(['error' => 'Opportunity not found']); exit;
        }

        $contactId  = $opp['contact_id'] ?? '';
        $contact    = $contactId ? ai_fetch_contact($conn, $contactId) : null;
        $discussions = $contactId ? ai_fetch_recent_discussions($conn, $contactId, 5) : [];

        $oppDetails  = "Opportunity: {$opp['stage']} stage";
        if (!empty($opp['value']))          $oppDetails .= ", value: \${$opp['value']}";
        if (!empty($opp['probability']))    $oppDetails .= ", probability: {$opp['probability']}%";
        if (!empty($opp['expected_close'])) $oppDetails .= ", expected close: {$opp['expected_close']}";

        $contactCtx = $contact ? "Contact: {$contact['first_name']} {$contact['last_name']}" . (!empty($contact['company']) ? " ({$contact['company']})" : '') : '';

        $recentDisc = '';
        if (!empty($discussions)) {
            $lines = array_map(fn($d) => '- [' . ($d['timestamp'] ?? '') . '] ' . mb_substr($d['entry_text'] ?? '', 0, 120), array_slice($discussions, 0, 3));
            $recentDisc = "Recent notes:\n" . implode("\n", $lines);
        }

        $prompt = "Suggest the single best next action for this sales opportunity. Be specific and actionable in 1-2 sentences.\n\n{$oppDetails}\n{$contactCtx}\n{$recentDisc}";
        $system = 'You are a sales coach. Give a short, specific, actionable recommendation.';

        $result = ai_complete($conn, $prompt, $system, 150, [
            'request_kind' => 'suggest_next_step',
            'metadata' => ['opportunity_id' => $oppId, 'contact_id' => $contactId],
        ]);
        echo json_encode($result);
        break;
    }

    // ── Inventory item insight ────────────────────────────────────────────────
    case 'inventory_insight': {
        $itemId = trim($_POST['item_id'] ?? '');
        if ($itemId === '') {
            echo json_encode(['error' => 'Missing item_id']); exit;
        }

        // Fetch item
        $stmt = $conn->prepare('SELECT * FROM inventory WHERE item_id = ? LIMIT 1');
        if (!$stmt) { echo json_encode(['error' => 'DB error']); exit; }
        $stmt->bind_param('s', $itemId);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$item) { echo json_encode(['error' => 'Item not found']); exit; }

        // Fetch recent movements (canonical first, fallback legacy)
        $movements = ai_fetch_item_movements($conn, $itemId, 10);

        $itemDesc = "Item: {$item['item_name']}, Current stock: {$item['quantity_in_stock']}, Status: {$item['status']}";
        if (!empty($item['category'])) $itemDesc .= ", Category: {$item['category']}";

        $movDesc = '';
        if (!empty($movements)) {
            $lines = array_map(fn($m) => '- [' . ($m['created_at'] ?? $m['changed_at'] ?? '') . '] ' . ($m['change_type'] ?? $m['movement_type'] ?? '') . ' ' . ($m['quantity_change'] ?? $m['quantity_delta'] ?? '') . ' (was ' . ($m['quantity_before'] ?? '?') . ' → ' . ($m['quantity_after'] ?? '?') . ')' . (!empty($m['reason']) ? ': ' . $m['reason'] : ''), $movements);
            $movDesc = "Recent movements:\n" . implode("\n", $lines);
        }

        $prompt = "Give a 2-3 sentence plain-English insight about this inventory item's stock movement. Flag anything unusual (rapid depletion, inactivity, large jumps).\n\n{$itemDesc}\n{$movDesc}";
        $system = 'You are an inventory analyst. Be concise and practical.';

        $result = ai_complete($conn, $prompt, $system, 200, [
            'request_kind' => 'inventory_insight',
            'metadata' => ['item_id' => $itemId],
        ]);
        echo json_encode($result);
        break;
    }

    // ── General CRM chat widget ──────────────────────────────────────────────
    case 'general_chat': {
        $message = trim((string) ($_POST['message'] ?? ''));
        $pageTitle = trim((string) ($_POST['page_title'] ?? ''));
        $pageName = trim((string) ($_POST['page_name'] ?? ''));

        if ($message === '') {
            echo json_encode(['error' => 'Missing message']);
            exit;
        }

        $system = 'You are Nova Relay, an original retro-futuristic operations advisor for a CRM and inventory system. Speak clearly, briefly, and practically. Help with customers, sales, inventory, suppliers, contracts, and workflow decisions. Do not claim to be from any TV or film franchise.';

        $pageContext = "Current page: " . ($pageTitle !== '' ? $pageTitle : 'Unknown');
        if ($pageName !== '') {
            $pageContext .= " ({$pageName})";
        }

        $prompt = "The user is interacting with the CRM through the Nova Relay widget. Answer as a helpful in-app assistant. Keep the response compact and action-oriented unless the user asks for depth.\n\n{$pageContext}\n\nUser message:\n{$message}";

        $result = ai_complete($conn, $prompt, $system, 350, [
            'request_kind' => 'general_chat',
            'metadata' => [
                'page_title' => $pageTitle,
                'page_name' => $pageName,
            ],
        ]);
        echo json_encode($result);
        break;
    }

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . htmlspecialchars($action)]);
}

// ── Data helpers ──────────────────────────────────────────────────────────────

function ai_fetch_contact(mysqli $conn, string $contactId): ?array {
    $stmt = $conn->prepare('SELECT * FROM contacts WHERE contact_id = ? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('s', $contactId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function ai_fetch_recent_discussions(mysqli $conn, string $contactId, int $limit): array {
    $result = [];
    $stmt   = $conn->prepare(
        'SELECT timestamp, author, entry_text FROM discussion_log
         WHERE contact_id = ? ORDER BY timestamp DESC LIMIT ?'
    );
    if (!$stmt) return $result;
    $stmt->bind_param('si', $contactId, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $result[] = $row;
    $stmt->close();
    return $result;
}

function ai_fetch_open_opportunities(mysqli $conn, string $contactId): array {
    $result = [];
    $stmt   = $conn->prepare(
        "SELECT stage, value, probability, expected_close
         FROM opportunities WHERE contact_id = ? AND (stage IS NULL OR stage NOT IN ('closed_won','closed_lost')) LIMIT 5"
    );
    if (!$stmt) return $result;
    $stmt->bind_param('s', $contactId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $result[] = $row;
    $stmt->close();
    return $result;
}

function ai_fetch_item_movements(mysqli $conn, string $itemId, int $limit): array {
    // Try canonical table first
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'inventory_transactions'");
    $useCanonical = ($result && ($result->fetch_assoc()['cnt'] ?? 0) > 0);

    if ($useCanonical) {
        $stmt = $conn->prepare(
            'SELECT created_at, change_type, quantity_before, quantity_after, quantity_delta, reason
             FROM inventory_transactions WHERE item_id = ? ORDER BY created_at DESC LIMIT ?'
        );
    } else {
        $stmt = $conn->prepare(
            'SELECT changed_at, movement_type, quantity_before, quantity_after, quantity_change, reason
             FROM inventory_movements WHERE item_id = ? ORDER BY changed_at DESC LIMIT ?'
        );
    }

    $rows = [];
    if (!$stmt) return $rows;
    $stmt->bind_param('si', $itemId, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function ai_build_contact_context(array $contact, array $discussions, array $opportunities): string {
    $name    = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));
    $company = $contact['company'] ?? '';
    $email   = $contact['email']   ?? '';
    $phone   = $contact['phone']   ?? '';
    $notes   = mb_substr($contact['notes'] ?? '', 0, 300);

    $lines = ["Contact: {$name}" . ($company ? " ({$company})" : '')];
    if ($email) $lines[] = "Email: {$email}";
    if ($phone) $lines[] = "Phone: {$phone}";
    if ($notes) $lines[] = "Notes: {$notes}";

    if (!empty($opportunities)) {
        $oppLines = array_map(fn($o) => "  · {$o['stage']}" . (!empty($o['value']) ? " \${$o['value']}" : '') . (!empty($o['expected_close']) ? " (close {$o['expected_close']})" : ''), $opportunities);
        $lines[] = "Open opportunities:\n" . implode("\n", $oppLines);
    }

    if (!empty($discussions)) {
        $discLines = array_map(fn($d) => '  · [' . ($d['timestamp'] ?? '') . '] ' . mb_substr($d['entry_text'] ?? '', 0, 150), $discussions);
        $lines[] = "Recent discussions (newest first):\n" . implode("\n", $discLines);
    }

    return implode("\n", $lines);
}
