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

$pageTitle = 'Supplier Purchase Order';
include_once __DIR__ . '/layout_start.php';
?>

<div class="container po-form-shell" style="max-width:1100px; margin-top:24px; margin-bottom:24px;">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
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
		<?php $statusLabel = trim((string) ($header['status'] ?? '')); ?>

		<section class="po-sheet">
			<header class="po-head">
				<div>
					<div class="po-brand">Eclipse Water Technologies</div>
					<div class="po-brand-sub">Supplier Purchase Order</div>
				</div>
				<div class="po-meta-grid">
					<div class="po-meta-label">PO Number</div>
					<div class="po-meta-value"><?= htmlspecialchars((string) ($header['po_number'] ?? '')) ?></div>
					<div class="po-meta-label">Issue Date</div>
					<div class="po-meta-value"><?= htmlspecialchars((string) ($header['date'] ?? '')) ?></div>
					<div class="po-meta-label">Expected</div>
					<div class="po-meta-value"><?= htmlspecialchars((string) ($header['expected_delivery'] ?? '')) ?></div>
					<div class="po-meta-label">Status</div>
					<div class="po-meta-value"><span class="po-status-pill"><?= htmlspecialchars($statusLabel !== '' ? $statusLabel : 'draft') ?></span></div>
				</div>
			</header>

			<div class="po-party-grid">
				<div class="po-party-card">
					<div class="po-card-title">Supplier</div>
					<div class="po-party-name"><?= htmlspecialchars((string) ($header['supplier_name'] ?? '')) ?></div>
					<div><strong>Supplier ID:</strong> <?= htmlspecialchars((string) ($header['supplier_id'] ?? '')) ?></div>
					<div><strong>Contact:</strong> <?= htmlspecialchars((string) ($header['supplier_contact'] ?? '')) ?></div>
					<div class="po-address"><?= nl2br(htmlspecialchars((string) ($header['supplier_address'] ?? ''))) ?></div>
				</div>
				<div class="po-party-card">
					<div class="po-card-title">Bill To</div>
					<div class="po-address"><?= nl2br(htmlspecialchars((string) ($header['billing_address'] ?? ''))) ?></div>
					<div class="po-card-title" style="margin-top:14px;">Ship To</div>
					<div class="po-address"><?= nl2br(htmlspecialchars((string) ($header['shipping_address'] ?? ''))) ?></div>
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

			<div class="po-foot-grid">
				<div class="po-foot-card">
					<h6 class="mb-2">Supplier Notes</h6>
					<div style="white-space:pre-wrap;"><?= htmlspecialchars((string) ($header['notes'] ?? '')) ?></div>
					<h6 class="mb-2" style="margin-top:14px;">Payment Instructions</h6>
					<div>Reference PO <strong><?= htmlspecialchars((string) ($header['po_number'] ?? '')) ?></strong> on all invoices and shipments.</div>
					<div>Payment Terms: <strong><?= htmlspecialchars((string) ($header['payment_terms'] ?? 'Net 30')) ?></strong></div>
				</div>
				<div class="po-foot-card">
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

			<div class="po-sign-row">
				<div class="po-sign-box">
					<div class="po-sign-label">Authorized By</div>
					<div class="po-sign-line"></div>
				</div>
				<div class="po-sign-box">
					<div class="po-sign-label">Supplier Confirmation</div>
					<div class="po-sign-line"></div>
				</div>
			</div>
		</section>
	<?php endif; ?>
</div>

<style>
.po-sheet {
	background: linear-gradient(180deg, #ffffff 0%, #f6f8fb 100%);
	border: 1px solid #d6dde8;
	border-radius: 14px;
	padding: 18px;
	box-shadow: 0 8px 20px rgba(15, 35, 64, 0.08);
}

.po-head {
	display: flex;
	justify-content: space-between;
	gap: 20px;
	padding-bottom: 14px;
	margin-bottom: 14px;
	border-bottom: 2px solid #cfd8e6;
}

.po-brand {
	font-size: 1.25rem;
	font-weight: 700;
	color: #0f2d52;
	letter-spacing: 0.02em;
}

.po-brand-sub {
	font-size: 0.92rem;
	color: #5f6f86;
	text-transform: uppercase;
	letter-spacing: 0.08em;
	margin-top: 2px;
}

.po-meta-grid {
	display: grid;
	grid-template-columns: auto auto;
	gap: 3px 12px;
	font-size: 0.9rem;
	min-width: 260px;
}

.po-meta-label {
	color: #5e6b7e;
	font-weight: 600;
}

.po-meta-value {
	color: #1f2e45;
	text-align: right;
	font-weight: 600;
}

.po-status-pill {
	display: inline-block;
	background: #e5edf8;
	color: #133964;
	border: 1px solid #bdd0ea;
	border-radius: 999px;
	padding: 1px 10px;
	font-size: 0.78rem;
	text-transform: uppercase;
	letter-spacing: 0.06em;
}

.po-party-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 12px;
	margin-bottom: 14px;
}

.po-party-card,
.po-foot-card {
	background: #ffffff;
	border: 1px solid #dfe6f1;
	border-radius: 10px;
	padding: 12px;
}

.po-card-title {
	font-size: 0.78rem;
	font-weight: 700;
	color: #567;
	text-transform: uppercase;
	letter-spacing: 0.08em;
	margin-bottom: 6px;
}

.po-party-name {
	font-size: 1.03rem;
	font-weight: 700;
	color: #1b3658;
	margin-bottom: 3px;
}

.po-address {
	white-space: pre-wrap;
	color: #33465f;
}

.po-foot-grid {
	display: grid;
	grid-template-columns: 1.4fr 1fr;
	gap: 12px;
	margin-top: 12px;
}

.po-sign-row {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 22px;
	margin-top: 20px;
}

.po-sign-label {
	font-size: 0.78rem;
	color: #56677f;
	text-transform: uppercase;
	letter-spacing: 0.08em;
	margin-bottom: 28px;
}

.po-sign-line {
	border-bottom: 1px solid #9fb0c8;
	height: 18px;
}

@media (max-width: 900px) {
	.po-head,
	.po-party-grid,
	.po-foot-grid,
	.po-sign-row {
		grid-template-columns: 1fr;
		display: grid;
	}

	.po-meta-grid {
		min-width: 0;
	}
}

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

	.po-sheet {
		border: none;
		box-shadow: none;
		background: #fff;
		padding: 0;
	}
}
</style>

<?php include_once __DIR__ . '/layout_end.php'; ?>
