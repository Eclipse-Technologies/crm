<?php
require_once __DIR__ . '/simple_auth/middleware.php';
// Sidebar navbar code here
$authPathPrefix = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$authPathPrefix = $authPathPrefix === '' ? '' : '/' . $authPathPrefix;

// Notification counts (overdue tasks + contracts expiring within 30 days)
$_notif_overdue_tasks = 0;
$_notif_expiring_contracts = 0;
if (!isset($_sidebar_notif_loaded)) {
    $_sidebar_notif_loaded = true;
    try {
        require_once __DIR__ . '/db_mysql.php';
        $_nc = get_mysql_connection();
        $r1 = $_nc->query("SELECT COUNT(*) FROM tasks WHERE due_date < CURDATE() AND status NOT IN ('completed','archived')");
        if ($r1) { $_notif_overdue_tasks = (int)$r1->fetch_row()[0]; $r1->free(); }
        $r2 = $_nc->query("SELECT COUNT(*) FROM contracts WHERE end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND contract_status = 'Active'");
        if ($r2) { $_notif_expiring_contracts = (int)$r2->fetch_row()[0]; $r2->free(); }
        $_nc->close();
    } catch (Throwable $e) { /* non-fatal */ }
}
$_notif_total = $_notif_overdue_tasks + $_notif_expiring_contracts;
?>
<?php
// Get current user info from session
$current_user = auth_current_user();
$user_name = $current_user['username'] ?? 'Guest';
$user_role = $_SESSION['role'] ?? 'user';
// $is_admin removed

