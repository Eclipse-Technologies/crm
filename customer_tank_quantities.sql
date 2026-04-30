-- Table to track tank quantities by customer and tank type
CREATE TABLE customer_tank_quantities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    item_id VARCHAR(64) NOT NULL, -- references inventory item_id
    quantity INT NOT NULL DEFAULT 1,
    notes VARCHAR(255),
    UNIQUE KEY uq_customer_tank (customer_id, item_id)
);

-- Example usage:
-- INSERT INTO customer_tank_quantities (customer_id, item_id, quantity) VALUES (123, 'TANKTYPE001', 13);
