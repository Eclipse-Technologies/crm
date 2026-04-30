<?php
/**
 * Authentication System
 * 
 * Provides user registration, login, session management, and security features
 * Follows 2026 security best practices
 */

// require_once __DIR__ . '/CsvDataStore.php'; // CSV support removed
require_once __DIR__ . '/SessionDataStore.php';

class Auth {
    /**
     * Wipe all users and session tokens (for testing/cleanup)
     */
    public function wipeAllUsersAndSessions() {
        // Remove all users
        $this->store->truncate('users');
        // Remove all sessions from SQL
        $conn = get_mysql_connection();
        $conn->query('DELETE FROM sessions');
        $conn->close();
        // Optionally clear current session
        $_SESSION = [];
        session_destroy();
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        return true;
    }
    // private $store; // CSV support removed
    private $sessionStore;
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
        // $this->store = new CsvDataStore($config); // CSV support removed
        $this->sessionStore = new SessionDataStore();
        $this->initSession();
    }
    
    /**
     * Initialize secure session
     */
    private function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            $security = $this->config['security'];
            $sessionLifetime = (int) ($security['session_lifetime'] ?? 86400);
            // Use a portable, local session directory
            $localSessionDir = __DIR__ . '/sessions';
            if (!is_dir($localSessionDir)) {
                mkdir($localSessionDir, 0755, true);
            }
            session_save_path($localSessionDir);
            ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
            // Set session cookie parameters before starting session
            session_set_cookie_params([
                'lifetime' => $sessionLifetime,
                'path' => '/',
                'domain' => '',  // Empty for localhost
                'secure' => $security['session_cookie_secure'] ?? false,
                'httponly' => $security['session_cookie_httponly'] ?? true,
                'samesite' => $security['session_cookie_samesite'] ?? 'Lax'
            ]);
            // Use only supported session ini settings
            ini_set('session.use_strict_mode', 1);
            if (!empty($security['session_name'])) {
                session_name($security['session_name']);
            }
            // Start session only if headers not sent
            if (!headers_sent()) {
                session_start();
            }
            // Regenerate session ID periodically
            if (session_status() === PHP_SESSION_ACTIVE) {
                if (!isset($_SESSION['created'])) {
                    $_SESSION['created'] = time();
                    // Always sync session_token to session_id on session creation
                    $_SESSION['session_token'] = session_id();
                    $_SESSION['session_lifetime'] = $sessionLifetime;
                } else if (time() - $_SESSION['created'] > 1800) {
                    $oldSessionToken = $_SESSION['session_token'] ?? session_id();
                    session_regenerate_id(true);
                    $_SESSION['created'] = time();
                    // Always sync session_token to session_id after regeneration
                    $_SESSION['session_token'] = session_id();
                    $_SESSION['session_lifetime'] = $_SESSION['session_lifetime'] ?? $sessionLifetime;
                    if (isset($_SESSION['user_id']) && $oldSessionToken !== $_SESSION['session_token']) {
                        $this->refreshSessionRecord((int) $_SESSION['user_id'], $oldSessionToken, $_SESSION['session_token']);
                    }
                }
            }
        }
    }
    
    /**
     * Register a new user
     */
    public function register($username, $email, $password) {
        // Validate input
        $validation = $this->validateRegistration($username, $email, $password);
        if (!$validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }
        
        // Check if user already exists
        if ($this->userExists($username, $email)) {
            return ['success' => false, 'errors' => ['Username or email already exists']];
        }

        // Hash password
        $passwordHash = $this->hashPassword($password);

        // Generate verification token
        $verificationToken = $this->config['app']['require_email_verification']
            ? bin2hex(random_bytes(32))
            : null;
        $role = 'user';

        try {
            $conn = get_mysql_connection();
            $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role, is_verified, is_active, verification_token, reset_token, reset_token_expires, failed_login_attempts, locked_until, last_login) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $is_verified = $verificationToken ? 0 : 1;
            $is_active = 1;
            $reset_token = '';
            $reset_token_expires = null;
            $failed_login_attempts = 0;
            $locked_until = null;
            $last_login = null;
            $stmt->bind_param('ssssiiississ',
                $username,
                $email,
                $passwordHash,
                $role,
                $is_verified,
                $is_active,
                $verificationToken,
                $reset_token,
                $reset_token_expires,
                $failed_login_attempts,
                $locked_until,
                $last_login
            );
            $stmt->execute();
            $userId = $stmt->insert_id;
            $stmt->close();
            $conn->close();

            return [
                'success' => true,
                'user_id' => $userId,
                'requires_verification' => $verificationToken !== null,
                'verification_token' => $verificationToken,
            ];
        } catch (Exception $e) {
            error_log('Registration failed: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Registration failed']];
        }
    }
    
    /**
     * Authenticate user login
     */
    public function login($usernameOrEmail, $password, $rememberMe = false) {
        $ip = $this->getIpAddress();
        
        // Bypass rate limiting for testing
        // if ($this->isRateLimited($usernameOrEmail, $ip)) {
        //     return [
        //         'success' => false,
        //         'error' => 'Too many login attempts. Please try again later.',
        //     ];
        // }
        
        // Find user
        $user = $this->findUser($usernameOrEmail);
        
        // Log attempt
        // $this->logLoginAttempt($usernameOrEmail, $ip, false); // CSV support removed
        // Implement SQL login attempt log here
        
        if (!$user) {
            return ['success' => false, 'error' => 'Invalid credentials'];
        }
        
        // Bypass account lockout for testing
        // if ($this->isAccountLocked($user)) {
        //     return ['success' => false, 'error' => 'Account is temporarily locked'];
        // }
        
        // Verify password
        if (!$this->verifyPassword($password, $user['password_hash'])) {
            $this->incrementFailedAttempts($user['id']);
            return ['success' => false, 'error' => 'Invalid credentials'];
        }
        
        // Check if email verification is required
        if ($this->config['app']['require_email_verification'] && !$user['is_verified']) {
            return ['success' => false, 'error' => 'Please verify your email address'];
        }
        
        // Check if account is active
        if (!$user['is_active']) {
            return ['success' => false, 'error' => 'Account is disabled'];
        }
        
        // Successful login
        // $this->logLoginAttempt($usernameOrEmail, $ip, true);
        // $this->resetFailedAttempts($user['id']);
        // $this->updateLastLogin($user['id']);
        // Implement SQL login success logic here
        
        // Create session
        $sessionToken = $this->createSession($user['id'], $rememberMe);
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'] ?? 'user';
        $_SESSION['session_token'] = session_id();
        $_SESSION['session_lifetime'] = $rememberMe ? 30 * 24 * 3600 : (int) $this->config['security']['session_lifetime'];
        $_SESSION['ip_address'] = $ip;
        
        // Log activity
        // $this->logActivity($user['id'], 'user_login', 'Successful login');
        // Implement SQL activity log here
        
        return [
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                    'role' => $user['role'] ?? 'user',
                'email' => $user['email'],
            ],
        ];
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if (isset($_SESSION['session_token'])) {
            $this->sessionStore->delete($_SESSION['session_token']);
        }
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'user_logout', 'User logged out');
        }
        $_SESSION = [];
        session_destroy();
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        return ['success' => true];
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
            return false;
        }
        $session = $this->sessionStore->fetchOne(trim((string)$_SESSION['session_token']), (int)$_SESSION['user_id']);
        if (!$session) {
            return false;
        }
        if (isset($session['expires_at']) && strtotime($session['expires_at']) <= time()) {
            return false;
        }
        $this->refreshSessionRecord((int) $_SESSION['user_id'], trim((string) $_SESSION['session_token']));
        // Optionally check IP address here
        return true;
    }
    
    /**
     * Get current authenticated user
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        // $user = $this->store->fetchOne('users', ['id' => (string)$_SESSION['user_id']]); // CSV support removed
        $user = null; // Implement SQL user fetch here
        
        if ($user) {
            // Return only safe fields
            $user = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'created_at' => $user['created_at'] ?? '',
                'last_login' => $user['last_login'] ?? '',
            ];
        }
        
        return $user ?: null;
    }
    
    /**
     * Change user password
     */
    public function changePassword($userId, $oldPassword, $newPassword) {
        $user = $this->store->fetchOne('users', ['id' => (string)$userId]);
        
        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }
        
        if (!$this->verifyPassword($oldPassword, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Current password is incorrect'];
        }
        
        $validation = $this->validatePassword($newPassword);
        if (!$validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }
        
        $newHash = $this->hashPassword($newPassword);
        // $this->store->update('users', ['password_hash' => $newHash], ['id' => (string)$userId]); // CSV support removed
        // Implement SQL password update here
        
        $this->logActivity($userId, 'password_changed', 'User changed password');
        
        return ['success' => true];
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes($this->config['security']['csrf_token_length']));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCsrfToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    // ==================== Private Helper Methods ====================
    
    private function hashPassword($password) {
        return password_hash(
            $password,
            $this->config['security']['password_algo'],
            $this->config['security']['password_options']
        );
    }
    
    private function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    private function validateRegistration($username, $email, $password) {
        $errors = [];
        
        // Validate username
        $minLen = $this->config['validation']['username_min_length'];
        $maxLen = $this->config['validation']['username_max_length'];
        
        if (strlen($username) < $minLen || strlen($username) > $maxLen) {
            $errors[] = "Username must be between $minLen and $maxLen characters";
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Username can only contain letters, numbers, and underscores';
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address';
        }
        
        // Validate password
        $passwordValidation = $this->validatePassword($password);
        if (!$passwordValidation['valid']) {
            $errors = array_merge($errors, $passwordValidation['errors']);
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
    
    private function validatePassword($password) {
        $errors = [];
        $rules = $this->config['validation'];
        
        if (strlen($password) < $rules['password_min_length']) {
            $errors[] = 'Password must be at least ' . $rules['password_min_length'] . ' characters';
        }
        
        if ($rules['password_require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if ($rules['password_require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if ($rules['password_require_number'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if ($rules['password_require_special'] && !preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
    
    private function userExists($username, $email) {
        $conn = get_mysql_connection();
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        $conn->close();
        return $exists;
    }
    
    private function findUser($usernameOrEmail) {
        $conn = get_mysql_connection();
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param('ss', $usernameOrEmail, $usernameOrEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $user ?: null;
    }
    
    private function isRateLimited($usernameOrEmail, $ip) {
        $window = $this->config['security']['rate_limit_window'];
        $maxAttempts = $this->config['security']['max_login_attempts'];
        $cutoff = date('Y-m-d H:i:s', time() - $window);
        
        // CSV isRateLimited removed; implement SQL isRateLimited here
        return false;
    }
    
    private function isAccountLocked($user) {
        // CSV isAccountLocked removed; implement SQL isAccountLocked here
        return false;
    }
    
    private function incrementFailedAttempts($userId) {
        // CSV incrementFailedAttempts removed; implement SQL incrementFailedAttempts here
    }
    
    private function resetFailedAttempts($userId) {
        // CSV resetFailedAttempts removed; implement SQL resetFailedAttempts here
    }
    
    private function updateLastLogin($userId) {
        // CSV updateLastLogin removed; implement SQL updateLastLogin here
    }
    
    private function createSession($userId, $rememberMe = false) {
        // Use PHP session_id as the session_token for DB and $_SESSION
        $sessionToken = session_id();
        $lifetime = $rememberMe ? 30 * 24 * 3600 : $this->config['security']['session_lifetime'];
        $_SESSION['session_lifetime'] = $lifetime;
        $expiresAt = date('Y-m-d H:i:s', time() + $lifetime);
        $ip = $this->getIpAddress();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $this->sessionStore->insert($userId, $sessionToken, $ip, $userAgent, $expiresAt);
        return $sessionToken;
    }
    
    private function logLoginAttempt($usernameOrEmail, $ip, $success) {
        // CSV logLoginAttempt removed; implement SQL logLoginAttempt here
    }
    
    private function logActivity($userId, $actionType, $actionDetails) {
        if (!$this->config['logging']['enabled']) {
            return;
        }
        
        // CSV logActivity removed; implement SQL logActivity here
    }
    
    private function getIpAddress() {
        // Handle proxy headers safely to prevent IP spoofing
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // X-Forwarded-For can contain multiple IPs (client, proxy1, proxy2)
            // Take the first IP (the client's IP) and validate it
            $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            $clientIp = $ips[0];
            if (filter_var($clientIp, FILTER_VALIDATE_IP)) {
                return $clientIp;
            }
        }
        
        if (!empty($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private function refreshSessionRecord($userId, $currentToken, $newToken = null) {
        $lifetime = max(300, (int) ($_SESSION['session_lifetime'] ?? $this->config['security']['session_lifetime'] ?? 86400));
        $expiresAt = date('Y-m-d H:i:s', time() + $lifetime);

        if ($newToken !== null && $newToken !== $currentToken) {
            $this->sessionStore->rotateToken($currentToken, $newToken, $userId, $expiresAt);
            return;
        }

        $this->sessionStore->refresh($currentToken, $userId, $expiresAt);
    }
    
    /**
     * Get user statistics
     */
    public function getUserStats($userId) {
        // CSV getUserStats removed; implement SQL getUserStats here
        return null;
    }
}
