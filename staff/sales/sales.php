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
    $stmt = $mysqli->prepare(
    'SELECT o.id, o.table_id, o.status, o.reference, o.created_at, o.paid_at, o.checked_out_by, c.username AS checked_out_by_username,
        (SELECT COALESCE(SUM((CASE WHEN oi.price IS NOT NULL THEN oi.price ELSE p.price END) * oi.quantity),0)
         FROM order_items oi
         LEFT JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = o.id) AS total,
        (SELECT p2.method FROM payments p2 WHERE p2.order_id = o.id ORDER BY p2.created_at DESC LIMIT 1) AS payment_method
     FROM `orders` o
     LEFT JOIN `credentials` c ON o.checked_out_by = c.id
     WHERE o.status = "paid"  /* <-- THIS LINE HIDES UNPAID ORDERS */
     ORDER BY o.paid_at DESC LIMIT 200'
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
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Orders</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/style.css">
    <style>table { width:100%; border-collapse:collapse } th,td { padding:0.5rem; border-bottom:1px solid #eee; }</style>
</head>
<body>
<?php require_once __DIR__ . '/../navbar.php'; ?>
<div style="max-width:1100px;margin:1.5rem auto;padding:1rem;">
    <h2>Recent Orders</h2>

    <div class="form-row" style="margin-bottom:0.75rem; display:flex; gap:0.5rem; align-items:center;">
        <input id="ordersSearch" type="search" placeholder="Search by reference (e.g. R202512-0001)" class="form-control flex-fill" />
        <select id="ordersGroup" class="form-control select-control width-sm">
            <option value="all">All Users</option>
            <?php foreach ($checkedUsers as $cu): ?>
                <option value="<?php echo htmlspecialchars($cu); ?>"><?php echo htmlspecialchars($cu); ?></option>
            <?php endforeach; ?>
        </select>
        <select id="ordersSort" class="form-control select-control width-sm">
            <option value="created_desc">Sort: Newest</option>
            <option value="created_asc">Sort: Oldest</option>
            <option value="total_desc">Sort: Total ↓</option>
            <option value="total_asc">Sort: Total ↑</option>
            <option value="table_asc">Sort: Table ↑</option>
            <option value="table_desc">Sort: Table ↓</option>
        </select>
    </div>

    <div class="table-container">
    <table class="nice-table">
        <thead>
            <tr>
                <th>Table</th>
                <th>Reference</th>
                <th>Date Paid</th>
                <th>Method</th>
                <th>Amount</th>
                <th>In Charge</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <?php
                // Clean up variables for display
                $ref = htmlspecialchars($o['reference'] ?? 'N/A');
                $paidAt = !empty($o['paid_at']) ? date("M d, H:i", strtotime($o['paid_at'])) : '---';
                $method = htmlspecialchars($o['payment_method'] ?? '---');
                $total = number_format((float)($o['total'] ?? 0), 2);
                $inCharge = htmlspecialchars($o['checked_out_by_username'] ?? ($o['checked_out_by'] ?? '---'));
            ?>
            <tr data-reference="<?php echo $ref; ?>" data-checked-by="<?php echo $inCharge; ?>">
                <td><strong><?php echo htmlspecialchars($o['table_id']); ?></strong></td>
                <td style="font-family:monospace;"><?php echo $ref; ?></td>
                <td><?php echo $paidAt; ?></td>
                <td><?php echo $method; ?></td>
                <td style="color:#2e7d32; font-weight:bold;">₱<?php echo $total; ?></td>
                <td><?php echo $inCharge; ?></td>
                <td>
                    <button class="btn-info" onclick="viewOrder(<?php echo $o['id']; ?>)">Details</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    
    <script>
    (function(){
    const search = document.getElementById('ordersSearch');
    const sortSel = document.getElementById('ordersSort');
    const groupSel = document.getElementById('ordersGroup');
        const table = document.querySelector('table');
        const tbody = table.querySelector('tbody');

        function rowsArray() {
            return Array.from(tbody.querySelectorAll('tr'));
        }

        function applyFilterAndSort() {
            const q = (search.value || '').trim().toLowerCase();
            const rows = rowsArray();

            const group = (groupSel && groupSel.value) ? groupSel.value : 'all';
            rows.forEach(r => {
                const ref = (r.dataset.reference || '').toLowerCase();
                const by = (r.dataset.checkedBy || '');
                const matchRef = q === '' || (ref.indexOf(q) !== -1);
                const matchGroup = (group === 'all') || (by === group);
                const show = matchRef && matchGroup;
                r.style.display = show ? '' : 'none';
            });

            const mode = sortSel.value;
            const visible = rows.filter(r => r.style.display !== 'none');
            visible.sort((a,b) => {
                if (mode === 'created_desc' || mode === 'created_asc') {
                    const da = new Date(a.dataset.created || 0).getTime();
                    const db = new Date(b.dataset.created || 0).getTime();
                    return mode === 'created_desc' ? db - da : da - db;
                }
                if (mode === 'total_desc' || mode === 'total_asc') {
                    const na = parseFloat(a.dataset.total || 0);
                    const nb = parseFloat(b.dataset.total || 0);
                    return mode === 'total_desc' ? nb - na : na - nb;
                }
                if (mode === 'table_asc' || mode === 'table_desc') {
                    const ta = a.dataset.table || '';
                    const tb = b.dataset.table || '';
                    if (ta === tb) return 0;
                    return mode === 'table_asc' ? (ta < tb ? -1 : 1) : (ta < tb ? 1 : -1);
                }
                return 0;
            });

            visible.forEach(r => tbody.appendChild(r));
        }

        search.addEventListener('input', applyFilterAndSort);
        sortSel.addEventListener('change', applyFilterAndSort);
        if (groupSel) groupSel.addEventListener('change', applyFilterAndSort);
    })();

    function showMoreInfo(orderId) {
    // This is where you will eventually fetch order items (receipt items)
    Swal.fire({
        title: 'Order Details',
        text: 'Loading items for Order ID: ' + orderId,
        icon: 'info',
        confirmButtonColor: '#6B4226'
    });
    }
    </script>
</div>
</body>
</html>
