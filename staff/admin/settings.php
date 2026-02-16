
<?php
require_once __DIR__ . '/../../db.php';?>
<script src="<?php echo $base_url; ?>/assets/autolock.js"></script>
<?php
session_start();

if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: $base_url/index.php");
    exit;
}

$mysqli = get_db_conn();

// --- 1. FETCH GLOBAL SETTINGS ---
$settings_res = $mysqli->query("SELECT setting_key, setting_value FROM system_settings");
$set = [];
while($s = $settings_res->fetch_assoc()){
    $set[$s['setting_key']] = $s['setting_value'];
}

// --- 2. FETCH PRINTERS (For the Device List & Routing Dropdowns) ---
$printers = $mysqli->query("SELECT * FROM printers ORDER BY id ASC");
$printer_list = []; // Store for dropdown reuse
while($p = $printers->fetch_assoc()) { $printer_list[] = $p; }

// --- 3. FETCH TABLES (Dynamic, not hardcoded) ---
$tables_res = $mysqli->query("SELECT * FROM tables ORDER BY table_type, table_number ASC");

// --- 4. FETCH STAFF (Your Code Integrated) ---
$staff_query = "SELECT c.id, c.username, c.first_name, c.last_name, c.role_id, r.role_name, c.hourly_rate 
                FROM credentials c 
                JOIN roles r ON c.role_id = r.id 
                ORDER BY c.last_name ASC";
$staff_res = $mysqli->query($staff_query);

// --- 5. FETCH DISCOUNTS ---
$discounts_res = $mysqli->query("SELECT * FROM discounts ORDER BY id DESC");

// --- 5. FETCH ROLES (For Staff Modal) ---
$roles_res = $mysqli->query("SELECT id, role_name FROM roles ORDER BY id ASC");
$roles = $roles_res->fetch_all(MYSQLI_ASSOC);

