-- Task Action Log Table
CREATE TABLE IF NOT EXISTS task_action_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME NOT NULL,
    user VARCHAR(128),
    action VARCHAR(32) NOT NULL,
    task_id VARCHAR(64) NOT NULL,
    from_value VARCHAR(255),
    to_value VARCHAR(255)
);