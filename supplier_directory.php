<?php
require_once 'db_mysql.php';
require_once 'csrf_helper.php';

$pageTitle = 'Supplier Directory';

function ensure_suppliers_table(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id VARCHAR(32) NOT NULL UNIQUE,
        supplier_name VARCHAR(255) NOT NULL,
        contact_name VARCHAR(255) DEFAULT NULL,
        email VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(64) DEFAULT NULL,
        address_line1 VARCHAR(255) DEFAULT NULL,
        address_line2 VARCHAR(255) DEFAULT NULL,
        city VARCHAR(120) DEFAULT NULL,
        state_province VARCHAR(120) DEFAULT NULL,
        postal_code VARCHAR(40) DEFAULT NULL,
        country VARCHAR(120) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_supplier_name (supplier_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($sql)) {
        return;
    }

    // Fallback for older MySQL/MariaDB variants that reject ON UPDATE on DATETIME.
    $fallbackSql = "CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id VARCHAR(32) NOT NULL UNIQUE,
        supplier_name VARCHAR(255) NOT NULL,
        contact_name VARCHAR(255) DEFAULT NULL,
        email VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(64) DEFAULT NULL,
        address_line1 VARCHAR(255) DEFAULT NULL,
        address_line2 VARCHAR(255) DEFAULT NULL,
        city VARCHAR(120) DEFAULT NULL,
        state_province VARCHAR(120) DEFAULT NULL,
        postal_code VARCHAR(40) DEFAULT NULL,
        country VARCHAR(120) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        INDEX idx_supplier_name (supplier_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($fallbackSql);
}

function next_supplier_code(mysqli $conn): string
{
    $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(supplier_id, '-', -1) AS UNSIGNED)) AS max_num
            FROM suppliers
            WHERE supplier_id REGEXP '^SUP-[0-9]+$'";
    $result = $conn->query($sql);
    $maxNum = 0;
    if ($result instanceof mysqli_result) {
        $row = $result->fetch_assoc();
        $maxNum = (int) ($row['max_num'] ?? 0);
        $result->free();
    }
    return 'SUP-' . str_pad((string) ($maxNum + 1), 4, '0', STR_PAD_LEFT);
}

