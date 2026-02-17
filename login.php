<?php
require_once 'db.php';?>
<script>
    window.FOGS_BASE_URL = "<?php echo $base_url; ?>";
</script>
<?php
session_start();

// Redirect if already logged in for POS
if (!empty($_SESSION['user_id']) && !isset($_POST['action_mode'])) {
    header('Location: /fogs-1/staff/pos/pos.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $passcode = $_POST['passcode'] ?? '';
    $mode = $_POST['action_mode'] ?? 'login'; 
    
    try {
        $mysqli = get_db_conn();
    } catch (Exception $e) {
        $error = 'Database connection failed.';
        $mysqli = null;
    }

    if ($mysqli && !empty($passcode)) {
        // 1. Updated Query: Join roles table to get the role_name
        $sql = "SELECT c.id, c.first_name, r.role_name, c.passcode 
                FROM credentials c 
                JOIN roles r ON c.role_id = r.id";
        $result = $mysqli->query($sql);
        $user_data = null;

        while ($user = $result->fetch_assoc()) {
            if (password_verify($passcode, $user['passcode'])) {
                $user_data = $user;
                break;
            }
        }

        if ($user_data) {
            if ($mode === 'login') {
                // --- POS LOGIN ---
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user_data['id'];
                $_SESSION['username'] = $user_data['first_name'];
                // Set the role name (lowercase) for consistency with your checks
                $_SESSION['role'] = strtolower($user_data['role_name']); 
                
                header('Location: /fogs-1/staff/pos/pos.php');
                exit;
            } else {
                // --- TIME PUNCH LOGIC ---
                $uid = $user_data['id'];
                
                // Check for active session
                $check = $mysqli->prepare("SELECT id, clock_in FROM time_tracking WHERE user_id = ? AND clock_out IS NULL LIMIT 1");
                $check->bind_param("i", $uid);
                $check->execute();
                $active_session = $check->get_result()->fetch_assoc();
                $check->close();

                if ($active_session) {
                    // CLOCK OUT
                    $punch_id = $active_session['id'];
                    $start = new DateTime($active_session['clock_in']);
                    $end = new DateTime();
                    $diff = $start->diff($end);
                    // Standard hours calculation
                    $hrs = round($diff->h + ($diff->i / 60) + ($diff->s / 3600), 2);

                    $upd = $mysqli->prepare("UPDATE time_tracking SET clock_out = NOW(), hours_worked = ? WHERE id = ?");
                    $upd->bind_param("di", $hrs, $punch_id);
                    if ($upd->execute()) {
                        $success = "Clocked OUT: " . $user_data['first_name'] . " (" . $hrs . " hrs)";
                    }
                    $upd->close();
                } else {
                    // CLOCK IN
                    $today = date('Y-m-d');
                    $ins = $mysqli->prepare("INSERT INTO time_tracking (user_id, clock_in, date) VALUES (?, NOW(), ?)");
                    $ins->bind_param("is", $uid, $today);
                    if ($ins->execute()) {
                        $success = "Clocked IN: " . $user_data['first_name'] . " @ " . date('h:i A');
                    }
                    $ins->close();
                }
            }
        } else {
            $error = 'Invalid Passcode';
        }
        $mysqli->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="theme-color" content="#6B4226">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="manifest" href="<?php echo $base_url; ?>/manifest.json">
    <script>
    // Register the service worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
        navigator.serviceWorker.register('<?php echo $base_url; ?>/sw.js')
            .then(reg => console.log('SW Registered!', reg))
            .catch(err => console.log('SW Registration Failed', err));
        });
    }
    </script>
    <title>FOGS System</title>
    <style>
        :root { --p-brown: #8D6E63; --d-brown: #6B4226; --tan: #C58F63; --cream: #F2E7D5; }
        body { margin: 0; font-family: sans-serif; background: var(--cream); display: flex; align-items: center; justify-content: center; height: 100vh; overflow: hidden; }
        .login-container { width: 100%; max-width: 380px; background: var(--p-brown); padding: 30px; border-radius: 20px; color: white; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .mode-selector { display: flex; background: rgba(0,0,0,0.2); border-radius: 10px; margin-bottom: 20px; padding: 5px; }
        .mode-btn { flex: 1; padding: 12px; border: none; background: transparent; color: white; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .mode-btn.active { background: var(--tan); }
        #pass-display { width: 100%; font-size: 2.2rem; background: rgba(0,0,0,0.2); border: none; color: white; text-align: center; margin-bottom: 20px; padding: 15px 0; border-radius: 10px; letter-spacing: 10px; }
        .numpad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .btn { padding: 20px; font-size: 1.5rem; border: none; border-radius: 12px; background: var(--tan); color: white; cursor: pointer; }
        .btn:active { background: var(--d-brown); transform: scale(0.98); }
        .btn.clear { background: #c62828; }
        .btn.enter { background: #2e7d32; font-size: 1rem; }
        .msg { margin-top: 15px; font-weight: bold; padding: 10px; border-radius: 8px; }
        .error { background: #ef5350; }
        .success { background: #66bb6a; }
    </style>
</head>
<body>

<div class="login-container">
    <img src="assets/logo.png" style="max-width:70px; margin-bottom:10px;">
    <h2 style="margin-bottom: 20px;">FOGS SYSTEM</h2>

    <div class="mode-selector">
        <button type="button" id="t-login" class="mode-btn active" onclick="setMode('login')">POS LOGIN</button>
        <button type="button" id="t-punch" class="mode-btn" onclick="setMode('punch')">TIME PUNCH</button>
    </div>
    
    <form method="POST">
        <input type="hidden" name="action_mode" id="action_mode" value="login">
        <input type="password" name="passcode" id="pass-display" readonly placeholder="••••">
        
        <div class="numpad">
            <?php for($i=1; $i<=9; $i++): ?>
                <button type="button" class="btn" onclick="press('<?php echo $i; ?>')"><?php echo $i; ?></button>
            <?php endfor; ?>
            <button type="button" class="btn clear" onclick="press('C')">C</button>
            <button type="button" class="btn" onclick="press('0')">0</button>
            <button type="submit" class="btn enter">ENTER</button>
        </div>
    </form>
    
    <?php if($error): ?><div class="msg error"><?php echo $error; ?></div><?php endif; ?>
    <?php if($success): ?><div class="msg success"><?php echo $success; ?></div><?php endif; ?>
</div>

<script>
    let code = "";
    const disp = document.getElementById('pass-display');
    function setMode(m) {
        document.getElementById('action_mode').value = m;
        document.getElementById('t-login').classList.toggle('active', m==='login');
        document.getElementById('t-punch').classList.toggle('active', m==='punch');
        code = ""; disp.value = "";
    }
    function press(v) {
        if(v==='C') code = ""; else code += v;
        disp.value = code;
    }
</script>
</body>
</html>