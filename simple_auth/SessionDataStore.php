<?php
// SessionDataStore.php - MySQL session handler for CRM Auth
require_once __DIR__ . '/../db_mysql.php';

class SessionDataStore {
    private $conn;
    public function __construct() {
        $this->conn = get_mysql_connection();
    }
    public function insert($userId, $sessionToken, $ip, $userAgent, $expiresAt) {
        // Delete any existing session with this token to avoid duplicate key error
        $del = $this->conn->prepare("DELETE FROM sessions WHERE session_token = ?");
        $del->bind_param('s', $sessionToken);
        $del->execute();
        $del->close();
        $stmt = $this->conn->prepare("INSERT INTO sessions (user_id, session_token, ip_address, user_agent, expires_at, last_activity) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('issss', $userId, $sessionToken, $ip, $userAgent, $expiresAt);
        $stmt->execute();
        $stmt->close();
    }
    public function fetchOne($sessionToken, $userId) {
        $stmt = $this->conn->prepare("SELECT * FROM sessions WHERE session_token = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param('si', $sessionToken, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row;
    }
    public function delete($sessionToken) {
        $stmt = $this->conn->prepare("DELETE FROM sessions WHERE session_token = ?");
        $stmt->bind_param('s', $sessionToken);
        $stmt->execute();
        $stmt->close();
    }
    public function refresh($sessionToken, $userId, $expiresAt) {
        $stmt = $this->conn->prepare("UPDATE sessions SET last_activity = NOW(), expires_at = ? WHERE session_token = ? AND user_id = ?");
        $stmt->bind_param('ssi', $expiresAt, $sessionToken, $userId);
        $stmt->execute();
        $stmt->close();
    }
    public function rotateToken($oldSessionToken, $newSessionToken, $userId, $expiresAt) {
        $stmt = $this->conn->prepare("UPDATE sessions SET session_token = ?, last_activity = NOW(), expires_at = ? WHERE session_token = ? AND user_id = ?");
        $stmt->bind_param('sssi', $newSessionToken, $expiresAt, $oldSessionToken, $userId);
        $stmt->execute();
        $stmt->close();
    }
}
?>
