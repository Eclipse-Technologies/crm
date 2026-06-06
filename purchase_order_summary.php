<?php
require_once __DIR__ . '/db_mysql.php';

function build_summary_totals(array $header, array $items): array {
		$subtotal = is_numeric($header['subtotal'] ?? null) ? (float) $header['subtotal'] : 0.0;
		$discount = is_numeric($header['total_discount'] ?? null) ? (float) $header['total_discount'] : 0.0;
		$tax = is_numeric($header['total_tax'] ?? null) ? (float) $header['total_tax'] : 0.0;
		$shipping = is_numeric($header['shipping_cost'] ?? null) ? (float) $header['shipping_cost'] : 0.0;
		$otherFees = is_numeric($header['other_fees'] ?? null) ? (float) $header['other_fees'] : 0.0;
		$grand = is_numeric($header['grand_total'] ?? null) ? (float) $header['grand_total'] : 0.0;

		if ($subtotal <= 0.0 && !empty($items)) {
				foreach ($items as $item) {
						$lineTotal = is_numeric($item['total'] ?? null) ? (float) $item['total'] : 0.0;
						$subtotal += $lineTotal;
				}
		}

		if ($tax <= 0.0 && !empty($items)) {
				foreach ($items as $item) {
						$lineTax = is_numeric($item['tax_amount'] ?? null) ? (float) $item['tax_amount'] : 0.0;
						$tax += $lineTax;
				}
		}

		if ($grand <= 0.0) {
				$grand = max(0.0, $subtotal - $discount + $tax + $shipping + $otherFees);
		}

		return [
				'subtotal' => $subtotal,
				'discount' => $discount,
				'tax' => $tax,
				'shipping' => $shipping,
				'other_fees' => $otherFees,
				'grand_total' => $grand,
		];
}

function money_fmt(float $amount, string $currency = 'CAD'): string {
		return $currency . ' ' . number_format($amount, 2);
}

$poNumber = trim((string) ($_GET['po'] ?? ''));
$header = [];
$items = [];
$loadError = '';

if ($poNumber !== '') {
		try {
				$conn = get_mysql_connection();

				$headerSql = 'SELECT po_number, date, status, supplier_id, supplier_name, supplier_contact, supplier_address, billing_address, shipping_address, subtotal, total_discount, total_tax, shipping_cost, other_fees, grand_total, currency, expected_delivery, payment_terms, notes, created_by, created_at, updated_at FROM purchase_orders WHERE po_number = ? LIMIT 1';
				$headerStmt = $conn->prepare($headerSql);
				if ($headerStmt) {
						$headerStmt->bind_param('s', $poNumber);
						$headerStmt->execute();
						$headerRes = $headerStmt->get_result();
						$header = $headerRes ? ($headerRes->fetch_assoc() ?: []) : [];
						if ($headerRes instanceof mysqli_result) {
								$headerRes->free();
						}
						$headerStmt->close();
				}

				if (!empty($header)) {
						$itemSql = 'SELECT item_id, item_name, quantity, unit, unit_price, discount, tax_rate, tax_amount, total FROM purchase_order_items WHERE po_number = ? ORDER BY id ASC';
						$itemStmt = $conn->prepare($itemSql);
						if ($itemStmt) {
								$itemStmt->bind_param('s', $poNumber);
								$itemStmt->execute();
								$itemRes = $itemStmt->get_result();
								if ($itemRes instanceof mysqli_result) {
										while ($row = $itemRes->fetch_assoc()) {
												$items[] = $row;
										}
										$itemRes->free();
								}
								$itemStmt->close();
						}
				}

				$conn->close();
		} catch (Throwable $e) {
				$loadError = 'Unable to load purchase order summary: ' . $e->getMessage();
		}
}

$pageTitle = 'Supplier Form';
include_once __DIR__ . '/layout_start.php';
?>

