<?php
// Force correct session name before any output
require_once __DIR__ . '/csrf_helper.php';
initializeCSRFToken();
// Layout start code here
require_once __DIR__ . '/simple_auth/middleware.php';
require_once __DIR__ . '/backup_handler.php';
require_once __DIR__ . '/error_handler.php';
require_once __DIR__ . '/sanitize_helper.php';
require_once __DIR__ . '/audit_handler.php';

// Initialize CSRF token for this session
initializeCSRFToken();

// Security headers
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net; connect-src 'self' https://cdn.jsdelivr.net;");

// Disable caching for pages with sensitive data
if (strpos($_SERVER['REQUEST_URI'], 'contact') !== false) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

$pageTitle = $pageTitle ?? 'CRM';
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en-CA">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="CRM system for managing contacts and customer interactions.">
  <meta name="author" content="Eclipse Water Technologies">
  <meta name="robots" content="index, follow">
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://yourdomain.com/<?= htmlspecialchars($currentPage) ?>">
  <meta property="og:image" content="https://yourdomain.com/images/preview.png">
  <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="crm_bootstrap.css?v=20260215">
    <link rel="stylesheet" href="styles.css?v=20251002">
    <link rel="stylesheet" href="css/modern-sidebar.css?v=20260213-2">
    <link rel="stylesheet" href="css/modern-components.css?v=20260213">
  <script src="https://cdn.jsdelivr.net/npm/dompurify@latest/dist/purify.min.js"></script>
  <title><?= htmlspecialchars($pageTitle) ?></title>
</head>
<body>
<?php
// Always include navbar-sidebar
include_once 'navbar-sidebar.php';
?>

<?php
// Display error messages if present
if (isset($_GET['error'])) {
    $errorMessages = [
        // 'access_denied' admin message removed
        'not_found' => 'The requested page was not found.',
        'invalid_request' => 'Invalid request received.',
    ];
    $errorType = $_GET['error'];
    if (isset($errorMessages[$errorType])) {
        echo '<div style="background:#fee;border:1px solid #fcc;padding:15px;margin:20px;border-radius:4px;color:#c33;">';
        echo htmlspecialchars($errorMessages[$errorType]);
        echo '</div>';
    }
}

// Display success messages if present
if (isset($_GET['success'])) {
    $successMessages = [
        'updated' => '✓ Successfully updated.',
        'created' => '✓ Successfully created.',
        'deleted' => '✓ Successfully deleted.',
    ];
    $successType = $_GET['success'];
    if (isset($successMessages[$successType])) {
        echo '<div style="background:#efe;border:1px solid #cfc;padding:15px;margin:20px;border-radius:4px;color:#363;">';
        echo htmlspecialchars($successMessages[$successType]);
        echo '</div>';
    }
}
?>

