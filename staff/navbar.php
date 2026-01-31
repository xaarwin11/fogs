<?php
// Prevent direct access to the navbar file
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Location: ../login.php');
    exit;
}

$role = strtolower($_SESSION['role'] ?? '');

/**
 * AUTO-DETECT PROJECT ROOT
 * This works on XAMPP (e.g., /fogs-1) and on a live server (e.g., /)
 * without needing to change anything.
 */
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); 
// We want to find the part of the path that ends at "fogs-1"
$baseUrl = substr($scriptDir, 0, strpos($scriptDir, '/staff')); 
?>

<nav>
    <div class="nav-left">
        <span class="brand">FOGSTASA'S CAFE</span>
        <label for="menu-toggle" class="burger">&#9776;</label>
        <input type="checkbox" id="menu-toggle">
        <div class="nav-links">
            <?php if (in_array($role, ['staff','admin','manager'])): ?>
                
                <a href="<?php echo $baseUrl; ?>/staff/pos/pos.php">POS</a>
                <a href="<?php echo $baseUrl; ?>/staff/sales/sales.php">Sales</a>
                <a href="<?php echo $baseUrl; ?>/staff/time_tracker/time_tracking.php">Time Tracking</a>
                <a href="<?php echo $baseUrl; ?>/staff/products/products_admin.php">Products</a>
                <a href="<?php echo $baseUrl; ?>/staff/kds/kitchen.php">Kitchen Display</a>
                <a href="<?php echo $baseUrl; ?>/logout.php" class="logout-mobile">Logout</a> </div>
            <?php endif; ?>
            <?php if ($role === 'manager' || $role === 'admin'): ?>
                <a href="<?php echo $baseUrl; ?>/staff/admin/staff.php">Staff</a>
                <a href="<?php echo $baseUrl; ?>/staff/reports/reports.php">Reports</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="nav-right">
        <div class="nav-user">Welcome, <b><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></b></div>
        <a class="logout" href="<?php echo $baseUrl; ?>/logout.php">Logout</a>

        <div id="connection-status" style="display: inline-flex; align-items: center; padding-left: 15px; cursor: default;" title="Server Status">
            <div id="status-dot" style="height: 10px; width: 10px; border-radius: 50%; background-color: #95a5a6; margin-right: 8px; transition: background-color 0.3s ease;"></div>
            <span id="status-text" style="font-size: 11px; font-weight: 600; color: #7f8c8d; text-transform: uppercase;">Connecting...</span>
        </div>
    </div>  
</nav>

<script>
// We pass the PHP base URL to JavaScript for the fetch call
const PROJECT_ROOT = "<?php echo $baseUrl; ?>";

function checkServerStatus() {
    const dot = document.getElementById('status-dot');
    const text = document.getElementById('status-text');

    // Use the absolute path from the root (e.g., /fogs-1/ping.php)
    fetch(PROJECT_ROOT + "/ping.php") 
        .then(response => {
            if (response.ok) {
                dot.style.backgroundColor = '#2ecc71'; // Healthy Green
                text.innerText = 'Online';
                text.style.color = '#2ecc71';
            } else {
                throw new Error();
            }
        })
        .catch(() => {
            dot.style.backgroundColor = '#e74c3c'; // Error Red
            text.innerText = 'Offline';
            text.style.color = '#e74c3c';
        });
}

// Check every 30 seconds
setInterval(checkServerStatus, 30000);
document.addEventListener('DOMContentLoaded', checkServerStatus);
</script>