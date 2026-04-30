<?php
return [
  'customer_id',
  'contact_id', // Foreign key to contacts.id
  'address',
  'customer_owned_tanks', // Comma-separated tank IDs
  'rented_tanks', // Comma-separated tank IDs
  // tank_count removed: derived from equipment table (COUNT by ownership)
  // 'tank_size', // Removed: not present in DB schema
  'last_delivery',
  'last_modified'
];
