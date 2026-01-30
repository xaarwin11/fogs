<?php

require_once 'db.php';
session_start();

if (!empty($_SESSION['user_id'])) {
    $role = strtolower($_SESSION['role'] ?? '');
    if (in_array($role, ['staff', 'admin', 'manager'])) {
        header('Location: staff/pos/pos.php');
    } else {
        header('Location: customer/dashboard.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please fill in both fields.';
    } else {
        try {
            $mysqli = get_db_conn();
        } catch (Exception $e) {
            error_log('MySQL connect error: ' . $e->getMessage());
            $error = 'An internal error occurred. Try again later.';
            $mysqli = null;
        }

        if ($mysqli) {
            $mysqli->set_charset('utf8mb4');

            $stmt = $mysqli->prepare('SELECT id, username, password, role FROM credentials WHERE username = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $stmt->bind_result($id, $dbUsername, $dbPasswordHash, $dbRole);
                if ($stmt->fetch()) {
                    if (password_verify($password, $dbPasswordHash)) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $id;
                        $_SESSION['username'] = $dbUsername;
                        $_SESSION['role'] = $dbRole;
                        $stmt->close();
                        $mysqli->close();
                        $role = strtolower($dbRole ?? '');
                        if (in_array($role, ['staff','admin','manager'])) {
                            header('Location: staff/pos/pos.php');
                        } else {
                            header('Location: customer/dashboard.php');
                        }
                        exit;
                    } else {
                        $error = 'Invalid username or password';
                    }
                } else {
                    $error = 'Invalid username or password';
                }
                $stmt->close();
            } else {
                error_log('MySQL prepare error: ' . $mysqli->error);
                $error = 'An internal error occurred. Try again later.';
            }

            $mysqli->close();
        }
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .login-container {
            width: 100%;
            max-width: 420px; 
            margin: 6vh auto; 
            padding: 20px;
            background: #8D6E63;
            border-radius: 10px;
            color: #fff;
            box-sizing: border-box;
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        .login-container input { width: 100%; padding: 8px; margin-top: 6px; border-radius: 4px; border: none; box-sizing: border-box; }
        .login-container button { margin-top: 12px; width: 100%; padding: 10px; border-radius: 6px; border: none; background: #C58F63; color: #fff; cursor: pointer; }
        #error { color: #ffdddd; margin-top: 8px; display:block; text-align:center; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: linear-gradient(180deg, #F7F4F0 0%, #F2E7D5 100%); }
    </style>
</head>
<body>
    <div class="login-container">
        <img src="assets/logo.png" alt="Logo" style="display:block;margin:0 auto 12px;max-width:100px;">
        <h2 style="text-align:center;margin:0 0 8px;color:#fff;">Login</h2>
        <form method="POST" action="">
            <br><label for="username">Username</label>
            <input type="text" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            <br><br><label for="password">Password</label>
            <input type="password" id="password" name="password" required>
            <label id="error"><?php echo $error ? htmlspecialchars($error, ENT_QUOTES, 'UTF-8') : ''; ?></label>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
