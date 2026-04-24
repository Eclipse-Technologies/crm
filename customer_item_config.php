<?php
// Maps to columns in the `equipment` table.
// Tanks are treated as a pool — no individual serial tracking here.
return [
  'ownership',  // rental | customer-owned
  'tank_size',  // size in cu ft
  'location',   // installation location/address
  'status',     // Trial | Active | Pending Removal
];
