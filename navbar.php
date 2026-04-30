<?php
// Navbar code here
$authPathPrefix = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$authPathPrefix = $authPathPrefix === '' ? '' : '/' . $authPathPrefix;
?>
<nav class="navbar">
  <ul>
    <li><a href="dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a></li>
    <li><a href="contacts_list.php" class="<?= $currentPage === 'contacts_list.php' ? 'active' : '' ?>">Contact List</a></li>
    <li><a href="add_customer.php" class="<?= $currentPage === 'add_customer.php' ? 'active' : '' ?>">➕ Add Customer</a></li>
    <li><a href="customers_list.php" class="<?= $currentPage === 'customers_list.php' ? 'active' : '' ?>">📋 Customer List</a></li>
    <li><a href="customers_list.php" class="<?= $currentPage === 'customers_list.php' ? 'active' : '' ?>">👁 View Customers</a></li>
    <li><a href="calendar.php" class="<?= $currentPage === 'calendar.php' ? 'active' : '' ?>">📅 Calendar</a></li>
    <li><a href="contact_form.php" class="<?= $currentPage === 'contact_form.php' ? 'active' : '' ?>">Add Contact</a></li>
    <li><a href="opportunities_list.php" class="<?= $currentPage === 'opportunities_list.php' ? 'active' : '' ?>">Opportunities</a></li>
    <li><a href="add_opportunity.php" class="<?= $currentPage === 'add_opportunity.php' ? 'active' : '' ?>">Add Opportunity</a></li>
    <li><a href="import_contacts.php" class="<?= $currentPage === 'import_contacts.php' ? 'active' : '' ?>">Import</a></li>
    <li><a href="export_contacts.php" class="<?= $currentPage === 'export_contacts.php' ? 'active' : '' ?>">Export</a></li>
    <li><a href="inventory_list.php" class="<?= $currentPage === 'inventory_list.php' ? 'active' : '' ?>">📦 Inventory</a></li>
    <li><a href="inventory_ledger.php" class="<?= $currentPage === 'inventory_ledger.php' ? 'active' : '' ?>">📦 Inventory Ledger</a></li>
    <li><a href="backorders_list.php" class="<?= $currentPage === 'backorders_list.php' ? 'active' : '' ?>">📦 Backorders</a></li>
    <li><a href="purchase_orders_list.php" class="<?= $currentPage === 'purchase_orders_list.php' ? 'active' : '' ?>">🧾 Purchase Orders</a></li>
    <?php if (auth_check()): ?>
      <li style="margin-left: auto;">
        <span style="margin-right: 15px;">👤 <?= htmlspecialchars(auth_current_user()['username']) ?></span>
        <a href="<?= htmlspecialchars(($authPathPrefix === '' ? '' : $authPathPrefix) . '/simple_auth/logout.php') ?>" style="color: #e74c3c;">Logout</a>
      </li>
    <?php else: ?>
      <li style="margin-left: auto;">
        <a href="<?= htmlspecialchars(($authPathPrefix === '' ? '' : $authPathPrefix) . '/simple_auth/login.php') ?>">Login</a> | 
        <a href="<?= htmlspecialchars(($authPathPrefix === '' ? '' : $authPathPrefix) . '/simple_auth/register.php') ?>">Register</a>
      </li>
    <?php endif; ?>
  </ul>
</nav>
