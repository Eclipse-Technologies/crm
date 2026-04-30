-- PostgreSQL table for contacts, matching your CSV schema
CREATE TABLE contacts (
    id VARCHAR(64) PRIMARY KEY,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    company VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    address VARCHAR(255),
    city VARCHAR(100),
    province VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100),
    notes TEXT,
    created_at VARCHAR(32),
    last_modified VARCHAR(32),
    is_customer VARCHAR(10),
    tank_number VARCHAR(50),
    delivery_date VARCHAR(32),
    tags VARCHAR(255)
);