<div class="container" style="max-width:1100px; margin-top:24px; margin-bottom:24px;">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<h2 class="m-0">Supplier Purchase Order Form</h2>
		<div class="d-flex gap-2">
			<button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">Print</button>
			<a href="purchase_orders_list.php" class="btn btn-outline-secondary btn-sm">Back to Purchase Orders</a>
		</div>
	</div>

	<?php if ($loadError !== ''): ?>
		<div class="alert alert-danger"><?= htmlspecialchars($loadError) ?></div>
	<?php endif; ?>

	<?php if ($poNumber === '' || empty($header)): ?>
		<div class="alert alert-warning">Purchase order not found. Please open this page with a valid PO number.</div>
	<?php else: ?>
		<?php $totals = build_summary_totals($header, $items); ?>
		<?php $currency = trim((string) ($header['currency'] ?? 'CAD')); ?>

		<div class="card shadow-sm mb-3">
			<div class="card-body">
				<div class="row g-3">
					<div class="col-12 col-md-6">
						<h5 class="mb-2">Supplier Details</h5>
						<div><strong>Supplier Name:</strong> <?= htmlspecialchars((string) ($header['supplier_name'] ?? '')) ?></div>
						<div><strong>Supplier ID:</strong> <?= htmlspecialchars((string) ($header['supplier_id'] ?? '')) ?></div>
						<div><strong>Contact:</strong> <?= htmlspecialchars((string) ($header['supplier_contact'] ?? '')) ?></div>
						<div><strong>Supplier Address:</strong> <?= nl2br(htmlspecialchars((string) ($header['supplier_address'] ?? ''))) ?></div>
					</div>
					<div class="col-12 col-md-6">
						<h5 class="mb-2">Purchase Order Details</h5>
						<div><strong>PO Number:</strong> <?= htmlspecialchars((string) ($header['po_number'] ?? '')) ?></div>
						<div><strong>Date:</strong> <?= htmlspecialchars((string) ($header['date'] ?? '')) ?></div>
						<div><strong>Status:</strong> <?= htmlspecialchars((string) ($header['status'] ?? '')) ?></div>
						<div><strong>Expected Delivery:</strong> <?= htmlspecialchars((string) ($header['expected_delivery'] ?? '')) ?></div>
						<div><strong>Payment Terms:</strong> <?= htmlspecialchars((string) ($header['payment_terms'] ?? '')) ?></div>
					</div>
				</div>
			</div>
		</div>

		<div class="card shadow-sm mb-3">
			<div class="card-body">
				<div class="row g-3">
					<div class="col-12 col-md-6">
						<h6 class="mb-2">Billing Address</h6>
						<div><?= nl2br(htmlspecialchars((string) ($header['billing_address'] ?? ''))) ?></div>
					</div>
					<div class="col-12 col-md-6">
						<h6 class="mb-2">Shipping Address</h6>
						<div><?= nl2br(htmlspecialchars((string) ($header['shipping_address'] ?? ''))) ?></div>
					</div>
				</div>
			</div>
		</div>

		<div class="card shadow-sm mb-3">
			<div class="card-body">
				<h5 class="mb-3">Items</h5>
				<div class="table-responsive">
					<table class="table table-sm table-bordered align-middle mb-0">
						<thead class="table-light">
							<tr>
								<th>Item ID</th>
								<th>Item Name</th>
								<th class="text-end">Qty</th>
								<th>Unit</th>
								<th class="text-end">Unit Price</th>
								<th class="text-end">Discount</th>
								<th class="text-end">Tax</th>
								<th class="text-end">Line Total</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($items)): ?>
								<tr>
									<td colspan="8" class="text-center text-muted">No line items found for this purchase order.</td>
								</tr>
							<?php else: ?>
								<?php foreach ($items as $item): ?>
									<tr>
										<td><?= htmlspecialchars((string) ($item['item_id'] ?? '')) ?></td>
										<td><?= htmlspecialchars((string) ($item['item_name'] ?? '')) ?></td>
										<td class="text-end"><?= htmlspecialchars((string) ($item['quantity'] ?? '')) ?></td>
										<td><?= htmlspecialchars((string) ($item['unit'] ?? '')) ?></td>
										<td class="text-end"><?= htmlspecialchars((string) ($item['unit_price'] ?? '')) ?></td>
										<td class="text-end"><?= htmlspecialchars((string) ($item['discount'] ?? '')) ?></td>
										<td class="text-end"><?= htmlspecialchars((string) ($item['tax_amount'] ?? '')) ?></td>
										<td class="text-end"><?= htmlspecialchars((string) ($item['total'] ?? '')) ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<div class="card shadow-sm mb-3">
			<div class="card-body">
				<div class="row g-3">
					<div class="col-12 col-md-7">
						<h6 class="mb-2">Supplier Notes</h6>
						<div style="white-space:pre-wrap;"><?= htmlspecialchars((string) ($header['notes'] ?? '')) ?></div>
					</div>
					<div class="col-12 col-md-5">
						<h6 class="mb-2">Totals</h6>
						<table class="table table-sm mb-0">
							<tr><th scope="row">Subtotal</th><td class="text-end"><?= htmlspecialchars(money_fmt($totals['subtotal'], $currency)) ?></td></tr>
							<tr><th scope="row">Discount</th><td class="text-end"><?= htmlspecialchars(money_fmt($totals['discount'], $currency)) ?></td></tr>
							<tr><th scope="row">Tax</th><td class="text-end"><?= htmlspecialchars(money_fmt($totals['tax'], $currency)) ?></td></tr>
							<tr><th scope="row">Shipping</th><td class="text-end"><?= htmlspecialchars(money_fmt($totals['shipping'], $currency)) ?></td></tr>
							<tr><th scope="row">Other Fees</th><td class="text-end"><?= htmlspecialchars(money_fmt($totals['other_fees'], $currency)) ?></td></tr>
							<tr class="table-light"><th scope="row">Grand Total</th><td class="text-end"><strong><?= htmlspecialchars(money_fmt($totals['grand_total'], $currency)) ?></strong></td></tr>
						</table>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>

<style>
@media print {
	.nova-relay-shell,
	.sidebar,
	.btn,
	.navbar,
	.nav,
	.no-print {
		display: none !important;
	}
	.container {
		max-width: 100% !important;
		margin: 0 !important;
		padding: 0 !important;
	}
}
</style>

<?php include_once __DIR__ . '/layout_end.php'; ?>
