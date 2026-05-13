-- Table for logging as-needed regeneration events for contracts
CREATE TABLE contract_regenerations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id VARCHAR(64) NOT NULL,
    date DATE NOT NULL,
    quantity INT DEFAULT 1,
    amount DECIMAL(10,2) NOT NULL,
    notes VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(contract_id)
);

-- Example query: Total actual revenue for a period
-- (add this to your reporting logic)
-- SELECT SUM(amount) FROM contract_regenerations WHERE date BETWEEN '2026-01-01' AND '2026-12-31';

-- Example query: Projected monthly recurring revenue
-- SELECT SUM(monthly_fee) FROM contracts WHERE contract_status = 'Active';