// Get initials for avatar
$initials = strtoupper(substr($user_name, 0, 2));
?>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <!-- Logo -->
  <div class="sidebar-header">
    <div class="sidebar-logo">Eclipse CRM</div>
  </div>
  
  <!-- User Info -->
  <div class="sidebar-user">
    <div class="user-avatar"><?= $initials ?></div>
    <div class="user-info">
      <div class="user-name"><?= htmlspecialchars($user_name) ?></div>
      <div class="user-role"><?= ucfirst($user_role) ?></div>
    </div>
  </div>
  
  <!-- Navigation -->
  <nav class="sidebar-nav">
    
    <!-- MAIN SECTION -->
    <div class="nav-section">
      <div class="nav-section-title">Main</div>
      <ul class="nav-menu">
        <li class="nav-item">
          <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span>
            <span>Dashboard</span>
          </a>
        </li>
      </ul>
    </div>

    <!-- ACTIVITY SECTION -->
    <div class="nav-section">
      <div class="nav-section-title">Activity & History</div>
      <div style="font-size:12px;color:#666;padding:0 18px 6px 18px;">Linked Tasks, Opportunities, and Discussions</div>
      <ul class="nav-menu">
        <li class="nav-item">
          <a href="tasks.php" class="nav-link <?= $currentPage === 'tasks.php' ? 'active' : '' ?>">
            <span class="nav-icon">🗂️</span>
            <span>Tasks</span>
            <?php if ($_notif_overdue_tasks > 0): ?><span class="nav-badge" style="background:#dc2626;"><?= $_notif_overdue_tasks ?></span><?php endif; ?>
          </a>
        </li>
        <li class="nav-item">
          <a href="discussion.php" class="nav-link <?= $currentPage === 'discussion.php' ? 'active' : '' ?>">
            <span class="nav-icon">💬</span>
            <span>Discussion Log</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="calendar.php" class="nav-link <?= $currentPage === 'calendar.php' ? 'active' : '' ?>">
            <span class="nav-icon">📅</span>
            <span>Calendar</span>
          </a>
        </li>
      </ul>
    </div>
    
    <!-- PEOPLE SECTION -->
    <div class="nav-section">
      <div class="nav-section-title">People</div>
      <ul class="nav-menu">
        <li class="nav-item">
          <a href="contacts_list.php" class="nav-link <?= $currentPage === 'contacts_list.php' ? 'active' : '' ?>">
            <span class="nav-icon">👥</span>
            <span>All Contacts</span>
            <?php 
            require_once 'db_mysql.php';
            $conn = get_mysql_connection();
            $result = $conn->query('SELECT COUNT(*) AS cnt FROM contacts');
            $row = $result ? $result->fetch_assoc() : null;
            $contact_count = $row ? (int)$row['cnt'] : 0;
            $result && $result->free();
            $conn->close();
            ?>
            <span class="nav-badge"><?= $contact_count ?></span>
          </a>
        </li>
        <li class="nav-item">
          <a href="contact_form.php" class="nav-link <?= $currentPage === 'contact_form.php' ? 'active' : '' ?>">
            <span class="nav-icon">➕</span>
            <span>Add Contact</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="import_contacts.php" class="nav-link <?= $currentPage === 'import_contacts.php' ? 'active' : '' ?>">
            <span class="nav-icon">📥</span>
            <span>Import</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="export_table.php" class="nav-link <?= $currentPage === 'export_table.php' ? 'active' : '' ?>">
            <span class="nav-icon">📤</span>
            <span>Export</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="mass_email.php" class="nav-link <?= $currentPage === 'mass_email.php' ? 'active' : '' ?>">
            <span class="nav-icon">✉️</span>
            <span>Mass Email</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="customers_list.php" class="nav-link <?= $currentPage === 'customers_list.php' ? 'active' : '' ?>">
            <span class="nav-icon">📋</span>
            <span>All Customers</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="customer_portal.php" class="nav-link <?= $currentPage === 'customer_portal.php' ? 'active' : '' ?>">
            <span class="nav-icon">🏢</span>
            <span>Customer Portal</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="add_customer.php" class="nav-link <?= $currentPage === 'add_customer.php' ? 'active' : '' ?>">
            <span class="nav-icon">➕</span>
            <span>Add Customer</span>
          </a>
        </li>
      </ul>
    </div>
    <?php
    require_once __DIR__ . '/simple_auth/middleware.php';
    // Sidebar navbar code here
    ?>
    
    <!-- SALES SECTION -->
    <div class="nav-section">
      <div class="nav-section-title">Sales</div>
      <ul class="nav-menu">
        <li class="nav-item">
          <a href="pipeline_board.php" class="nav-link <?= $currentPage === 'pipeline_board.php' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span>
            <span>Pipeline Board</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="opportunities_list.php" class="nav-link <?= $currentPage === 'opportunities_list.php' ? 'active' : '' ?>">
            <span class="nav-icon">📋</span>
            <span>Opportunities List</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="contracts_list.php" class="nav-link <?= $currentPage === 'contracts_list.php' ? 'active' : '' ?>">
      <style>
        .sidebar .nav-section-title {
          cursor: pointer;
          display: flex;
          align-items: center;
          justify-content: space-between;
          user-select: none;
        }

        .sidebar .nav-section-title::after {
          content: '+';
          font-weight: 700;
          opacity: 0.7;
          transition: transform 0.2s ease;
        }

        .sidebar .nav-section.expanded .nav-section-title::after {
          content: '-';
          opacity: 1;
        }

        .sidebar .nav-section .nav-menu {
          display: none;
        }

        .sidebar .nav-section.expanded .nav-menu {
          display: block;
        }
      </style>
            <span class="nav-icon">📄</span>
            <span>All Contracts</span>
            <?php 
            $conn = get_mysql_connection();
            $result = $conn->query('SELECT COUNT(*) AS cnt FROM contracts');
            $row = $result ? $result->fetch_assoc() : null;
            $contracts_count = $row ? (int)$row['cnt'] : 0;
            $result && $result->free();
            $conn->close();
            if ($contracts_count > 0):
            ?>
            <span class="nav-badge"><?= $contracts_count ?></span>
            <?php endif; ?>
            <?php if ($_notif_expiring_contracts > 0): ?><span class="nav-badge" style="background:#d97706;" title="<?= $_notif_expiring_contracts ?> expiring in 30 days"><?= $_notif_expiring_contracts ?> ⚠️</span><?php endif; ?>
          </a>
        </li>

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var sections = document.querySelectorAll('.sidebar .nav-section');
        sections.forEach(function (section) {
          var title = section.querySelector('.nav-section-title');
          var activeLink = section.querySelector('.nav-link.active');
          if (!title) {
            return;
          }

          title.setAttribute('role', 'button');
          title.setAttribute('tabindex', '0');

          if (activeLink) {
            section.classList.add('expanded');
          }

          var syncExpandedState = function () {
            title.setAttribute('aria-expanded', section.classList.contains('expanded') ? 'true' : 'false');
          };

          syncExpandedState();

          var toggleSection = function () {
            section.classList.toggle('expanded');
            syncExpandedState();
          };

          title.addEventListener('click', toggleSection);
          title.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
              event.preventDefault();
              toggleSection();
            }
          });
        });
      });
    </script>
        <li class="nav-item">
          <a href="renewals.php" class="nav-link <?= $currentPage === 'renewals.php' ? 'active' : '' ?>">
            <span class="nav-icon">🔄</span>
            <span>Renewals</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="revenue_dashboard.php" class="nav-link <?= $currentPage === 'revenue_dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">💰</span>
            <span>Revenue Dashboard</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="forecast_dashboard.php" class="nav-link <?= $currentPage === 'forecast_dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">📈</span>
            <span>Forecast Dashboard</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="add_opportunity.php" class="nav-link <?= $currentPage === 'add_opportunity.php' ? 'active' : '' ?>">
            <span class="nav-icon">➕</span>
            <span>Add Opportunity</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="contract_form.php" class="nav-link <?= $currentPage === 'contract_form.php' ? 'active' : '' ?>">
            <span class="nav-icon">➕</span>
            <span>Add Contract</span>
          </a>
        </li>
      </ul>
    </div>
    
    <!-- EQUIPMENT SECTION -->
    <div class="nav-section">
      <div class="nav-section-title">Equipment</div>
      <ul class="nav-menu">
        <li class="nav-item">
          <a href="equipment_list.php" class="nav-link <?= $currentPage === 'equipment_list.php' ? 'active' : '' ?>">
            <span class="nav-icon">🔧</span>
            <span>All Equipment</span>
            <?php 
            // Equipment count from MySQL
            require_once 'db_mysql.php';
            $conn = get_mysql_connection();
            $result = $conn->query('SELECT COUNT(*) AS cnt FROM equipment');
            $row = $result ? $result->fetch_assoc() : null;
            $equipment_count = $row ? (int)$row['cnt'] : 0;
            $result && $result->free();
            $conn->close();
            if ($equipment_count > 0):
            ?>
            <span class="nav-badge"><?= $equipment_count ?></span>
            <?php endif; ?>
          </a>
        </li>
        <li class="nav-item">
          <a href="equipment_form.php" class="nav-link <?= $currentPage === 'equipment_form.php' ? 'active' : '' ?>">
            <span class="nav-icon">➕</span>
            <span>Add Equipment</span>
          </a>
        </li>
      </ul>
    </div>
    
    <!-- INVENTORY SECTION -->
    <div class="nav-section">
      <div class="nav-section-title">Inventory</div>
      <ul class="nav-menu">
        <li class="nav-item">
          <a href="inventory_list.php" class="nav-link <?= $currentPage === 'inventory_list.php' ? 'active' : '' ?>">
            <span class="nav-icon">📦</span>
            <span>Products</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="supplier_directory.php" class="nav-link <?= $currentPage === 'supplier_directory.php' ? 'active' : '' ?>">
            <span class="nav-icon">🏭</span>
            <span>Suppliers</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="inventory_ledger.php" class="nav-link <?= $currentPage === 'inventory_ledger.php' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span>
            <span>Ledger</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="inventory_movement_history.php" class="nav-link <?= $currentPage === 'inventory_movement_history.php' ? 'active' : '' ?>">
            <span class="nav-icon">📋</span>
            <span>Stock History</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="inventory_ledger_parity.php" class="nav-link <?= $currentPage === 'inventory_ledger_parity.php' ? 'active' : '' ?>">
            <span class="nav-icon">🔍</span>
            <span>Ledger Parity</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="backorders_list.php" class="nav-link <?= $currentPage === 'backorders_list.php' ? 'active' : '' ?>">
            <span class="nav-icon">⚠️</span>
            <span>Backorders</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="purchase_orders_list.php" class="nav-link <?= $currentPage === 'purchase_orders_list.php' ? 'active' : '' ?>">
            <span class="nav-icon">🧾</span>
            <span>Purchase Orders</span>
          </a>
        </li>
      </ul>
    </div>
    
    <!-- ADMIN SECTION -->
    <div class="nav-section">
      <div class="nav-section-title">Admin</div>
      <ul class="nav-menu">
        <li class="nav-item">
          <a href="admin_dashboard.php" class="nav-link <?= $currentPage === 'admin_dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">🛡️</span>
            <span>Dashboard</span>
          </a>
        </li>
        <?php if (($user_role ?? 'user') === 'admin'): ?>
        <li class="nav-item">
          <a href="simple_auth/admin_users.php" class="nav-link <?= $currentPage === 'admin_users.php' ? 'active' : '' ?>">
            <span class="nav-icon">👥</span>
            <span>User Access</span>
          </a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
          <a href="admin_search.php" class="nav-link <?= $currentPage === 'admin_search.php' ? 'active' : '' ?>">
            <span class="nav-icon">🔍</span>
            <span>Advanced Search</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="admin_bulk_ops.php" class="nav-link <?= $currentPage === 'admin_bulk_ops.php' ? 'active' : '' ?>">
            <span class="nav-icon">⚙️</span>
            <span>Bulk Operations</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="admin_reports.php" class="nav-link <?= $currentPage === 'admin_reports.php' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span>
            <span>Reports</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="admin_timeline.php" class="nav-link <?= $currentPage === 'admin_timeline.php' ? 'active' : '' ?>">
            <span class="nav-icon">🕐</span>
            <span>Contact Timeline</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="admin_deduplicate.php" class="nav-link <?= $currentPage === 'admin_deduplicate.php' ? 'active' : '' ?>">
            <span class="nav-icon">🔀</span>
            <span>Deduplicate</span>
          </a>
        </li>
      </ul>
    </div>

    <!-- TOOLS SECTION -->
    <div class="nav-section">
      <div class="nav-section-title">Tools</div>
      <ul class="nav-menu">
        <li class="nav-item">
          <a href="ai_settings.php" class="nav-link <?= $currentPage === 'ai_settings.php' ? 'active' : '' ?>">
            <span class="nav-icon">🤖</span>
            <span>AI Settings</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="ai_activity.php" class="nav-link <?= $currentPage === 'ai_activity.php' ? 'active' : '' ?>">
            <span class="nav-icon">📈</span>
            <span>AI Activity</span>
          </a>
        </li>
      </ul>
    </div>

    <!-- ACCOUNT SECTION -->
    <div class="nav-section">
      <div class="nav-section-title">Account</div>
      <ul class="nav-menu">
        <li class="nav-item">
          <a href="<?= htmlspecialchars(($authPathPrefix === '' ? '' : $authPathPrefix) . '/simple_auth/logout.php') ?>" class="nav-link">
            <span class="nav-icon">🚪</span>
            <span>Logout</span>
          </a>
        </li>
      </ul>
    </div>
    
  </nav>
</aside>

<!-- Main Content Wrapper -->
<div class="main-content" id="mainContent">
  
  <!-- Top Bar -->
  <div class="topbar">
    <div class="topbar-left">
      <button class="menu-toggle" id="menuToggle">
        ☰
      </button>
      <h1 class="page-title">
        <?php
        // Auto-generate page title from filename
        $title = str_replace(['_', '.php'], [' ', ''], $currentPage);
        echo ucwords($title);
        ?>
      </h1>
    </div>
    
    <div class="topbar-right">
      <!-- Global Search -->
      <form method="GET" action="contacts_list.php" class="search-bar" style="display: flex; align-items: center; gap: 8px; margin: 0;">
        <span class="search-icon">🔍</span>
        <input type="text" 
               class="search-input" 
               name="query"
               placeholder="Search contacts, companies..."
               id="globalSearch"
               value="<?= htmlspecialchars($_GET['query'] ?? '') ?>">
        <input type="hidden" name="field" value="">
        <button type="submit" style="display: none;"></button>
      </form>
    </div>
  </div>
  
  <!-- Page Content -->
  <div class="content-container">
    <!-- Page content goes here (from included PHP files) -->
