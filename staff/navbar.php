<?php

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {

    header('Location: /fogs/login.php');
    exit;
}

$role = strtolower($_SESSION['role'] ?? '');
?>
<nav>
    <div class="nav-left">
        <span class="brand">FOGSTASA'S CAFE</span>
        <div class="nav-links">
            <?php if (in_array($role, ['staff','admin','manager'])): ?>
                <a href="/fogs/staff/dashboard_staff.php">Dashboard</a>
                <a href="/fogs/staff/pos/pos.php">POS</a>
                <a href="/fogs/staff/orders/orders.php">Orders</a>
                <a href="/fogs/staff/time_tracker/time_tracking.php">Time Tracking</a>
                <a href="/fogs/staff/products/products_admin.php">Products</a>
                <a href="/fogs/staff/kds/kitchen.php">Kitchen Display</a>
            <?php endif; ?>
            
        </div>
    </div>
    <div class="nav-right">
        <div class="nav-user">Welcome, <b><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></b></div>
        <a class="logout" href="/fogs/logout.php">Logout</a>
    </div>  
</nav>
