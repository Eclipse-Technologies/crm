<?php
/**
 * Contact Information Enrichment Endpoint
 * 
 * Searches web for missing contact information and returns candidate data.
 * User must approve before any changes are saved.
 * 
 * POST parameters:
 *  - contact_id: contact ID to enrich
 *  - csrf_token: CSRF token for security
 * 
 * Returns JSON:
 *  {
 *    "success": true|false,
 *    "message": "...",
 *    "found_data": { field => value },  // only fields that were blank
 *    "matches": { field => confidence },  // validation scores
 *    "errors": [...]
 *  }
 */

header('Content-Type: application/json; charset=utf-8');

require_once 'db_mysql.php';
require_once __DIR__ . '/request_guard.php';

require_post_with_csrf_json();

$contactId = trim($_POST['contact_id'] ?? '');
if (!$contactId) {
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'message' => 'Contact ID required'
  ]);
  exit;
}

$conn = get_mysql_connection();

// Fetch current contact data
$stmt = $conn->prepare('SELECT * FROM contacts WHERE contact_id = ? LIMIT 1');
if (!$stmt) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Database error: ' . $conn->error
  ]);
  $conn->close();
  exit;
}

$stmt->bind_param('s', $contactId);
$stmt->execute();
$result = $stmt->get_result();
$contact = $result->fetch_assoc();
$stmt->close();

if (!$contact) {
  http_response_code(404);
  echo json_encode([
    'success' => false,
    'message' => 'Contact not found'
  ]);
  $conn->close();
  exit;
}

$conn->close();

// ──────────────────────────────────────────────────────────────────────────────
// ENRICHMENT LOGIC
// ──────────────────────────────────────────────────────────────────────────────

$foundData = [];
$matches = [];
$errors = [];

// Fields to enrich (skip ones with existing data)
$enrichFields = [
  'phone', 'email', 'address', 'city', 'province', 'postal_code', 'country'
];

$missingFields = [];
foreach ($enrichFields as $field) {
  if (isMissingFieldValue($contact[$field] ?? null)) {
    $missingFields[] = $field;
  }
}

// Build search query from existing data
$firstName = trim($contact['first_name'] ?? '');
$lastName = trim($contact['last_name'] ?? '');
$company = trim($contact['company'] ?? '');

// Only attempt enrichment if we have something to search for
$searchQuery = '';
if ($firstName && $lastName && $company) {
  $searchQuery = "$firstName $lastName $company";
} elseif ($firstName && $lastName) {
  $searchQuery = "$firstName $lastName";
} elseif ($company) {
  $searchQuery = $company;
} else {
  echo json_encode([
    'success' => false,
    'message' => 'Insufficient data to search (need first/last name or company)',
    'errors' => ['Provide at least name or company to search']
  ]);
  exit;
}

// ──────────────────────────────────────────────────────────────────────────────
// TRY MULTIPLE DATA SOURCES
// ──────────────────────────────────────────────────────────────────────────────

// Source 1: Google Custom Search (via their free API)
// Note: requires GOOGLE_CSE_KEY and GOOGLE_CSE_ID environment variables
$googleResults = enrichFromGoogle($searchQuery);
if (!empty($googleResults)) {
  mergeEnrichedData($foundData, $matches, $googleResults, $contact, $enrichFields);
}

// Source 2: Hunter.io (email finder) - if available
// Note: requires HUNTER_API_KEY environment variable
if (empty($foundData['email'])) {
  $hunterResults = enrichFromHunter($firstName, $lastName, $company);
  if (!empty($hunterResults)) {
    mergeEnrichedData($foundData, $matches, $hunterResults, $contact, $enrichFields);
  }
}

// Source 3: Clearbit (company data) - if available
// Note: requires CLEARBIT_API_KEY environment variable
if (!empty($company) && empty($foundData['address'])) {
  $clearbitResults = enrichFromClearbit($company);
  if (!empty($clearbitResults)) {
    mergeEnrichedData($foundData, $matches, $clearbitResults, $contact, $enrichFields);
  }
}

// Source 4: Local database - similar contacts
$dbResults = enrichFromLocalDatabase($firstName, $lastName, $company);
if (!empty($dbResults)) {
  mergeEnrichedData($foundData, $matches, $dbResults, $contact, $enrichFields);
}

// ──────────────────────────────────────────────────────────────────────────────
// VALIDATE MATCHES AGAINST EXISTING DATA
// ──────────────────────────────────────────────────────────────────────────────

$validationErrors = validateEnrichedData($foundData, $contact);
if (!empty($validationErrors)) {
  $errors = array_merge($errors, $validationErrors);
}