function supplier_id_exists(mysqli $conn, string $supplierId, ?int $excludeId = null): bool
{
    if ($excludeId !== null) {
        $stmt = $conn->prepare('SELECT 1 FROM suppliers WHERE supplier_id = ? AND id <> ? LIMIT 1');
        $stmt->bind_param('si', $supplierId, $excludeId);
    } else {
        $stmt = $conn->prepare('SELECT 1 FROM suppliers WHERE supplier_id = ? LIMIT 1');
        $stmt->bind_param('s', $supplierId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $result->free();
    $stmt->close();
    return $exists;
}

function table_exists(mysqli $conn, string $tableName): bool
{
    $sql = 'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $result->free();
    $stmt->close();
    return $exists;
}

$conn = get_mysql_connection();
ensure_suppliers_table($conn);

$errors = [];
$notice = trim((string) ($_GET['notice'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

$defaultForm = [
    'id' => 0,
    'supplier_id' => next_supplier_code($conn),
    'supplier_name' => '',
    'contact_name' => '',
    'email' => '',
    'phone' => '',
    'address_line1' => '',
    'address_line2' => '',
    'city' => '',
    'state_province' => '',
    'postal_code' => '',
    'country' => '',
    'notes' => '',
    'is_active' => '1',
];
$form = $defaultForm;

if ($editId > 0) {
    $stmt = $conn->prepare('SELECT * FROM suppliers WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $result = $stmt->get_result();
        $editing = $result->fetch_assoc();
        $result->free();
        $stmt->close();
        if ($editing) {
            $form = array_merge($form, $editing);
            $form['is_active'] = (string) ($editing['is_active'] ?? 1);
        }
    } else {
        $errors[] = 'Unable to load supplier for editing: ' . $conn->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security validation failed. Please refresh and try again.';
    } else {
        $action = trim((string) ($_POST['action'] ?? 'create'));
        if ($action === 'delete') {
            $deleteId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

            if ($deleteId <= 0) {
                $errors[] = 'Invalid supplier selected for deletion.';
            } else {
                $stmt = $conn->prepare('SELECT supplier_id FROM suppliers WHERE id = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('i', $deleteId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $supplier = $result->fetch_assoc();
                    $result->free();
                    $stmt->close();

                    if (!$supplier) {
                        $errors[] = 'Supplier not found.';
                    } else {
                        $supplierIdForDelete = (string) ($supplier['supplier_id'] ?? '');
                        $usageCount = 0;

                        if (table_exists($conn, 'inventory')) {
                            $usageStmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM inventory WHERE supplier_id = ?');
                            if ($usageStmt) {
                                $usageStmt->bind_param('s', $supplierIdForDelete);
                                $usageStmt->execute();
                                $usageResult = $usageStmt->get_result();
                                $usageRow = $usageResult->fetch_assoc();
                                $usageResult->free();
                                $usageStmt->close();
                                $usageCount += (int) ($usageRow['cnt'] ?? 0);
                            }
                        }

                        if (table_exists($conn, 'purchase_orders')) {
                            $usageStmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM purchase_orders WHERE supplier_id = ?');
                            if ($usageStmt) {
                                $usageStmt->bind_param('s', $supplierIdForDelete);
                                $usageStmt->execute();
                                $usageResult = $usageStmt->get_result();
                                $usageRow = $usageResult->fetch_assoc();
                                $usageResult->free();
                                $usageStmt->close();
                                $usageCount += (int) ($usageRow['cnt'] ?? 0);
                            }
                        }

                        if ($usageCount > 0) {
                            $errors[] = 'Cannot delete this supplier because it is referenced by existing inventory or purchase orders. Set it to Inactive instead.';
                        } else {
                            $deleteStmt = $conn->prepare('DELETE FROM suppliers WHERE id = ?');
                            if ($deleteStmt) {
                                $deleteStmt->bind_param('i', $deleteId);
                                if ($deleteStmt->execute()) {
                                    $deleteStmt->close();
                                    header('Location: supplier_directory.php?notice=deleted');
                                    $conn->close();
                                    exit;
                                }
                                $errors[] = 'Unable to delete supplier: ' . $deleteStmt->error;
                                $deleteStmt->close();
                            } else {
                                $errors[] = 'Unable to prepare delete: ' . $conn->error;
                            }
                        }
                    }
                } else {
                    $errors[] = 'Unable to prepare lookup for deletion: ' . $conn->error;
                }
            }
        }

        if ($action !== 'delete') {
        $postedId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $supplierId = strtoupper(trim((string) ($_POST['supplier_id'] ?? '')));
        $supplierName = trim((string) ($_POST['supplier_name'] ?? ''));
        $contactName = trim((string) ($_POST['contact_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $address1 = trim((string) ($_POST['address_line1'] ?? ''));
        $address2 = trim((string) ($_POST['address_line2'] ?? ''));
        $city = trim((string) ($_POST['city'] ?? ''));
        $stateProvince = trim((string) ($_POST['state_province'] ?? ''));
        $postalCode = trim((string) ($_POST['postal_code'] ?? ''));
        $country = trim((string) ($_POST['country'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $isActive = ($_POST['is_active'] ?? '1') === '1' ? 1 : 0;

        $form = [
            'id' => $postedId,
            'supplier_id' => $supplierId,
            'supplier_name' => $supplierName,
            'contact_name' => $contactName,
            'email' => $email,
            'phone' => $phone,
            'address_line1' => $address1,
            'address_line2' => $address2,
            'city' => $city,
            'state_province' => $stateProvince,
            'postal_code' => $postalCode,
            'country' => $country,
            'notes' => $notes,
            'is_active' => (string) $isActive,
        ];

        if ($supplierName === '') {
            $errors[] = 'Supplier name is required.';
        }

        if ($supplierId === '') {
            $supplierId = next_supplier_code($conn);
            $form['supplier_id'] = $supplierId;
        }

        if (!preg_match('/^[A-Z0-9\-]+$/', $supplierId)) {
            $errors[] = 'Supplier ID can contain only letters, numbers, and dashes.';
        }

        $excludeId = $action === 'update' ? $postedId : null;
        if ($supplierId !== '' && supplier_id_exists($conn, $supplierId, $excludeId)) {
            $errors[] = 'Supplier ID already exists. Use a different ID.';
        }

        if (!$errors) {
            if ($action === 'update' && $postedId > 0) {
                $stmt = $conn->prepare('UPDATE suppliers SET supplier_id = ?, supplier_name = ?, contact_name = ?, email = ?, phone = ?, address_line1 = ?, address_line2 = ?, city = ?, state_province = ?, postal_code = ?, country = ?, notes = ?, is_active = ? WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param(
                        'ssssssssssssii',
                        $supplierId,
                        $supplierName,
                        $contactName,
                        $email,
                        $phone,
                        $address1,
                        $address2,
                        $city,
                        $stateProvince,
                        $postalCode,
                        $country,
                        $notes,
                        $isActive,
                        $postedId
                    );
                    if ($stmt->execute()) {
                        $stmt->close();
                        header('Location: supplier_directory.php?notice=updated');
                        $conn->close();
                        exit;
                    }
                    $errors[] = 'Unable to update supplier: ' . $stmt->error;
                    $stmt->close();
                } else {
                    $errors[] = 'Unable to prepare update: ' . $conn->error;
                }
            }

            $stmt = $conn->prepare('INSERT INTO suppliers (supplier_id, supplier_name, contact_name, email, phone, address_line1, address_line2, city, state_province, postal_code, country, notes, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param(
                    'ssssssssssssi',
                    $supplierId,
                    $supplierName,
                    $contactName,
                    $email,
                    $phone,
                    $address1,
                    $address2,
                    $city,
                    $stateProvince,
                    $postalCode,
                    $country,
                    $notes,
                    $isActive
                );
                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: supplier_directory.php?notice=created');
                    $conn->close();
                    exit;
                }
                $errors[] = 'Unable to create supplier: ' . $stmt->error;
                $stmt->close();
            } else {
                $errors[] = 'Unable to prepare insert: ' . $conn->error;
            }
        }
    }
}
    }

$listSql = 'SELECT id, supplier_id, supplier_name, contact_name, email, phone, city, country, is_active FROM suppliers';
$bindSearch = null;
if ($search !== '') {
    $listSql .= ' WHERE supplier_id LIKE ? OR supplier_name LIKE ? OR contact_name LIKE ?';
    $bindSearch = '%' . $search . '%';
}
$listSql .= ' ORDER BY supplier_name ASC, supplier_id ASC';

$suppliers = [];
$stmt = $conn->prepare($listSql);
if ($stmt) {
    if ($bindSearch !== null) {
        $stmt->bind_param('sss', $bindSearch, $bindSearch, $bindSearch);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
    $result->free();
    $stmt->close();
} else {
    $errors[] = 'Unable to load suppliers list: ' . $conn->error;
}

$conn->close();

include_once __DIR__ . '/layout_start.php';
?>

<div class="container" style="max-width: 1120px; margin-top: 24px; margin-bottom: 24px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="m-0">Supplier Master</h2>
        <a href="inventory_list.php" class="btn btn-outline-secondary btn-sm">Back to Inventory</a>
    </div>

    <?php if ($notice === 'created'): ?>
        <div class="alert alert-success">Supplier created successfully.</div>
    <?php elseif ($notice === 'updated'): ?>
        <div class="alert alert-success">Supplier updated successfully.</div>
    <?php elseif ($notice === 'deleted'): ?>
        <div class="alert alert-success">Supplier deleted successfully.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="mb-3"><?= (int) ($form['id'] ?? 0) > 0 ? 'Edit Supplier' : 'Add Supplier' ?></h5>
            <form method="post">
                <?php renderCSRFInput(); ?>
                <input type="hidden" name="action" value="<?= (int) ($form['id'] ?? 0) > 0 ? 'update' : 'create' ?>">
                <input type="hidden" name="id" value="<?= (int) ($form['id'] ?? 0) ?>">

                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label for="supplier_id" class="form-label">Supplier ID</label>
                        <input type="text" id="supplier_id" name="supplier_id" class="form-control" value="<?= htmlspecialchars((string) ($form['supplier_id'] ?? '')) ?>" placeholder="SUP-0001" required>
                    </div>
                    <div class="col-12 col-md-5">
                        <label for="supplier_name" class="form-label">Supplier Name</label>
                        <input type="text" id="supplier_name" name="supplier_name" class="form-control" value="<?= htmlspecialchars((string) ($form['supplier_name'] ?? '')) ?>" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="contact_name" class="form-label">Contact Name</label>
                        <input type="text" id="contact_name" name="contact_name" class="form-control" value="<?= htmlspecialchars((string) ($form['contact_name'] ?? '')) ?>">
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars((string) ($form['email'] ?? '')) ?>">
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars((string) ($form['phone'] ?? '')) ?>">
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="country" class="form-label">Country</label>
                        <input type="text" id="country" name="country" class="form-control" value="<?= htmlspecialchars((string) ($form['country'] ?? '')) ?>">
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="address_line1" class="form-label">Address Line 1</label>
                        <input type="text" id="address_line1" name="address_line1" class="form-control" value="<?= htmlspecialchars((string) ($form['address_line1'] ?? '')) ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="address_line2" class="form-label">Address Line 2</label>
                        <input type="text" id="address_line2" name="address_line2" class="form-control" value="<?= htmlspecialchars((string) ($form['address_line2'] ?? '')) ?>">
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="city" class="form-label">City</label>
                        <input type="text" id="city" name="city" class="form-control" value="<?= htmlspecialchars((string) ($form['city'] ?? '')) ?>">
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="state_province" class="form-label">State / Province</label>
                        <input type="text" id="state_province" name="state_province" class="form-control" value="<?= htmlspecialchars((string) ($form['state_province'] ?? '')) ?>">
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="postal_code" class="form-label">Postal Code</label>
                        <input type="text" id="postal_code" name="postal_code" class="form-control" value="<?= htmlspecialchars((string) ($form['postal_code'] ?? '')) ?>">
                    </div>

                    <div class="col-12 col-md-8">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3"><?= htmlspecialchars((string) ($form['notes'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="is_active" class="form-label">Status</label>
                        <select id="is_active" name="is_active" class="form-select">
                            <option value="1" <?= ((string) ($form['is_active'] ?? '1')) === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= ((string) ($form['is_active'] ?? '1')) === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary"><?= (int) ($form['id'] ?? 0) > 0 ? 'Update Supplier' : 'Create Supplier' ?></button>
                    <a href="supplier_directory.php" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end mb-3">
                <div class="col-12 col-md-8">
                    <label for="q" class="form-label">Search Suppliers</label>
                    <input type="text" id="q" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Supplier ID, name, or contact">
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">Search</button>
                    <a href="supplier_directory.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Supplier ID</th>
                            <th>Supplier Name</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th style="width: 90px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($suppliers)): ?>
                            <tr>
                                <td colspan="8" class="text-muted">No suppliers found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($suppliers as $supplier): ?>
                                <tr>
                                    <td><?= htmlspecialchars($supplier['supplier_id']) ?></td>
                                    <td><?= htmlspecialchars($supplier['supplier_name']) ?></td>
                                    <td><?= htmlspecialchars((string) ($supplier['contact_name'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars((string) ($supplier['email'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars((string) ($supplier['phone'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars(trim(((string) ($supplier['city'] ?? '')) . ' ' . ((string) ($supplier['country'] ?? '')))) ?></td>
                                    <td>
                                        <?php if ((int) ($supplier['is_active'] ?? 1) === 1): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="supplier_directory.php?edit=<?= (int) $supplier['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <form method="post" onsubmit="return confirm('Delete this supplier? This cannot be undone.');">
                                                <?php renderCSRFInput(); ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $supplier['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/layout_end.php'; ?>