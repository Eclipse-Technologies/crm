<?php
require_once __DIR__ . '/simple_auth/middleware.php';

$vendorScript = __DIR__ . '/vendor/phpmailer/phpmailer/get_oauth_token.php';
if (!file_exists($vendorScript)) {
    http_response_code(404);
    exit('OAuth helper not available.');
}

require $vendorScript;
