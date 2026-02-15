<?php
$base_url = "/fogs-1";
require_once __DIR__ . '/../../db.php';
session_start();

if (empty($_SESSION['user_id'])) {
    header("Location: $base_url/index.php");
    exit;
}
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['staff','admin','manager'])) {
    header("Location: $base_url/index.php");
    exit;
}

$orders = [];
try {
    $mysqli = get_db_conn();
    // 1. UPDATED SQL: Now fetches both "paid" and "voided"
    $stmt = $mysqli->prepare(
    "SELECT o.id, o.table_id, o.status, o.reference, o.created_at, o.paid_at, o.grand_total as total, o.checked_out_by, c.username AS checked_out_by_username,
        (SELECT p2.method FROM payments p2 WHERE p2.order_id = o.id ORDER BY p2.created_at DESC LIMIT 1) AS payment_method
     FROM `orders` o
     LEFT JOIN `credentials` c ON o.checked_out_by = c.id
     WHERE o.status IN ('paid', 'voided') 
     ORDER BY o.paid_at DESC LIMIT 200"
    );
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $orders[] = $row;
        }
        $res->free();
        $stmt->close();
    }
    $mysqli->close();
} catch (Exception $ex) {
    error_log('orders list error: ' . $ex->getMessage());
}

$checkedUsers = [];
foreach ($orders as $o) {
    $uname = trim((string)($o['checked_out_by_username'] ?? ($o['checked_out_by'] ?? '')));
    if ($uname !== '') $checkedUsers[$uname] = true;
}
$checkedUsers = array_keys($checkedUsers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="<?php echo $base_url; ?>/assets/logo.png">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Recent Orders</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        table { width:100%; border-collapse:collapse } 
        th,td { padding:0.75rem; border-bottom:1px solid #eee; text-align: left; }
        .nice-table tr:hover { background: #f9f9f9; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
        .badge-paid { background: #e8f5e9; color: #2e7d32; border: 1px solid #2e7d32; }
        .badge-voided { background: #ffebee; color: #c62828; border: 1px solid #c62828; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../navbar.php'; ?>

<div style="max-width:1100px; margin:1.5rem auto; padding:1rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h2 style="margin:0;">Recent Orders</h2>
        <span style="color:#666; font-size:0.9rem;">Paid & Voided Transactions</span>
    </div>

    <div class="form-row" style="margin-bottom:1.5rem; display:flex; gap:0.5rem; align-items:center;">
        <input id="ordersSearch" type="search" placeholder="Search by reference..." style="flex:2; padding:8px; border:1px solid #ddd; border-radius:4px;" />
        
        <select id="ordersGroup" style="flex:1; padding:8px; border:1px solid #ddd; border-radius:4px;">
            <option value="all">All Staff</option>
            <?php foreach ($checkedUsers as $cu): ?>
                <option value="<?php echo htmlspecialchars($cu); ?>"><?php echo htmlspecialchars($cu); ?></option>
            <?php endforeach; ?>
        </select>

        <select id="ordersSort" style="flex:1; padding:8px; border:1px solid #ddd; border-radius:4px;">
            <option value="created_desc">Newest Paid</option>
            <option value="created_asc">Oldest Paid</option>
            <option value="total_desc">Amount: High to Low</option>
            <option value="total_asc">Amount: Low to High</option>
            <option value="table_asc">Table Number</option>
        </select>
    </div>

    <div class="table-container" style="background:#fff; border-radius:8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
    <table class="nice-table">
        <thead>
            <tr>
                <th>Table</th>
                <th>Reference</th>
                <th>Status</th>
                <th>Date Paid</th>
                <th>Method</th>
                <th>Amount</th>
                <th>Staff</th>
                <th style="text-align:center;">Action</th>
            </tr>
        </thead>
        <tbody id="ordersBody">
        <?php foreach ($orders as $o): ?>
            <?php
                $ref = htmlspecialchars($o['reference'] ?? 'N/A');
                $status = strtolower($o['status'] ?? 'paid');
                $paidAt = !empty($o['paid_at']) ? date("M d, H:i", strtotime($o['paid_at'])) : '---';
                $method = strtoupper(htmlspecialchars($o['payment_method'] ?? 'CASH'));
                $totalVal = (float)($o['total'] ?? 0);
                $totalFormatted = number_format($totalVal, 2);
                $inCharge = htmlspecialchars($o['checked_out_by_username'] ?? ($o['checked_out_by'] ?? '---'));
            ?>
            <tr data-reference="<?php echo $ref; ?>" 
                data-checked-by="<?php echo $inCharge; ?>" 
                data-total="<?php echo $totalVal; ?>" 
                data-created="<?php echo strtotime($o['paid_at'] ?? 0); ?>" 
                data-table="<?php echo htmlspecialchars($o['table_id']); ?>">
                
                <td><strong>#<?php echo htmlspecialchars($o['table_id']); ?></strong></td>
                <td style="font-family:monospace; color:#555;"><?php echo $ref; ?></td>
                <td>
                    <span class="badge badge-<?php echo $status; ?>">
                        <?php echo $status; ?>
                    </span>
                </td>
                <td><?php echo $paidAt; ?></td>
                <td>
                    <span style="font-size:0.8rem; padding:3px 8px; border-radius:12px; background:#eee;">
                        <?php echo $method; ?>
                    </span>
                </td>
                <td style="color:<?php echo $status === 'voided' ? '#999' : '#2e7d32'; ?>; font-weight:bold; <?php echo $status === 'voided' ? 'text-decoration:line-through;' : ''; ?>">
                    ₱<?php echo $totalFormatted; ?>
                </td>
                <td><?php echo $inCharge; ?></td>
                <td style="text-align:center;">
                    <button class="btn" style="padding:5px 15px; font-size:0.8rem;" onclick="viewOrder(<?php echo $o['id']; ?>, '<?php echo $status; ?>')">Details</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    
    <script>
    (function(){
        // (Filtering and sorting logic remains the same as previous)
        const search = document.getElementById('ordersSearch');
        const sortSel = document.getElementById('ordersSort');
        const groupSel = document.getElementById('ordersGroup');
        const tbody = document.getElementById('ordersBody');

        function applyFilterAndSort() {
            const q = (search.value || '').trim().toLowerCase();
            const rows = Array.from(tbody.querySelectorAll('tr'));
            if(rows.length === 0 || rows[0].cells.length === 1) return;
            const group = (groupSel && groupSel.value) ? groupSel.value : 'all';
            
            rows.forEach(r => {
                const ref = (r.dataset.reference || '').toLowerCase();
                const by = (r.dataset.checkedBy || '');
                const matchRef = q === '' || (ref.indexOf(q) !== -1);
                const matchGroup = (group === 'all') || (by === group);
                r.style.display = (matchRef && matchGroup) ? '' : 'none';
            });

            const mode = sortSel.value;
            const visible = rows.filter(r => r.style.display !== 'none');
            visible.sort((a,b) => {
                if (mode === 'created_desc') return b.dataset.created - a.dataset.created;
                if (mode === 'created_asc') return a.dataset.created - b.dataset.created;
                if (mode === 'total_desc') return parseFloat(b.dataset.total) - parseFloat(a.dataset.total);
                if (mode === 'total_asc') return parseFloat(a.dataset.total) - parseFloat(b.dataset.total);
                if (mode === 'table_asc') return parseInt(a.dataset.table) - parseInt(b.dataset.table);
            });
            visible.forEach(r => tbody.appendChild(r));
        }

        search.addEventListener('input', applyFilterAndSort);
        sortSel.addEventListener('change', applyFilterAndSort);
        groupSel.addEventListener('change', applyFilterAndSort);
    })();

    function viewOrder(orderId, status) {
        Swal.fire({ title: 'Loading...', didOpen: () => { Swal.showLoading(); } });

        fetch(`get_order_details.php?id=${orderId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.error);

                let itemsHtml = '';
                data.items.forEach(item => {
                    const unitPrice = parseFloat(item.base_price) + parseFloat(item.modifier_total || 0);
                    const itemTotal = (item.quantity * unitPrice) - parseFloat(item.item_discount || 0);
                    itemsHtml += `
                        <div style="display:flex; justify-content:space-between; font-size: 0.95rem; margin-bottom:5px;">
                            <span>${item.quantity}x ${item.product_name}</span>
                            <span>₱${itemTotal.toFixed(2)}</span>
                        </div>`;
                });

                // Handle the Void Footer
                let voidFooter = '';
                if (status === 'voided') {
                    voidFooter = `
                        <div style="margin-top:15px; border: 2px solid #d32f2f; padding: 10px; border-radius: 5px; background: #fff5f5;">
                            <div style="color: #d32f2f; font-weight: bold; text-align: center; text-transform: uppercase; font-size: 1.1rem; margin-bottom: 5px;">
                                ⚠ Order Voided
                            </div>
                            <div style="font-size: 0.85rem; color: #555;">
                                <strong>Reason:</strong> ${data.meta.void_reason || 'No reason provided'}<br>
                                <strong>Authorized by:</strong> ${data.meta.voided_by_name || 'System'}
                            </div>
                        </div>`;
                } else {
                    voidFooter = `
                        <div style="margin-top:15px;">
                            <button onclick="refundPrompt(${data.meta.id})" style="background:#d32f2f; color:#fff; border:none; padding:10px; border-radius:4px; cursor:pointer; width:100%; font-weight:bold;">VOID ORDER</button>
                        </div>`;
                }

                const receiptContent = `
                    <div style="font-family: 'Courier New', Courier, monospace; text-align: left; padding: 10px; border: 1px dashed #ccc; background: #fff; ${status === 'voided' ? 'filter: grayscale(1); opacity: 0.7;' : ''}">
                        <div style="text-align:center; margin-bottom:10px;">
                            <strong>FOGS POS</strong><br>
                            <small>REF: ${data.meta.reference}</small>
                        </div>
                        <hr style="border-top:1px dashed #aaa;">
                        ${itemsHtml}
                        <div style="display:flex; justify-content:space-between; font-size:1.2rem; font-weight:bold; margin-top:10px; border-top:2px solid #333; padding-top:5px;">
                            <span>TOTAL:</span>
                            <span>₱${parseFloat(data.meta.grand_total).toFixed(2)}</span>
                        </div>
                    </div>
                    ${voidFooter}
                `;

                Swal.fire({
                    title: 'Order Details',
                    html: receiptContent,
                    showConfirmButton: true,
                    confirmButtonText: 'Close',
                    confirmButtonColor: '#666',
                    width: '420px'
                });
            });
    }

    window.refundPrompt = (orderId) => {
        Swal.fire({
            title: 'Void Entire Order?',
            text: "Provide a reason for voiding:",
            input: 'text',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d32f2f',
            confirmButtonText: 'Yes, Void it!',
            preConfirm: (reason) => {
                if (!reason) { Swal.showValidationMessage('A reason is required'); }
                return reason;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('void_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: orderId, reason: result.value })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Voided!', '', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.error, 'error');
                    }
                });
            }
        });
    };
    </script>
</div>
</body>
</html>