<style>
    .nova-relay-shell {
        position: fixed;
        right: 22px;
        bottom: 22px;
        z-index: 10020;
        font-family: Georgia, 'Times New Roman', serif;
    }

    .nova-relay-toggle {
        width: 78px;
        height: 78px;
        border-radius: 50%;
        border: 1px solid rgba(148, 163, 184, 0.45);
        background:
            radial-gradient(circle at 35% 35%, rgba(255,255,255,0.92), rgba(160,220,255,0.18) 30%, rgba(6,22,44,0.98) 68%),
            linear-gradient(135deg, #06162c 0%, #0f2f56 52%, #04101f 100%);
        box-shadow: 0 0 0 1px rgba(125, 211, 252, 0.18), 0 0 30px rgba(56, 189, 248, 0.28), 0 18px 40px rgba(2, 8, 23, 0.42);
        color: #d7f3ff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        overflow: hidden;
        position: relative;
    }

    .nova-relay-toggle::before {
        content: '';
        position: absolute;
        inset: 8px;
        border-radius: 50%;
        border: 1px solid rgba(148, 230, 255, 0.22);
    }

    .nova-relay-toggle::after {
        content: '';
        position: absolute;
        width: 94px;
        height: 18px;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.38), transparent);
        transform: rotate(-28deg) translateY(-6px);
        animation: novaSweep 4.8s linear infinite;
        opacity: 0.75;
    }

    .nova-relay-mark {
        font-size: 12px;
        letter-spacing: 0.28em;
        text-transform: uppercase;
        text-align: center;
        line-height: 1.2;
        text-shadow: 0 0 12px rgba(125, 211, 252, 0.6);
    }

    .nova-relay-panel {
        position: absolute;
        right: 0;
        bottom: 96px;
        width: min(390px, calc(100vw - 28px));
        max-height: min(72vh, 640px);
        display: none;
        flex-direction: column;
        border-radius: 18px;
        overflow: hidden;
        border: 1px solid rgba(125, 211, 252, 0.26);
        background: linear-gradient(180deg, rgba(7, 20, 39, 0.98), rgba(5, 15, 29, 0.98));
        box-shadow: 0 24px 80px rgba(2, 8, 23, 0.55);
    }

    .nova-relay-panel.is-open {
        display: flex;
    }

    .nova-relay-header {
        padding: 16px 18px 14px;
        background:
            linear-gradient(90deg, rgba(34,197,94,0.18), rgba(56,189,248,0.22), rgba(59,130,246,0.12)),
            linear-gradient(180deg, rgba(15, 33, 57, 0.96), rgba(7, 20, 39, 0.96));
        border-bottom: 1px solid rgba(125, 211, 252, 0.18);
        color: #e0f2fe;
    }

    .nova-relay-topline {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
    }

    .nova-relay-title {
        font-size: 18px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .nova-relay-subtitle {
        margin: 0;
        font-size: 12px;
        color: rgba(224, 242, 254, 0.78);
        line-height: 1.5;
    }

    .nova-relay-close {
        background: transparent;
        color: #bae6fd;
        border: 1px solid rgba(186, 230, 253, 0.24);
        border-radius: 999px;
        width: 32px;
        height: 32px;
        cursor: pointer;
    }

    .nova-relay-status {
        margin-top: 10px;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #fde68a;
    }

    .nova-relay-messages {
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        overflow-y: auto;
        background:
            radial-gradient(circle at top, rgba(14, 165, 233, 0.06), transparent 30%),
            linear-gradient(180deg, rgba(3, 10, 20, 0.95), rgba(6, 12, 24, 0.98));
    }

    .nova-relay-msg {
        padding: 12px 14px;
        border-radius: 14px;
        font-size: 13px;
        line-height: 1.6;
        white-space: pre-wrap;
    }

    .nova-relay-msg.assistant {
        align-self: flex-start;
        max-width: 92%;
        color: #e0f2fe;
        background: linear-gradient(180deg, rgba(14, 33, 58, 0.95), rgba(8, 24, 44, 0.95));
        border: 1px solid rgba(125, 211, 252, 0.16);
    }

    .nova-relay-msg.user {
        align-self: flex-end;
        max-width: 84%;
        color: #f8fafc;
        background: linear-gradient(180deg, rgba(37, 99, 235, 0.92), rgba(30, 64, 175, 0.94));
    }

    .nova-relay-prompts {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 0 16px 14px;
        background: rgba(3, 10, 20, 0.98);
    }

    .nova-relay-prompt {
        border: 1px solid rgba(125, 211, 252, 0.2);
        background: rgba(15, 23, 42, 0.92);
        color: #bae6fd;
        border-radius: 999px;
        padding: 7px 11px;
        font-size: 11px;
        cursor: pointer;
    }

    .nova-relay-form {
        display: flex;
        gap: 10px;
        padding: 14px 16px 16px;
        background: linear-gradient(180deg, rgba(5, 15, 29, 0.98), rgba(4, 12, 23, 1));
        border-top: 1px solid rgba(125, 211, 252, 0.14);
    }

    .nova-relay-input {
        flex: 1;
        min-height: 46px;
        max-height: 120px;
        resize: vertical;
        border-radius: 14px;
        border: 1px solid rgba(125, 211, 252, 0.2);
        background: rgba(15, 23, 42, 0.92);
        color: #e2e8f0;
        padding: 12px 14px;
        font-size: 13px;
    }

    .nova-relay-input::placeholder {
        color: rgba(186, 230, 253, 0.5);
    }

    .nova-relay-send {
        min-width: 104px;
        border-radius: 14px;
        border: 1px solid rgba(125, 211, 252, 0.16);
        background: linear-gradient(135deg, #0ea5e9, #2563eb);
        color: white;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        cursor: pointer;
    }

    .nova-relay-meta {
        padding: 0 16px 12px;
        color: #94a3b8;
        font-size: 11px;
        background: rgba(4, 12, 23, 1);
    }

    @keyframes novaSweep {
        0% { transform: rotate(-28deg) translate(-88px, -12px); }
        100% { transform: rotate(-28deg) translate(86px, 12px); }
    }

    @media (max-width: 640px) {
        .nova-relay-shell { right: 12px; bottom: 12px; }
        .nova-relay-toggle { width: 68px; height: 68px; }
        .nova-relay-panel { width: min(100vw - 16px, 390px); right: 0; bottom: 82px; }
    }
</style>

<div class="nova-relay-shell" id="novaRelayShell">
    <div class="nova-relay-panel" id="novaRelayPanel" aria-live="polite">
        <div class="nova-relay-header">
            <div class="nova-relay-topline">
                <div class="nova-relay-title">Nova Relay</div>
                <button type="button" class="nova-relay-close" id="novaRelayClose" aria-label="Close Nova Relay">✕</button>
            </div>
            <p class="nova-relay-subtitle">Original retro-futuristic bridge advisor for your CRM. Ask about customers, suppliers, inventory, sales, or what to do next on this page.</p>
            <div class="nova-relay-status">No-spend mode safe: requests log without external AI calls while spend is disabled.</div>
        </div>

        <div class="nova-relay-messages" id="novaRelayMessages">
            <div class="nova-relay-msg assistant">Nova Relay online. I can help interpret the current screen, suggest next actions, or answer CRM workflow questions.</div>
        </div>

        <div class="nova-relay-prompts">
            <button type="button" class="nova-relay-prompt" data-prompt="What is the most useful next step on this page?">Next step</button>
            <button type="button" class="nova-relay-prompt" data-prompt="Summarize what this page is for.">Summarize page</button>
            <button type="button" class="nova-relay-prompt" data-prompt="What should I look at first to avoid mistakes here?">Avoid mistakes</button>
        </div>

        <form class="nova-relay-form" id="novaRelayForm">
            <textarea class="nova-relay-input" id="novaRelayInput" placeholder="Ask Nova Relay about this page, your workflow, inventory, customers, or sales."></textarea>
            <button type="submit" class="nova-relay-send" id="novaRelaySend">Transmit</button>
        </form>
        <div class="nova-relay-meta" id="novaRelayMeta">Mode: CRM advisor</div>
    </div>

    <button type="button" class="nova-relay-toggle" id="novaRelayToggle" aria-label="Open Nova Relay">
        <div class="nova-relay-mark">Nova<br>Relay</div>
    </button>
</div>

<script>
    (function () {
        var shell = document.getElementById('novaRelayShell');
        var panel = document.getElementById('novaRelayPanel');
        var toggle = document.getElementById('novaRelayToggle');
        var closeBtn = document.getElementById('novaRelayClose');
        var form = document.getElementById('novaRelayForm');
        var input = document.getElementById('novaRelayInput');
        var send = document.getElementById('novaRelaySend');
        var messages = document.getElementById('novaRelayMessages');
        var meta = document.getElementById('novaRelayMeta');
        var promptButtons = shell.querySelectorAll('.nova-relay-prompt');
        var csrfToken = <?= json_encode(getCSRFToken()) ?>;
        var pageTitle = <?= json_encode($pageTitle ?? 'CRM') ?>;
        var pageName = <?= json_encode($currentPage ?? '') ?>;

        function appendMessage(role, text) {
            var bubble = document.createElement('div');
            bubble.className = 'nova-relay-msg ' + role;
            bubble.textContent = text;
            messages.appendChild(bubble);
            messages.scrollTop = messages.scrollHeight;
        }

        function setOpen(isOpen) {
            panel.classList.toggle('is-open', isOpen);
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (isOpen) {
                window.setTimeout(function () { input.focus(); }, 60);
            }
        }

        toggle.addEventListener('click', function () {
            setOpen(!panel.classList.contains('is-open'));
        });

        closeBtn.addEventListener('click', function () {
            setOpen(false);
        });

        promptButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                input.value = btn.getAttribute('data-prompt') || '';
                setOpen(true);
            });
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var message = input.value.trim();
            if (!message) {
                return;
            }

            appendMessage('user', message);
            input.value = '';
            send.disabled = true;
            send.textContent = 'Routing';
            meta.textContent = 'Consulting Nova Relay...';

            var data = new FormData();
            data.append('action', 'general_chat');
            data.append('message', message);
            data.append('page_title', pageTitle);
            data.append('page_name', pageName);
            data.append('csrf_token', csrfToken);

            fetch('ai_endpoint.php', {
                method: 'POST',
                body: data
            })
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    if (payload.error) {
                        appendMessage('assistant', payload.error);
                        meta.textContent = 'Status: request error';
                        return;
                    }

                    appendMessage('assistant', payload.text || '(no response)');

                    var selectionLabel = payload.selection_mode === 'cheapest' ? 'chosen by cost' : 'manual selection';
                    var spendLabel = payload.spend_blocked ? ' · spend blocked' : '';
                    meta.textContent = 'via ' + (payload.provider || 'ai') + ' / ' + (payload.model || 'unknown') + ' · ' + selectionLabel + spendLabel;
                })
                .catch(function (error) {
                    appendMessage('assistant', 'Network error: ' + error.message);
                    meta.textContent = 'Status: network error';
                })
                .finally(function () {
                    send.disabled = false;
                    send.textContent = 'Transmit';
                });
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                form.requestSubmit();
            }
        });
    })();
</script>