// --- 6. FETCH CATEGORIES ---
$cat_res = $mysqli->query("SELECT * FROM categories ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Command Center | FOGS</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/style.css">
    <style>
        /* Layout Framework */
        .settings-wrapper { display: flex; max-width: 1200px; margin: 2rem auto; background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); min-height: 750px; }
        .settings-nav { width: 260px; background: #3a2d23; color: white; border-top-left-radius: 12px; border-bottom-left-radius: 12px; padding: 20px 0; display: flex; flex-direction: column; }
        .settings-body { flex: 1; padding: 40px; background: #F7F4F0; border-top-right-radius: 12px; border-bottom-right-radius: 12px; overflow-y: auto; }

        /* Navigation Items */
        .nav-item { padding: 18px 25px; cursor: pointer; transition: 0.2s; border-left: 5px solid transparent; opacity: 0.8; font-weight: 500; font-size: 1rem; }
        .nav-item:hover { background: rgba(255,255,255,0.1); opacity: 1; }
        .nav-item.active { background: #5D4037; border-left-color: #C58F63; opacity: 1; font-weight: bold; }

        /* Content Styling */
        .tab-pane { display: none; animation: fadeIn 0.3s; }
        .tab-pane.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        .card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 25px; border: 1px solid #eee; }
        .card h3 { margin-top: 0; color: #5D4037; border-bottom: 2px solid #F2E7D5; padding-bottom: 10px; margin-bottom: 20px; font-size: 1.1rem; }

        /* Hardware Table */
        .device-table { width: 100%; border-collapse: collapse; }
        .device-table th { text-align: left; color: #888; font-size: 0.85rem; border-bottom: 1px solid #eee; padding: 10px; }
        .device-table td { padding: 12px 10px; border-bottom: 1px solid #f9f9f9; }
        .device-badge { background: #eee; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; color: #555; }
        .device-badge.usb { background: #E3F2FD; color: #1565C0; }
        .device-badge.lan { background: #E8F5E9; color: #2E7D32; }

        /* Table Layout Grid */
        .table-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 20px; }
        .table-node { background: white; border: 2px solid #e0e0e0; border-radius: 12px; padding: 20px; text-align: center; position: relative; transition: 0.2s; cursor: default; }
        .table-node:hover { border-color: #C58F63; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .table-node strong { font-size: 1.4rem; display: block; color: #3a2d23; }
        .table-node small { color: #888; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; }
        .del-btn { position: absolute; top: 5px; right: 5px; color: #ff5252; cursor: pointer; font-size: 1.2rem; border: none; background: none; opacity: 0.5; }
        .del-btn:hover { opacity: 1; }

        /* Modals */
        dialog { border: none; border-radius: 12px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); padding: 30px; width: 400px; max-width: 90%; }
        dialog::backdrop { background: rgba(0,0,0,0.5); }
    </style>
</head>
<body>

<?php include __DIR__ . '/../navbar.php'; ?>

<div class="settings-wrapper">
    <div class="settings-nav">
        <div style="padding: 20px 25px; font-size: 0.85rem; color: #aaa; text-transform: uppercase; letter-spacing: 1px;">Menu</div>
        <div class="nav-item active" onclick="showTab(event, 'business')">🏢 Business Profile</div>
        <div class="nav-item" onclick="showTab(event, 'hardware')">🖨️ Hardware & Routing</div>
        <div class="nav-item" onclick="showTab(event, 'tables')">🪑 Table Layout</div>
        <div class="nav-item" onclick="showTab(event, 'categories')">📂 Menu Categories</div>
        <div class="nav-item" onclick="showTab(event, 'master-library')">📋 Master Add-ons</div>
        <div class="nav-item" onclick="showTab(event, 'discounts')">🏷️ Discounts & Promos</div>
        <div class="nav-item" onclick="showTab(event, 'staff')">👥 Staff & Payroll</div>
        <div class="nav-item" onclick="showTab(event, 'system')">⚙️ System & Backup</div>
    </div>

    <div class="settings-body">

        <div id="business" class="tab-pane active">
            <div class="card">
                <h3>Receipt Header Info</h3>
                <form action="process_settings.php" method="POST">
                    <input type="hidden" name="action" value="update_settings">
                    <input type="hidden" name="current_tab" value="business">
                    
                    <div class="form-group">
                        <label>Store Name</label>
                        <input type="text" name="store_name" class="staff-input" value="<?php echo htmlspecialchars($set['store_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Store Address</label>
                        <textarea name="store_address" class="staff-input" rows="3"><?php echo htmlspecialchars($set['store_address'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Contact Phone</label>
                        <input type="text" name="store_phone" class="staff-input" value="<?php echo htmlspecialchars($set['store_phone'] ?? ''); ?>">
                    </div>
                    <button type="submit" class="btn">Save Profile</button>
                </form>
            </div>
        </div>

        <div id="hardware" class="tab-pane">
            <div class="card">
                <h3>🔀 Order Routing</h3>
                <p style="font-size:0.9rem; color:#666; margin-bottom:15px;">Decide which printer handles which task.</p>
                <form action="process_settings.php" method="POST">
                    <input type="hidden" name="action" value="update_settings">
                    <input type="hidden" name="current_tab" value="hardware">
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:15px;">
                        <div>
                            <label>Receipts (Front)</label>
                            <select name="route_receipt" class="staff-input">
                                <option value="0">-- Select --</option>
                                <?php foreach($printer_list as $p) echo "<option value='{$p['id']}' " . (($set['route_receipt'] ?? 0) == $p['id'] ? 'selected' : '') . ">{$p['printer_label']}</option>"; ?>
                            </select>
                        </div>
                        <div>
                            <label>Kitchen (Food)</label>
                            <select name="route_kitchen" class="staff-input">
                                <option value="0">-- Select --</option>
                                <?php foreach($printer_list as $p) echo "<option value='{$p['id']}' " . (($set['route_kitchen'] ?? 0) == $p['id'] ? 'selected' : '') . ">{$p['printer_label']}</option>"; ?>
                            </select>
                        </div>
                        <div>
                            <label>Bar (Drinks)</label>
                            <select name="route_bar" class="staff-input">
                                <option value="0">-- Select --</option>
                                <?php foreach($printer_list as $p) echo "<option value='{$p['id']}' " . (($set['route_bar'] ?? 0) == $p['id'] ? 'selected' : '') . ">{$p['printer_label']}</option>"; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn" style="margin-top:15px;">Update Routing</button>
                </form>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 style="margin:0;">Connected Devices</h3>
                <button class="btn small" onclick="openPrinterModal()">+ Add Device</button>
            </div>
            <div class="card" style="padding:0;">
                <table class="device-table">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Connection</th>
                            <th>Path / IP</th>
                            <th>Triggers</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($printer_list as $p): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($p['printer_label']); ?></strong></td>
                            <td><span class="device-badge <?php echo $p['connection_type']; ?>"><?php echo strtoupper($p['connection_type']); ?></span></td>
                            <td><?php echo htmlspecialchars($p['path']); ?><?php echo ($p['connection_type']=='lan') ? ':'.$p['port'] : ''; ?></td>
                            <td>
                                <?php if($p['beep_on_print']) echo "🔊 "; ?>
                                <?php if($p['cut_after_print']) echo "✂️ "; ?>
                            </td>
                            <td>
                                <a href="process_settings.php?action=delete&target=printer&id=<?php echo $p['id']; ?>" class="del-btn" style="position:static;">&times;</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($printer_list)) echo "<tr><td colspan='5' style='text-align:center; padding:20px;'>No printers configured yet.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tables" class="tab-pane">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3>Floor Plan</h3>
                <button class="btn" onclick="openTableModal()">+ Add Table</button>
            </div>
            
            <div class="table-grid">
                <?php while($t = $tables_res->fetch_assoc()): ?>
                <div class="table-node">
                    <button class="del-btn" onclick="confirmDelete('table', <?php echo $t['id']; ?>)">&times;</button>
                    <strong><?php echo htmlspecialchars($t['table_number']); ?></strong>
                    <small><?php echo ucfirst($t['table_type']); ?></small>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <div id="categories" class="tab-pane">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3>Menu Categories</h3>
                <button class="btn" onclick="openCatModal()">+ Add Category</button>
            </div>
            <div class="card" style="padding:0;">
                <table class="nice-table">
                    <thead><tr><th>Category Name</th><th width="50">Action</th></tr></thead>
                    <tbody>
                        <?php 
                        // Re-fetch categories to ensure we have the latest
                        $cat_res = $mysqli->query("SELECT * FROM categories ORDER BY name ASC");
                        while($c = $cat_res->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($c['name']); ?></strong></td>
                            <td style="text-align:right; white-space:nowrap;">
                                <button class="btn small" onclick="openManageCatModal(<?php echo $c['id']; ?>, '<?php echo addslashes($c['name']); ?>')">
                                    ⚙️ Manage Global Options
                                </button>
                                <a href="process_settings.php?action=delete&target=category&id=<?php echo $c['id']; ?>" 
                                class="del-btn" style="position:static; margin-left:15px;" 
                                onclick="return confirm('Deleting this category will affect all linked products. Continue?')">&times;</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="master-library" class="tab-pane">
            <div class="card">
                <h3>➕ Master Add-ons (Global Modifiers)</h3>
                <p style="font-size:0.8rem; color:#888;">Create add-ons here once. You can then link them to any Category.</p>
                
                <form action="process_settings.php" method="POST" style="margin-bottom:15px; display:flex; gap:5px;">
                    <input type="hidden" name="action" value="add_master_modifier">
                    <input type="text" name="name" class="staff-input" placeholder="e.g. Extra Pearls" required style="flex:2">
                    <input type="number" name="price" class="staff-input" placeholder="₱" step="0.01" required style="flex:1">
                    <button type="submit" class="btn small">Create Add-on</button>
                </form>

                <table class="nice-table" style="font-size:0.9rem;">
                    <thead><tr><th>Name</th><th>Standard Price</th><th width="50"></th></tr></thead>
                    <tbody>
                        <?php 
                        // FETCHING FROM YOUR product_modifiers WHERE product_id IS NULL
                        $m_res = $mysqli->query("SELECT * FROM product_modifiers WHERE product_id IS NULL ORDER BY name ASC");
                        while($m = $m_res->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($m['name']); ?></strong></td>
                            <td>₱<?php echo number_format($m['price'], 2); ?></td>
                            <td>
                                <a href="process_settings.php?action=delete&target=master_mod&id=<?php echo $m['id']; ?>" 
                                class="del-btn" style="position:static;" onclick="return confirm('Delete this global add-on?')">&times;</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="discounts" class="tab-pane" style="display:none;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="margin:0;">Manage Discounts & Promos</h3>
                <button onclick="openDiscountModal()" class="btn" style="width:auto; padding:10px 20px;">+ Add New Discount</button>
            </div>

            <div class="card" style="padding:0;">
                <table class="nice-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Value</th>
                            <th>Target</th>
                            <th>Status</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($d = $discounts_res->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($d['name']); ?></strong></td>
                            <td>
                                <span class="device-badge"><?php echo ucfirst($d['type']); ?></span>
                            </td>
                            <td>
                                <strong><?php echo ($d['type'] == 'percent' ? $d['value'].'%' : '₱'.number_format($d['value'],2)); ?></strong>
                            </td>
                            <td><?php echo ucfirst($d['target_type']); ?></td>
                            <td>
                                <span style="color: <?php echo $d['is_active'] ? '#2e7d32' : '#d32f2f'; ?>; font-weight:bold;">
                                    <?php echo $d['is_active'] ? '● Active' : '○ Inactive'; ?>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <button class="btn small" onclick='openDiscountModal(<?php echo htmlspecialchars(json_encode($d), ENT_QUOTES, "UTF-8"); ?>)'>Edit</button>
                                <button class="del-btn" style="position:static; float:none; display:inline-block;" onclick="deleteDiscount(<?php echo $d['id']; ?>)">&times;</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if($discounts_res->num_rows == 0): ?>
                            <tr><td colspan="6" style="text-align:center; padding:20px; color:#999;">No discounts created yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="staff" class="tab-pane">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3>Staff & Payroll</h3>
                <button class="btn" onclick="showStaffModal()">+ Add Employee</button>
            </div>
            <div class="card" style="padding:0;">
                <table class="nice-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Rate/Hr</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $staff_res->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong></td>
                            <td><span class="badge-method"><?php echo strtoupper($row['role_name']); ?></span></td>
                            <td>₱<?php echo number_format($row['hourly_rate'], 2); ?></td>
                            <td style="text-align:center;">
                                <button class="btn small" onclick='editStaff(<?php echo json_encode($row); ?>)'>Edit</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="system" class="tab-pane">
            <div class="card">
                <h3>System Preferences</h3>
                <form action="process_settings.php" method="POST">
                    <input type="hidden" name="action" value="update_settings">
                    <input type="hidden" name="current_tab" value="system">

                    <div class="form-group">
                        <label>VAT Rate (%)</label>
                        <input type="number" name="vat_rate" class="staff-input" value="<?php echo $set['vat_rate'] ?? 12; ?>">
                    </div>
                    <div class="form-group">
                        <label>Auto-Lock Timer (Minutes)</label>
                        <input type="number" name="auto_lock_time" class="staff-input" value="<?php echo $set['auto_lock_time'] ?? 5; ?>">
                    </div>
                    <button type="submit" class="btn">Save Preferences</button>
                </form>
            </div>
            
            <div class="card" style="background:#fff0f0; border-color:#ffcdcd;">
                <h3 style="color:#d32f2f; border-color:#ffcdcd;">Danger Zone</h3>
                <button class="btn secondary" style="border:1px solid #d32f2f; color:#d32f2f;">Reset Daily Transactions</button>
            </div>
        </div>

    </div>
</div>

<dialog id="printerModal">
    <h3>Configure Printer</h3>
    <form action="process_settings.php" method="POST">
        <input type="hidden" name="action" value="save_printer">
        
        <label>Friendly Name</label>
        <input type="text" name="printer_label" class="staff-input" placeholder="e.g. Cashier Thermal" required>
        
        <label>Connection Type</label>
        <select name="connection_type" class="staff-input" onchange="togglePort(this.value)">
            <option value="usb">USB (Shared Name)</option>
            <option value="lan">LAN (Ethernet IP)</option>
        </select>
        
        <label>Path / IP Address</label>
        <input type="text" name="path" class="staff-input" placeholder="XP-80C or 192.168.1.xxx" required>
        
        <div id="port-field" style="display:none;">
            <label>Port</label>
            <input type="number" name="port" class="staff-input" value="9100">
        </div>

        <div style="margin-top:15px;">
            <label><input type="checkbox" name="cut_after_print" checked> Auto-Cut</label> &nbsp;
            <label><input type="checkbox" name="beep_on_print"> Beep</label>
        </div>

        <div class="modal-actions">
            <button type="submit" class="btn">Save Device</button>
            <button type="button" class="btn secondary" onclick="document.getElementById('printerModal').close()">Cancel</button>
        </div>
    </form>
</dialog>

<dialog id="tableModal">
    <h3>Add Table</h3>
    <form action="process_settings.php" method="POST">
        <input type="hidden" name="action" value="add_table">
        
        <label>Table Number / ID</label>
        <input type="text" name="table_number" class="staff-input" placeholder="e.g. 5 or Grab-1" required>
        
        <label>Type</label>
        <select name="table_type" class="staff-input">
            <option value="physical">Physical (Dine-In)</option>
            <option value="virtual">Virtual (Takeout)</option>
        </select>

        <div class="modal-actions">
            <button type="submit" class="btn">Add Table</button>
            <button type="button" class="btn secondary" onclick="document.getElementById('tableModal').close()">Cancel</button>
        </div>
    </form>
</dialog>

<dialog id="staffDialog">
    <h3 id="modalTitle">Add New Staff</h3>
    <form method="POST" action="process_staff.php">
        <input type="hidden" name="user_id" id="field_id">
        
        <div class="form-group">
            <label>First Name</label>
            <input type="text" name="first_name" id="field_fname" class="staff-input" required>
        </div>
        <div class="form-group">
            <label>Last Name</label>
            <input type="text" name="last_name" id="field_lname" class="staff-input" required>
        </div>
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" id="field_user" class="staff-input" required>
        </div>

        <div class="form-group">
            <label>Role</label>
            <select name="role_id" id="field_role" class="staff-input" required>
                <?php foreach($roles as $role): ?>
                    <option value="<?php echo $role['id']; ?>"><?php echo $role['role_name']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Hourly Rate (₱)</label>
            <input type="number" step="0.01" name="hourly_rate" id="field_rate" class="staff-input" required>
        </div>

        <div class="form-group">
            <label>Passcode (Numeric)</label>
            <input type="password" name="passcode" id="field_passcode" class="staff-input" inputmode="numeric">
        </div>

        <div class="modal-actions">
            <button type="submit" class="btn btn-full">Save Employee</button>
            <button type="button" class="btn secondary btn-full" onclick="document.getElementById('staffDialog').close()">Cancel</button>
        </div>
    </form>
</dialog>

<dialog id="manageCatModal" style="width: 500px;">
    <h3 id="manageCatTitle">Manage Category</h3>
    
    <div style="margin-bottom: 20px;">
        <h4 style="color:#5D4037; border-bottom:1px solid #eee; padding-bottom:5px;">📏 Global Variations (Sizes)</h4>
        <div id="catVarList" style="max-height: 150px; overflow-y:auto; margin-bottom:10px; font-size:0.9rem;">
            </div>
        <form onsubmit="addCategoryGlobal(event, 'variation')">
            <div style="display:flex; gap:5px;">
                <input type="text" id="newVarName" placeholder="Size (e.g. Large)" class="staff-input" style="flex:2" required>
                <input type="number" id="newVarPrice" placeholder="+₱" class="staff-input" style="flex:1" step="0.01" required>
                <button type="submit" class="btn small">Add</button>
            </div>
        </form>
    </div>

    <div>
        <h4 style="color:#5D4037; border-bottom:1px solid #eee; padding-bottom:5px;">➕ Global Modifiers (Add-ons)</h4>
        <div id="catModList" style="max-height: 150px; overflow-y:auto; margin-bottom:10px; font-size:0.9rem;">
            </div>
        <div style="display:flex; gap:5px;">
             <select id="modSelector" class="staff-input" style="flex:3">
                 <option value="">-- Choose Existing Modifier --</option>
                 </select>
             <button onclick="addCategoryGlobal(null, 'modifier')" class="btn small">Link</button>
        </div>
        <p style="font-size:0.75rem; color:#888; margin-top:5px;">* Modifiers must be created in the Menu/Product settings first to appear here.</p>
    </div>

    <div class="modal-actions" style="margin-top:20px;">
        <button type="button" class="btn secondary btn-full" onclick="document.getElementById('manageCatModal').close()">Done</button>
    </div>
</dialog>

<dialog id="catModal">
    <h3>Add Category</h3>
    <form action="process_settings.php" method="POST">
        <input type="hidden" name="action" value="add_category">
        <label>Category Name</label>
        <input type="text" name="cat_name" class="staff-input" placeholder="e.g. Desserts" required>
        <label>Category Type</label>
        <select name="cat_type" class="staff-input">
            <option value="food">Food (Kitchen Printer)</option>
            <option value="drink">Drink (Bar Printer)</option>
        </select>
        <div class="modal-actions">
            <button type="submit" class="btn">Save</button>
            <button type="button" class="btn secondary" onclick="document.getElementById('catModal').close()">Cancel</button>
        </div>
    </form>
</dialog>

<script>
// --- TAB LOGIC ---
window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab') || 'business';
    const target = document.querySelector(`[onclick*="showTab(event, '${tab}')"]`);
    if(target) target.click();
};

function showTab(evt, tabName) {
    let i, tabcontent, tablinks;
    // Hide all tab panes
    tabcontent = document.getElementsByClassName("tab-pane");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
        tabcontent[i].classList.remove("active");
    }
    // Remove active class from all nav items
    tablinks = document.getElementsByClassName("nav-item");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove("active");
    }
    // Show the current tab and add an "active" class to the button that opened it
    const activeTab = document.getElementById(tabName);
    activeTab.style.display = "block";
    activeTab.classList.add("active");
    evt.currentTarget.classList.add("active");
}
// --- MODAL OPENERS ---
function openPrinterModal() { document.getElementById('printerModal').showModal(); }
function openTableModal() { document.getElementById('tableModal').showModal(); }
function openCatModal() { document.getElementById('catModal').showModal(); }

function togglePort(val) {
    document.getElementById('port-field').style.display = (val === 'lan') ? 'block' : 'none';
}

function confirmDelete(target, id) {
    if(confirm("Are you sure? This cannot be undone.")) {
        window.location.href = `process_settings.php?action=delete&target=${target}&id=${id}`;
    }
}

// --- STAFF LOGIC (From your snippet) ---
const staffModal = document.getElementById('staffDialog');
const staffTitle = document.getElementById('modalTitle');

function showStaffModal() {
    staffTitle.innerText = "Add New Staff";
    document.getElementById('field_id').value = "";
    document.getElementById('field_fname').value = "";
    document.getElementById('field_lname').value = "";
    document.getElementById('field_user').value = "";
    document.getElementById('field_role').value = "3";
    document.getElementById('field_rate').value = "43.75"; 
    
    const pinInput = document.getElementById('field_passcode');
    pinInput.required = true;
    pinInput.placeholder = "Set initial PIN";
    
    staffModal.showModal();
}

function editStaff(data) {
    staffTitle.innerText = "Edit " + data.first_name;
    document.getElementById('field_id').value = data.id;
    document.getElementById('field_fname').value = data.first_name;
    document.getElementById('field_lname').value = data.last_name;
    document.getElementById('field_user').value = data.username;
    document.getElementById('field_role').value = data.role_id;
    document.getElementById('field_rate').value = data.hourly_rate;
    
    const pinInput = document.getElementById('field_passcode');
    pinInput.required = false;
    pinInput.placeholder = "Leave blank to keep current";
    
    staffModal.showModal();
}

let currentManagingCatId = null;

async function openManageCatModal(catId, catName) {
    currentManagingCatId = catId;
    document.getElementById('manageCatTitle').innerText = "Global Options: " + catName;
    
    // 1. Clear previous lists
    document.getElementById('catVarList').innerHTML = "Loading...";
    document.getElementById('catModList').innerHTML = "Loading...";
    
    // 2. Fetch current globals for this category
    // You'll need to create a small helper: fetch_category_globals.php
    const resp = await fetch(`fetch_category_globals.php?category_id=${catId}`);
    const data = await resp.json();
    
    renderGlobals(data);
    document.getElementById('manageCatModal').showModal();
}

function renderGlobals(data) {
    // Render Variations
    let varHtml = data.variations.length ? '' : '<p style="color:#aaa">No global sizes set.</p>';
    data.variations.forEach(v => {
        varHtml += `<div style="display:flex; justify-content:space-between; padding:5px; border-bottom:1px solid #f9f9f9;">
            <span>${v.variation_name} (+₱${parseFloat(v.price).toFixed(2)})</span>
            <button onclick="deleteGlobal('variation', '${v.variation_name}')" style="color:red; border:none; background:none; cursor:pointer;">&times;</button>
        </div>`;
    });
    document.getElementById('catVarList').innerHTML = varHtml;

    // Render Modifiers
    let modHtml = data.modifiers.length ? '' : '<p style="color:#aaa">No global add-ons set.</p>';
    data.modifiers.forEach(m => {
        modHtml += `<div style="display:flex; justify-content:space-between; padding:5px; border-bottom:1px solid #f9f9f9;">
            <span>${m.name} (+₱${parseFloat(m.price).toFixed(2)})</span>
            <button onclick="deleteGlobal('modifier', ${m.id})" style="color:red; border:none; background:none; cursor:pointer;">&times;</button>
        </div>`;
    });
    document.getElementById('catModList').innerHTML = modHtml;
    
    // Populate the dropdown selector for modifiers
    let selectorHtml = '<option value="">-- Choose Existing Modifier --</option>';
    data.all_available_modifiers.forEach(am => {
        selectorHtml += `<option value="${am.id}">${am.name} (₱${am.price})</option>`;
    });
    document.getElementById('modSelector').innerHTML = selectorHtml;
}

async function addCategoryGlobal(e, type) {
    if(e) e.preventDefault();
    
    let payload = { category_id: currentManagingCatId, type: type };
    
    if(type === 'variation') {
        payload.name = document.getElementById('newVarName').value;
        payload.price = document.getElementById('newVarPrice').value;
    } else {
        payload.modifier_id = document.getElementById('modSelector').value;
        if(!payload.modifier_id) return;
    }

    const resp = await fetch('process_category_globals.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    
    const result = await resp.json();
    if(result.success) {
        // Refresh the list
        openManageCatModal(currentManagingCatId, ""); 
        if(type === 'variation') {
            document.getElementById('newVarName').value = '';
            document.getElementById('newVarPrice').value = '';
        }
    } else {
        alert(result.error);
    }
}

async function deleteGlobal(type, idOrName) {
    if(!confirm("Remove this global option?")) return;
    
    await fetch('process_category_globals.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ category_id: currentManagingCatId, type: type, target: idOrName })
    });
    openManageCatModal(currentManagingCatId, "");
}
// --- DISCOUNT MANAGEMENT ---
function openDiscountModal(data = null) {
    const isEdit = !!data;
    Swal.fire({
        title: isEdit ? 'Edit Discount' : 'Add New Discount',
        html: `
            <input type="hidden" id="discId" value="${data?.id || ''}">
            <div style="text-align:left; margin-bottom:10px;">
                <label style="font-weight:bold; display:block; margin-bottom:5px;">Discount Name</label>
                <input type="text" id="discName" class="swal2-input" placeholder="e.g. Senior Citizen" value="${data?.name || ''}" style="width:100%; margin:0;">
            </div>
            <div style="display:flex; gap:10px; margin-bottom:10px;">
                <div style="flex:1; text-align:left;">
                    <label style="font-weight:bold; display:block; margin-bottom:5px;">Type</label>
                    <select id="discType" class="swal2-input" style="width:100%; margin:0;">
                        <option value="percent" ${data?.type==='percent'?'selected':''}>Percentage (%)</option>
                        <option value="fixed" ${data?.type==='fixed'?'selected':''}>Fixed Amount (₱)</option>
                    </select>
                </div>
                <div style="flex:1; text-align:left;">
                    <label style="font-weight:bold; display:block; margin-bottom:5px;">Value</label>
                    <input type="number" id="discValue" class="swal2-input" style="width:100%; margin:0;" value="${data?.value || ''}">
                </div>
            </div>
            <div style="text-align:left; margin-bottom:10px;">
                <label style="font-weight:bold; display:block; margin-bottom:5px;">Target Behavior</label>
                <select id="discTarget" class="swal2-input" style="width:100%; margin:0;">
                    <option value="all" ${data?.target_type==='all'?'selected':''}>Apply to Entire Bill</option>
                    <option value="highest" ${data?.target_type==='highest'?'selected':''}>Apply to Highest Priced Item</option>
                    <option value="food" ${data?.target_type==='food'?'selected':''}>Apply to All Food Items</option>
                    <option value="drink" ${data?.target_type==='drink'?'selected':''}>Apply to All Drinks</option>
                </select>
            </div>
            <div style="text-align:left;">
                <label style="cursor:pointer;"><input type="checkbox" id="discActive" ${(!isEdit || data?.is_active == 1) ? 'checked' : ''}> Active / Enabled</label>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Save Discount',
        focusConfirm: false,
        preConfirm: () => {
            const name = document.getElementById('discName').value;
            const value = document.getElementById('discValue').value;
            if (!name || !value) {
                Swal.showValidationMessage('Please fill out all fields');
                return false;
            }
            return {
                id: document.getElementById('discId').value,
                name: name,
                type: document.getElementById('discType').value,
                value: value,
                target_type: document.getElementById('discTarget').value,
                is_active: document.getElementById('discActive').checked ? 1 : 0
            }
        }
    }).then(result => {
        if (result.isConfirmed) {
            saveDiscount(result.value);
        }
    });
}

async function saveDiscount(payload) {
    try {
        const resp = await fetch('process_discounts.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const res = await resp.json();
        if(res.success) {
            Swal.fire({ icon: 'success', title: 'Saved!', showConfirmButton: false, timer: 1000 })
                .then(() => location.reload());
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Could not connect to server', 'error');
    }
}

async function deleteDiscount(id) {
    const result = await Swal.fire({
        title: 'Delete Discount?',
        text: "This cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, remove it'
    });

    if (result.isConfirmed) {
        const resp = await fetch('process_discounts.php?action=delete&id=' + id, { method: 'POST' });
        const res = await resp.json();
        if(res.success) {
            location.reload();
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    }
}
</script>

</body>
</html>