<?php
require __DIR__ . '/library/printerService.php';
// Change 'Receipt' to your actual Shared Name
$p = new PrinterService('lan', '192.168.1.100'); 
if ($p->isValid()) {
    $p->printTicket("TEST PRINT", [['quantity'=>1, 'name'=>'System Check', 'price'=>0]], ['table'=>'99', 'staff'=>'Admin'], true);
    $p->printercut();
    echo "Printed!";
} else {
    echo "Connection Failed.";
}
?>