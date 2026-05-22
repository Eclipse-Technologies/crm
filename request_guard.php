<?php
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/simple_auth/middleware.php';

function require_post_with_csrf(?string $redirectUrl = null): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        if ($redirectUrl !== null) {
            header('Location: ' . $redirectUrl);
            exit;
        }

        http_response_code(405);
        exit('Method Not Allowed');
    }

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        if ($redirectUrl !== null) {
            header('Location: ' . $redirectUrl);
            exit;
        }

        http_response_code(403);
        exit('CSRF validation failed');
    }
}

function require_post_with_csrf_json(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
        exit;
    }

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'CSRF validation failed']);
        exit;
    }
}
