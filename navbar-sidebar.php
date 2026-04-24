<?php
require_once __DIR__ . '/simple_auth/middleware.php';
// Sidebar navbar code here
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
        <li class="nav-item">
          <a href="discussion.php" class="nav-link <?= $currentPage === 'discussion.php' ? 'active' : '' ?>">
            <span class="nav-icon">💬</span>
            <span>Discussion Log</span>
          </a>
        </li>
      </ul>
    </div>
    
    <!-- CONTACTS SECTION -->
    <div class="nav-section">
      <div class="nav-section-title">Contacts</div>
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
      </ul>
    </div>
    
    <!-- CUSTOMERS SECTION -->
    <div class="nav-section">
      <div class="nav-section-title">Customers</div>
      <ul class="nav-menu">
        <li class="nav-item">
          <a href="customers_list.php" class="nav-link <?= $currentPage === 'customers_list.php' ? 'active' : '' ?>">
            <span class="nav-icon">📋</span>
            <span>All Customers</span>
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
          </a>
        </li>
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
        <li class="nav-item">
          <a href="calendar.php" class="nav-link <?= $currentPage === 'calendar.php' ? 'active' : '' ?>">
            <span class="nav-icon">📅</span>
            <span>Calendar</span>
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
          <a href="inventory_ledger.php" class="nav-link <?= $currentPage === 'inventory_ledger.php' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span>
            <span>Ledger</span>
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
    
    <!-- ADMIN SECTION FULLY REMOVED -->
    
    <!-- ACCOUNT SECTION -->
    <div class="nav-section">
      <div class="nav-section-title">Account</div>
      <ul class="nav-menu">
        <li class="nav-item">
          <a href="simple_auth/logout.php" class="nav-link">
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
