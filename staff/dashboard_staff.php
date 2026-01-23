<?php
require_once  '../db.php';
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['staff','admin','manager'])) {
    header('Location: ../customer/dashboard.php');
    exit;
}

$tables = [];
try {
    $mysqli = get_db_conn();
    $stmt = $mysqli->prepare('SELECT id, table_number, occupied FROM `tables` ORDER BY table_number ASC');
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $id = isset($row['id']) ? (int)$row['id'] : null;
            $tableNumber = isset($row['table_number']) ? (int)$row['table_number'] : $id;
            $isOccupied = isset($row['occupied']) ? ((int)$row['occupied'] === 1) : false;
            $tables[] = ['id' => $id, 'table_number' => $tableNumber, 'is_occupied' => (int)$isOccupied];
        }
        $res->free();
        $stmt->close();
    }
    $mysqli->close();
} catch (Exception $ex) {
    error_log('Dashboard DB error: ' . $ex->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>

    <link rel="stylesheet" href="../assets/style.css">
    </head>
    <body>
        <?php include 'navbar.php'; ?>
        <main style="width:100%; max-width:1100px; margin:0 auto;">
            <div id="tableGrid" class="table-grid">
            </div>
        </main>

        <script>
        
        const initialTables = <?php echo json_encode($tables, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;

        let currentTableId = null;

        function createTableCard(table) {
            const div = document.createElement('div');
            div.className = 'table-card ' + (table.is_occupied ? 'occupied' : 'available');
            div.dataset.tableId = table.id;
            div.dataset.tableNumber = table.table_number;

           
            const icon = document.createElement('div');
            icon.className = 'table-icon';
            const img = document.createElement('img');
            img.alt = table.is_occupied ? 'Occupied table' : 'Available table';
            img.src = table.is_occupied ? '../assets/table-occupied.svg' : '../assets/table-available.svg';
            icon.appendChild(img);

            
            const num = document.createElement('div');
            num.className = 'table-number';
            num.textContent = 'Table ' + table.table_number;

           
            const status = document.createElement('div');
            status.className = 'table-status';
            status.textContent = table.is_occupied ? 'Occupied' : 'Available';

            
            const summary = document.createElement('div');
            summary.className = 'order-summary';
            summary.textContent = 'Loading...';
            
            fetch('get_table_orders.php?table_id=' + encodeURIComponent(table.id), { credentials: 'same-origin' })
                .then(r => r.json()).then(data => {
                    if (!data || !data.success) {
                        summary.textContent = 'No orders';
                        return;
                    }
                    summary.textContent = (data.total_items ? (data.total_items + ' items') : 'No orders');
                }).catch(() => {
                    summary.textContent = 'No orders';
                });

            div.appendChild(icon);
            div.appendChild(num);
            div.appendChild(status);
            div.appendChild(summary);

            return div;
        }

        function updateTableStatusesFromApi() {
            fetch('get_table_statuses.php').then(r => r.json()).then(data => {
                if (!data || !data.success) return;
                const grid = document.getElementById('tableGrid');
                const known = {};
                grid.querySelectorAll('.table-card').forEach(el => known[el.dataset.tableId] = el);

                data.tables.forEach(t => {
                    const id = String(t.id);
                    const existing = known[id];
                    if (existing) {
                        existing.classList.toggle('occupied', !!t.is_occupied);
                        existing.classList.toggle('available', !t.is_occupied);
                        const statusEl = existing.querySelector('.table-status');
                        if (statusEl) statusEl.textContent = t.is_occupied ? 'Occupied' : 'Available';
                        const img = existing.querySelector('.table-icon img');
                        if (img) img.src = t.is_occupied ? '/fogs/assets/table-occupied.svg' : '/fogs/assets/table-available.svg';
                        delete known[id];
                    } else {
                        
                        const newCard = createTableCard(t);
                        grid.appendChild(newCard);
                    }
                });

                
                Object.values(known).forEach(el => el.remove());
            }).catch(err => {
                console.warn('Failed to update table statuses', err);
            });
        }

       
        (function hydrate() {
            const grid = document.getElementById('tableGrid');
            if (!grid) return;
            if (!grid.querySelector('.table-card') && Array.isArray(initialTables)) {
                initialTables.forEach(t => grid.appendChild(createTableCard(t)));
            }
        })();

        setInterval(updateTableStatusesFromApi, 10000);
        updateTableStatusesFromApi();
        </script>
    </body>
    

    <script>
        const loginDialog = document.getElementById("loginDialog");
        loginDialog.showModal();

        loginDialog.addEventListener('click', () => loginDialog.close());
    </script>
    

</body>
</html>
