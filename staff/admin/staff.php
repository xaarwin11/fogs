<?php
$base_url = "/fogs-1";
require_once __DIR__ . '/../../db.php';
session_start();

if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: $base_url/index.php");
    exit;
}

$mysqli = get_db_conn();
$query = "SELECT id, username, first_name, last_name, role, hourly_rate FROM credentials ORDER BY last_name ASC";
$result = $mysqli->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Management | FOGS</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/style.css">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(180deg, #F7F4F0 0%, #F2E7D5 100%);
            margin: 0;
            color: #222;
        }
        .container { max-width: 1100px; margin: 2rem auto; padding: 1rem; }
        
        /* Modal Styling matching your #tableDialog */
        #staffDialog {
            border: none;
            border-radius: 12px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.2);
            padding: 25px;
            background: #8D6E63;
            width: 90%;
            max-width: 450px;
            color: white;
        }
        #staffDialog::backdrop { background: rgba(0,0,0,0.6); }
        
        #staffDialog h3 { margin-top: 0; color: #fff; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 10px; }
        
        .form-group { margin-bottom: 1rem; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 0.9rem; font-weight: 600; }
        
        /* Using your .form-control classes for inputs */
        .staff-input {
            width: 100%;
            padding: 0.75rem;
            border-radius: 8px;
            border: none;
            box-sizing: border-box;
        }

        .modal-actions { display: flex; gap: 10px; margin-top: 1.5rem; }
        .btn-full { flex: 1; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../navbar.php'; ?>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2rem;">
        <h2 style="color: #3a2d23;">Staff Management</h2>
        <button class="btn" onclick="showStaffModal()">+ Add New Employee</button>
    </div>

    <div class="products-card">
        <div class="table-container">
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
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong></td>
                        <td><span class="badge-method"><?php echo strtoupper($row['role']); ?></span></td>
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
</div>

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
            <label>Hourly Rate (₱)</label>
            <input type="number" step="0.01" name="hourly_rate" id="field_rate" class="staff-input" placeholder="e.g. 38.89" required>
        </div>
        <div class="form-group" id="pass_group">
            <label>Password</label>
            <input type="password" name="password" class="staff-input">
        </div>

        <div class="modal-actions">
            <button type="submit" class="btn btn-full">Save Employee</button>
            <button type="button" class="btn secondary btn-full" onclick="document.getElementById('staffDialog').close()">Cancel</button>
        </div>
    </form>
</dialog>

<script>
    const modal = document.getElementById('staffDialog');
    const title = document.getElementById('modalTitle');

    // Add this inside your script tag in staff.php

function showStaffModal() {
    title.innerText = "Add New Staff";
    document.getElementById('field_id').value = "";
    document.getElementById('field_fname').value = "";
    document.getElementById('field_lname').value = "";
    document.getElementById('field_user').value = "";
    document.getElementById('field_rate').value = "43.75"; 
    
    // MAKE PASSWORD REQUIRED FOR NEW STAFF
    const passInput = document.querySelector('input[name="password"]');
    passInput.required = true;
    passInput.placeholder = "Enter initial password";
    
    modal.showModal();
}

function editStaff(data) {
    title.innerText = "Edit " + data.first_name;
    document.getElementById('field_id').value = data.id;
    document.getElementById('field_fname').value = data.first_name;
    document.getElementById('field_lname').value = data.last_name;
    document.getElementById('field_user').value = data.username;
    document.getElementById('field_rate').value = data.hourly_rate;
    
    // PASSWORD OPTIONAL FOR UPDATES
    const passInput = document.querySelector('input[name="password"]');
    passInput.required = false;
    passInput.placeholder = "Leave blank to keep current";
    
    modal.showModal();
}
</script>

</body>
</html>