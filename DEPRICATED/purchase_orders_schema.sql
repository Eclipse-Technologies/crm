-- Table for purchase order headers (one row per PO)
CREATE TABLE purchase_orders (
    po_number VARCHAR(64) PRIMARY KEY,
    date DATE,
    status VARCHAR(32),
    supplier_id VARCHAR(64),
    supplier_name VARCHAR(128),
    supplier_contact VARCHAR(128),
    supplier_address VARCHAR(255),
    billing_address VARCHAR(255),
    shipping_address VARCHAR(255),
    subtotal DECIMAL(12,2),
    total_discount DECIMAL(12,2),
    total_tax DECIMAL(12,2),
    shipping_cost DECIMAL(12,2),
    other_fees DECIMAL(12,2),
    grand_total DECIMAL(12,2),
    currency VARCHAR(8),
    expected_delivery DATE,
    payment_terms VARCHAR(128),
    notes TEXT,
    created_by VARCHAR(64),
    created_at DATETIME,
    updated_at DATETIME
);

-- Table for purchase order items (one row per item, references po_number)
CREATE TABLE purchase_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(64),
    item_id VARCHAR(64),
    item_name VARCHAR(128),
    quantity DECIMAL(12,2),
    unit VARCHAR(32),
    unit_price DECIMAL(12,2),
    discount DECIMAL(12,2),
    tax_rate DECIMAL(5,2),
    tax_amount DECIMAL(12,2),
    total DECIMAL(12,2),
    FOREIGN KEY (po_number) REFERENCES purchase_orders(po_number) ON DELETE CASCADE
);