$responseMessage = 'Found candidate data';
if (empty($foundData)) {
  if (!empty($missingFields)) {
    $responseMessage = 'No additional information found for missing fields: ' . implode(', ', $missingFields);
  } else {
    $responseMessage = 'No missing fields detected';
  }
}

// Return response
$response = [
  'success' => !empty($foundData) || !empty($errors),
  'message' => $responseMessage,
  'found_data' => $foundData,
  'missing_fields' => $missingFields,
  'matches' => $matches,
  'errors' => $errors
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;

// ──────────────────────────────────────────────────────────────────────────────
// HELPER FUNCTIONS
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Search Google Custom Search for contact info
 */
function enrichFromGoogle($query) {
  $apiKey = getenv('GOOGLE_CSE_KEY');
  $cseId = getenv('GOOGLE_CSE_ID');
  
  if (!$apiKey || !$cseId) {
    return []; // Not configured
  }
  
  $url = 'https://www.googleapis.com/customsearch/v1?' . http_build_query([
    'q' => $query . ' contact information',
    'key' => $apiKey,
    'cx' => $cseId,
    'num' => 3
  ]);
  
  $response = @file_get_contents($url);
  if (!$response) {
    return [];
  }
  
  $data = json_decode($response, true);
  if (empty($data['items'])) {
    return [];
  }
  
  // Extract potential contact info from search results
  $results = [];
  foreach ($data['items'] as $item) {
    $text = $item['snippet'] ?? '';
    
    // Simple regex patterns to extract info
    if (preg_match('/(\d{3}[-.\s]?\d{3}[-.\s]?\d{4})/', $text, $m)) {
      $results['phone'] = preg_replace('/[^\d]/', '', $m[1]);
    }
    if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $text, $m)) {
      $results['email'] = $m[1];
    }
    if (preg_match('/([\d\w\s]+),\s*([A-Z]{2})\s+(\d{5})/', $text, $m)) {
      $results['address'] = trim($m[1]);
      $results['province'] = $m[2];
      $results['postal_code'] = $m[3];
    }
  }
  
  return $results;
}

/**
 * Search Hunter.io for email address
 */
function enrichFromHunter($firstName, $lastName, $company) {
  $apiKey = getenv('HUNTER_API_KEY');
  if (!$apiKey) {
    return []; // Not configured
  }
  
  $company = trim($company);
  if (empty($company)) {
    return [];
  }
  
  // Extract domain from company name or use as-is
  $domain = preg_replace('/[^a-z0-9-]/', '', strtolower($company));
  
  $url = 'https://api.hunter.io/v2/email-finder?' . http_build_query([
    'domain' => $domain . '.com',
    'first_name' => $firstName,
    'last_name' => $lastName,
    'api_key' => $apiKey
  ]);
  
  $response = @file_get_contents($url);
  if (!$response) {
    return [];
  }
  
  $data = json_decode($response, true);
  if (empty($data['data']['email'])) {
    return [];
  }
  
  return [
    'email' => $data['data']['email'],
    'phone' => $data['data']['phone'] ?? null
  ];
}

/**
 * Search Clearbit for company information
 */
function enrichFromClearbit($company) {
  $apiKey = getenv('CLEARBIT_API_KEY');
  if (!$apiKey) {
    return []; // Not configured
  }
  
  $company = trim($company);
  if (empty($company)) {
    return [];
  }
  
  $url = 'https://company.clearbit.com/v1/domains/find?' . http_build_query([
    'name' => $company
  ]);
  
  $context = stream_context_create([
    'http' => [
      'header' => 'Authorization: Bearer ' . $apiKey
    ]
  ]);
  
  $response = @file_get_contents($url, false, $context);
  if (!$response) {
    return [];
  }
  
  $data = json_decode($response, true);
  if (empty($data)) {
    return [];
  }
  
  return [
    'phone' => $data['phone'] ?? null,
    'address' => $data['location'] ?? null,
    'country' => $data['country'] ?? null
  ];
}

/**
 * Search local database for similar contacts to correlate data
 */
