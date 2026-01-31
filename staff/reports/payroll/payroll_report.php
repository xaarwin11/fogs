<?php
$base_url = "/fogs-1";
require_once __DIR__ . '/../../../db.php';
session_start();

// Security: President Only
if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: $base_url/index.php");
    exit;
}

$mysqli = get_db_conn();

$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Relational Query: Joining credentials and time_tracking
$query = "
    SELECT 
        u.first_name, u.last_name, u.hourly_rate,
        COUNT(t.id) as days_worked,
        SUM(TIMESTAMPDIFF(SECOND, t.clock_in, t.clock_out)) / 3600 as total_hours
    FROM credentials u
    INNER JOIN time_tracking t ON u.id = t.user_id 
    WHERE MONTH(t.date) = ? AND YEAR(t.date) = ?
    GROUP BY u.id, u.first_name, u.last_name, u.hourly_rate
    ORDER BY u.last_name ASC
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("ii", $selected_month, $selected_year);
$stmt->execute();
$result = $stmt->get_result();
$month_name = date("F", mktime(0, 0, 0, $selected_month, 10));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll - <?php echo $month_name; ?></title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/style.css"> 
    <style>
        /* Apply your specific body theme */
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(180deg, #F7F4F0 0%, #F2E7D5 100%);
            margin: 0;
            color: #222;
        }

        .report-container {
            max-width: 1000px;
            margin: 2rem auto;
            background: #fff;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
        }

        /* PRINT LOGIC: Forcing A4 Portrait and Hiding Navbar */
        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm;
            }
            /* Hide the included navbar and the print button */
            header, nav, .navbar, .no-print, .btn {
                display: none !important;
            }
            body { background: #fff; }
            .report-container {
                box-shadow: none;
                margin: 0;
                width: 100%;
                padding: 0;
            }
            .nice-table { border: 1px solid #000; }
            .footer-sigs { display: flex !important; }
        }

        .nice-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .nice-table th { background: #fdfaf7; color: #6B4226; text-align: left; padding: 1rem; border-bottom: 2px solid #6B4226; }
        .nice-table td { padding: 1rem; border-bottom: 1px solid #eee; }
        
        .footer-sigs { 
            display: none; 
            margin-top: 50px; 
            justify-content: space-between; 
        }
        .sig-box { border-top: 1px solid #000; width: 220px; text-align: center; padding-top: 5px; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../navbar.php'; ?>

<div class="container">
    <div class="report-container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1rem;">
            <h2 style="color: #6B4226; margin:0;">Payroll: <?php echo "$month_name $selected_year"; ?></h2>
            <button onclick="window.print()" class="btn no-print">🖨️ Print A4 PDF</button>
        </div>

        <div class="table-container">
            <table class="nice-table">
                <thead>
                    <tr>
                        <th>Staff Member</th>
                        <th>Days</th>
                        <th>Hours</th>
                        <th>Rate/Hr</th>
                        <th>Gross Pay</th>
                        <th class="no-print">Sign</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grand_total = 0;
                    while($row = $result->fetch_assoc()): 
                        $full_name = $row['first_name'] . ' ' . $row['last_name'];
                        $hours = (float)$row['total_hours'];
                        $rate = (float)$row['hourly_rate'];
                        $gross = $hours * $rate;
                        $grand_total += $gross;
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($full_name); ?></strong></td>
                        <td><?php echo $row['days_worked']; ?></td>
                        <td><?php echo number_format($hours, 2); ?></td>
                        <td>₱<?php echo number_format($rate, 2); ?></td>
                        <td style="font-weight:700; color:#2e7d32;">₱<?php echo number_format($gross, 2); ?></td>
                        <td style="width: 80px; border-bottom: 1px solid #000;" class="no-print"></td>
                    </tr>
                    <?php endwhile; ?>
                    <tr style="background:#fdfaf7; font-weight:bold;">
                        <td colspan="4" style="text-align:right;">TOTAL PAYROLL:</td>
                        <td colspan="2" style="color:#2e7d32; font-size:1.2rem;">₱<?php echo number_format($grand_total, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="footer-sigs">
            <div class="sig-box">
                <strong>SHARWIN MACARIO G. TABILA</strong><br>President
            </div>
            <div class="sig-box">
                <strong>STAFF SIGNATURE</strong><br>Acknowledgment
            </div>
        </div>
    </div>
</div>

</body>
</html>