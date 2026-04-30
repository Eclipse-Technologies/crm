<?php
/**
 * Global Search API
 * Returns JSON results for contacts, customers, opportunities, etc.
 */

require_once __DIR__ . '/simple_auth/middleware.php';
require_once __DIR__ . '/csv_handler.php';
require_once __DIR__ . '/sanitize_helper.php';

// Only allow authenticated users
if (empty($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

header('Content-Type: application/json');

$query = strtolower(trim($_GET['q'] ?? ''));

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$results = [];
$maxResultsPerType = 5;

// Search Contacts
try {
    $contacts = readCSV('contacts.csv', require __DIR__ . '/contact_schema.php');
    $contactsForSearch = is_array($contacts) ? $contacts : [];
    if (!empty($contactsForSearch)) {
        foreach ($contacts as $contact) {
            if (count(array_filter($results, fn($r) => $r['type'] === 'Contacts')) >= $maxResultsPerType) {
                break;
            }
            
            $haystack = strtolower(implode(' ', [
                $contact['first_name'] ?? '',
                $contact['last_name'] ?? '',
                $contact['company'] ?? '',
                $contact['email'] ?? '',
                $contact['phone'] ?? ''
            ]));
            
            if (strpos($haystack, $query) !== false) {
                $name = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));
                $results[] = [
                    'type' => 'Contacts',
                    'title' => $name ?: 'Unnamed Contact',
                    'subtitle' => ($contact['company'] ?? '') . ' • ' . ($contact['email'] ?? ''),
                    'url' => 'contact_view.php?id=' . urlencode($contact['contact_id'] ?? $contact['id'] ?? '')
                ];
            }
        }
    }
} catch (Exception $e) {
    // Silently skip if contacts.csv can't be read
    $contactsForSearch = [];
}

// Search Customers (from contacts marked as customers)
if (!empty($contactsForSearch)) {
    foreach ($contactsForSearch as $contact) {
        if (count(array_filter($results, fn($r) => $r['type'] === 'Customers')) >= $maxResultsPerType) {
            break;
        }
        
        $isCustomer = strtolower(trim($contact['is_customer'] ?? ''));
        if (!in_array($isCustomer, ['yes', 'true', '1'], true)) {
            continue;
        }
        
        $haystack = strtolower(implode(' ', [
            $contact['company'] ?? '',
            $contact['first_name'] ?? '',
            $contact['last_name'] ?? '',
            $contact['email'] ?? ''
        ]));
        
        if (strpos($haystack, $query) !== false) {
            $name = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));
            $results[] = [
                'type' => 'Customers',
                'title' => ($contact['company'] ?? '') ?: ($name ?: 'Unnamed Customer'),
                'subtitle' => $name,
                'url' => 'customer_view.php?id=' . urlencode($contact['id'] ?? '')
            ];
        }
    }
}

// Search Opportunities
try {
    $opportunities = readCSV('opportunities.csv', require __DIR__ . '/opportunity_schema.php');
    if (is_array($opportunities)) {
        foreach ($opportunities as $opp) {
            if (count(array_filter($results, fn($r) => $r['type'] === 'Opportunities')) >= $maxResultsPerType) {
                break;
            }
            
            $haystack = strtolower(implode(' ', [
                $opp['company'] ?? '',
                $opp['stage'] ?? '',
                $opp['description'] ?? ''
            ]));
            
            if (strpos($haystack, $query) !== false) {
                $value = isset($opp['value']) ? '$' . number_format($opp['value'], 0) : '';
                $results[] = [
                    'type' => 'Opportunities',
                    'title' => ($opp['company'] ?? 'Unnamed Opportunity') . ' - ' . ($opp['stage'] ?? ''),
                    'subtitle' => $value . ' • ' . ($opp['description'] ?? ''),
                    'url' => 'opportunities_list.php?id=' . urlencode($opp['id'] ?? '')
                ];
            }
        }
    }
} catch (Exception $e) {
    // Silently skip if opportunities.csv can't be read
}

// Search Purchase Orders (if admin)
if (isAdmin()) {
    try {
        $purchase_orders = readCSV('purchase_orders.csv', require __DIR__ . '/purchase_order_schema.php');
        if (is_array($purchase_orders)) {
            foreach ($purchase_orders as $po) {
                if (count(array_filter($results, fn($r) => $r['type'] === 'Purchase Orders')) >= $maxResultsPerType) {
                    break;
                }
                
                $haystack = strtolower(implode(' ', [
                    $po['po_number'] ?? '',
                    $po['supplier'] ?? '',
                    $po['status'] ?? ''
                ]));
                
                if (strpos($haystack, $query) !== false) {
                    $results[] = [
                        'type' => 'Purchase Orders',
                        'title' => 'PO #' . ($po['po_number'] ?? ''),
                        'subtitle' => ($po['supplier'] ?? '') . ' • ' . ($po['status'] ?? ''),
                        'url' => 'purchase_order_summary.php?po=' . urlencode($po['po_number'] ?? '')
                    ];
                }
            }
        }
    } catch (Exception $e) {
        // Silently skip
    }
}

// Return results
echo json_encode(array_values($results));
