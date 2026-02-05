<?php
require __DIR__ . '/library/printerService.php';
// Change 'Receipt' to your actual Shared Name
$p = new PrinterService('usb', 'VOZY-80'); 
if ($p->isValid()) {
    $p->printTicket("TEST PRINT", [['quantity'=>1, 'name'=>'System Check', 'price'=>0]], ['table'=>'99', 'staff'=>'Admin'], true);
    echo "Printed!";
} else {
    echo "Connection Failed.";
}
?>