function enrichFromLocalDatabase($firstName, $lastName, $company) {
  $conn = get_mysql_connection();
  
  $results = [];
  $seen = [];
  
  // Find similar contacts (by company, last name, or both)
  if (!empty($company)) {
    $stmt = $conn->prepare(
      "SELECT first_name, last_name, phone, email, address, city, province, postal_code, country 
       FROM contacts 
       WHERE company = ? AND (phone != '' OR email != '' OR address != '')
       LIMIT 5"
    );
    if ($stmt) {
      $stmt->bind_param('s', $company);
      $stmt->execute();
      $result = $stmt->get_result();
      while ($row = $result->fetch_assoc()) {
        $key = md5($row['phone'] . $row['email']);
        if (!isset($seen[$key]) && ($row['phone'] || $row['email'])) {
          $results[] = $row;
          $seen[$key] = true;
        }
      }
      $result->free();
      $stmt->close();
    }
  }
  
  if (!empty($lastName) && empty($results)) {
    $stmt = $conn->prepare(
      "SELECT first_name, last_name, phone, email, address, city, province, postal_code, country 
       FROM contacts 
       WHERE last_name = ? AND (phone != '' OR email != '' OR address != '')
       LIMIT 5"
    );
    if ($stmt) {
      $stmt->bind_param('s', $lastName);
      $stmt->execute();
      $result = $stmt->get_result();
      while ($row = $result->fetch_assoc()) {
        $key = md5($row['phone'] . $row['email']);
        if (!isset($seen[$key]) && ($row['phone'] || $row['email'])) {
          $results[] = $row;
          $seen[$key] = true;
        }
      }
      $result->free();
      $stmt->close();
    }
  }
  
  // Merge common fields from similar contacts
  $merged = [];
  $counts = [];
  
  foreach ($results as $row) {
    foreach (['phone', 'email', 'address', 'city', 'province', 'postal_code', 'country'] as $field) {
      if (!empty($row[$field])) {
        $val = $row[$field];
        $key = $field . '::' . $val;
        $counts[$key] = ($counts[$key] ?? 0) + 1;
      }
    }
  }
  
  // Return most common values
  foreach ($counts as $key => $count) {
    [$field, $val] = explode('::', $key, 2);
    if ($count >= 2 && empty($merged[$field])) { // Require at least 2 matches
      $merged[$field] = $val;
    }
  }
  
  $conn->close();
  return $merged;
}

/**
 * Merge enriched data, skipping fields that already have values
 */
function mergeEnrichedData(&$foundData, &$matches, $newData, $contact, $enrichFields) {
  foreach ($newData as $field => $value) {
    if (!in_array($field, $enrichFields)) {
      continue; // Skip fields we don't enrich
    }
    
    $currentValue = $contact[$field] ?? '';
    
    // Only add if field is missing/placeholder and candidate value is meaningful.
    if (isMissingFieldValue($currentValue) && !isMissingFieldValue($value)) {
      $foundData[$field] = $value;
      $matches[$field] = isset($matches[$field]) ? min($matches[$field] + 0.3, 1.0) : 0.7;
    }
  }
}

/**
 * Returns true for blank or placeholder values that should still be enriched.
 */
function isMissingFieldValue($value) {
  if ($value === null) {
    return true;
  }

  $normalized = strtolower(trim((string)$value));
  if ($normalized === '') {
    return true;
  }

  static $placeholders = [
    '-', '--',
    'n/a', 'na', 'none', 'null', 'unknown',
    '&mdash;', 'mdash',
    '\u2014',
    '0000-00-00'
  ];

  return in_array($normalized, $placeholders, true);
}

/**
 * Validate that enriched data doesn't contradict existing data
 */
function validateEnrichedData($foundData, $contact) {
  $errors = [];
  
  // Check if email matches pattern if one exists
  if (!empty($foundData['email']) && !empty($contact['email'])) {
    if (strtolower($foundData['email']) !== strtolower($contact['email'])) {
      $errors[] = 'Email mismatch: found ' . htmlspecialchars($foundData['email']) . ' but contact has ' . htmlspecialchars($contact['email']);
    }
  }
  
  // Check if phone matches pattern (ignore formatting)
  if (!empty($foundData['phone']) && !empty($contact['phone'])) {
    $foundPhone = preg_replace('/[^\d]/', '', $foundData['phone']);
    $existingPhone = preg_replace('/[^\d]/', '', $contact['phone']);
    if ($foundPhone !== $existingPhone) {
      $errors[] = 'Phone mismatch: found ' . htmlspecialchars($foundData['phone']) . ' but contact has ' . htmlspecialchars($contact['phone']);
    }
  }
  
  // Warn if company name found doesn't match
  if (!empty($foundData['company']) && !empty($contact['company'])) {
    if (strtolower(trim($foundData['company'])) !== strtolower(trim($contact['company']))) {
      $errors[] = 'Company mismatch: found "' . htmlspecialchars($foundData['company']) . '" but contact is in "' . htmlspecialchars($contact['company']) . '"';
    }
  }
  
  return $errors;
}
?>
