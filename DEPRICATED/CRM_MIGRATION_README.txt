# CRM Database Migration - README

## Overview
This document provides a comprehensive guide for migrating the CRM MySQL database to Zim Databases. It includes a summary of the schema, table relationships, and migration considerations. The schema is provided in crm_schema_full.txt.

## Database Tables
- audit_log: Tracks all audit events and changes.
- contacts: Stores contact information for customers and leads.
- contact_field_visibility: Controls visibility of contact fields.
- contracts: Manages contract details and relationships.
- customers: Customer records, linked to contacts.
- discussions: Discussion entries for contacts.
- discussion_log: Detailed log of discussions, with visibility and linked opportunities.
- equipment: Equipment inventory and details, linked to contracts and customers.
- inventory: Inventory items, pricing, and stock levels.
- inventory_ledger: Tracks inventory movements.
- inventory_serials: Serial numbers for inventory items.
- inventory_status_options: Status options for inventory items.
- opportunities: Sales opportunities, linked to contacts.
- purchase_orders: Purchase order records.
- purchase_order_items: Items within purchase orders.
- sessions: User session tracking.
- tasks: Task management for CRM users.

## Relationships & Constraints
- customers.contact_id → contacts.contact_id
- contracts.contact_id → contacts.contact_id
- contracts.customer_id → customers.customer_id
- equipment.customer_id → customers.customer_id
- equipment.contact_id → contacts.contact_id
- equipment.contract_id → contracts.contract_id
- discussion_log.contact_id → contacts.contact_id
- purchase_order_items.po_number → purchase_orders.po_number

## Migration Notes
- All tables use InnoDB and utf8mb4 encoding.
- Primary keys and foreign keys are defined for data integrity.
- Auto-increment fields are used for IDs where appropriate.
- Review all date/time fields for compatibility with Zim Databases.
- Text fields and varchar fields are used for flexible data storage.
- Indexes are defined for performance and relational integrity.

## Steps for Migration
1. Review crm_schema_full.txt for complete table definitions.
2. Map MySQL data types to Zim Databases equivalents.
3. Recreate tables and relationships in Zim Databases.
4. Import data, ensuring integrity and compatibility.
5. Test queries and application logic for correctness.

## Contact
For questions or support, contact Robert Lee (robertja98@gmail.com).

---

*Prepared for Zim Databases migration expert, February 26, 2026.*
