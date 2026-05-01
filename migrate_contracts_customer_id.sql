-- Migration: Change contracts.customer_id to varchar(32) to match customers.customer_id
ALTER TABLE contracts MODIFY COLUMN customer_id varchar(32) DEFAULT NULL;

-- (Optional) Add a foreign key constraint if desired:
-- ALTER TABLE contracts ADD CONSTRAINT fk_contracts_customer_id FOREIGN KEY (customer_id) REFERENCES customers(customer_id);
