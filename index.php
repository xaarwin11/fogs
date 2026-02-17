<?php
session_start();

// If not logged in, show the login page content or redirect
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// If they ARE logged in, this is your actual POS Dashboard
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>FOGS POS</title>
    
    <link rel="manifest" href="/fogs-1/manifest.json">
    <link rel="stylesheet" href="/fogs-1/assets/style.css">
    
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body>
   <script>
     // Register the Service Worker
     if ('serviceWorker' in navigator) {
       navigator.serviceWorker.register('/fogs-1/sw.js', { scope: '/fogs-1/' });
     }
   </script>
</body>
</html>