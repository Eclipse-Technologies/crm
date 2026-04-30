<?php
/**
 * Equipment/Inventory Schema - Both Sales & SDI
 * Tracks all equipment (owned by customer or leased from Evoqua)
 */
return [
    'equipment_id',         // Unique identifier
    'equipment_type',       // Softener / RO System / Filtration / DI Tank / Other
    'manufacturer',         // Evoqua / Eclipse / Other
    'model_number',         // Equipment model
    'serial_number',        // Equipment serial number
    'ownership',            // Customer Owned / Evoqua Lease / Evoqua Rental
    'customer_id',          // Which customer has this equipment
    'contact_id',           // Primary contact for equipment
    'contract_id',          // Linked service contract (if SDI)
    'install_date',         // Installation date
    'purchase_date',        // Purchase date (if owned)
    'purchase_value',       // Original purchase price
    'location',             // Installation location/address
    'tank_size',            // For tanks - size in liters/gallons
    'resin_type',           // For tanks - type of resin
    'regeneration_id',      // Regeneration number
    'service_frequency',    // How often serviced
    'last_service_date',    // Most recent service
    'next_service_date',    // Scheduled next service
    'warranty_expiry',      // Warranty expiration date
    'status',               // Active / Inactive / Pending Removal / Removed
    'purchase_order',       // Original PO number
    'notes',                // Equipment notes
    'created_date',         // Record creation date
    'modified_date'         // Last modification date
];
