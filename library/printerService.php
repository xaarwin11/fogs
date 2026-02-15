<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;

class PrinterService
{
    private $printer = null;
    private $connector = null;
    private $charLimit;

    public function __construct($type, $path, $charLimit = 32, $port = 9100)
    {
        $this->charLimit = $charLimit;
        try {
            // Support for your new LAN/Network type
            if ($type === 'network' || $type === 'lan') { 
                // 3 second timeout so it doesn't hang your POS
                $this->connector = new NetworkPrintConnector($path, $port, 3);
            } elseif ($type === 'usb' || $type === 'windows') {
                $this->connector = new WindowsPrintConnector($path);
            } else {
                throw new Exception("Unsupported printer type: " . $type);
            }

            if ($this->connector) {
                $this->printer = new Printer($this->connector);
            }
        } catch (Exception $e) {
            $this->printer = null;
            // Throwing this allows print_order.php to catch it and show the Swal warning
            throw new Exception($e->getMessage());
        }
    }

    private function columnize($left, $right) {
        $spaces = $this->charLimit - strlen($left) - strlen($right);
        if ($spaces < 1) $spaces = 1;
        return $left . str_repeat(" ", $spaces) . $right . "\n";
    }

    public function printTicket($title, $items, $meta = [], $showPrice = true, $options = [])
    {
        if (!$this->printer) throw new Exception("Printer not connected.");

        $total = 0;

        // Beep command
        if (($options['beep'] ?? 0) == 1) {
            $this->connector->write("\x1b\x42\x02\x02");
        }

        if ($showPrice) {
            try {
                // YOUR LOGO LOGIC
                $logo = EscposImage::load(__DIR__ . "/../assets/print.png");
                $this->printer->initialize(); 
                $this->printer->setJustification(Printer::JUSTIFY_CENTER);
                $this->printer->bitImage($logo, 0); 
                $this->printer->feed(1);
            } catch (Exception $e) {
                // Logo fail shouldn't stop the print
            }

            $this->printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $this->printer->text(($meta['Store'] ?? "FOGS RESTAURANT") . "\n");
            $this->printer->selectPrintMode(Printer::MODE_FONT_A);
            
            if (!empty($meta['Address'])) $this->printer->text($meta['Address'] . "\n");
            if (!empty($meta['Phone'])) $this->printer->text("Tel: " . $meta['Phone'] . "\n");
            
            $this->printer->feed(1);
            $this->printer->setEmphasis(true);
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT);
            $this->printer->text("BILL STATEMENT\n");
            $this->printer->setEmphasis(false);
            $this->printer->selectPrintMode(Printer::MODE_FONT_A);
            $this->printer->text(str_repeat("=", $this->charLimit) . "\n");
        }

        $this->printer->setJustification(Printer::JUSTIFY_LEFT);
        if (isset($meta['Table'])) {
            $this->printer->setEmphasis(true);
            $this->printer->text("TABLE: " . $meta['Table'] . "\n");
            $this->printer->setEmphasis(false);
        }
        
        if (isset($meta['Staff'])) $this->printer->text("STAFF: " . $meta['Staff'] . "\n");
        if (isset($meta['Date']))  $this->printer->text("TIME:  " . $meta['Date'] . "\n");
        $this->printer->text(str_repeat("-", $this->charLimit) . "\n");

        foreach ($items as $item) {
            $qty   = (int)$item['quantity'];
            $name  = $item['name'];
            $price = (float)($item['price'] ?? 0); 
            $item_discount = (float)($item['discount'] ?? 0);
            $discount_note = !empty($item['discount_note']) ? $item['discount_note'] : "Discount";
            
            $lineTotal = ($qty * $price) - $item_discount;
            $total += $lineTotal; 
            
            if ($showPrice) {
                $this->printer->text($this->columnize($qty . "x " . $name, number_format($qty * $price, 2)));
                
                if (!empty($item['modifiers'])) {
                    foreach ($item['modifiers'] as $mod) {
                        $this->printer->text("  + " . ($mod['name'] ?? $mod) . "\n");
                    }
                }

                if ($item_discount > 0) {
                    $this->printer->text($this->columnize("  (" . $discount_note . ")", "-" . number_format($item_discount, 2)));
                }
            } else {
                // KITCHEN MODE
                $this->printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT);
                $this->printer->text($qty . "x " . $name . "\n");
                $this->printer->selectPrintMode(Printer::MODE_FONT_A);
                if (!empty($item['modifiers'])) {
                    foreach ($item['modifiers'] as $mod) {
                        $this->printer->text("  + " . ($mod['name'] ?? $mod) . "\n");
                    }
                }
                $this->printer->text(str_repeat("-", $this->charLimit) . "\n"); 
            }
        }

        if ($showPrice) {
            $order_discount = (float)($meta['OrderDiscount'] ?? 0);
            $order_discount_label = !empty($meta['OrderDiscountNote']) ? strtoupper($meta['OrderDiscountNote']) : "DISCOUNT";
            $this->printer->text(str_repeat("=", $this->charLimit) . "\n");
            
            if ($order_discount > 0) {
                $this->printer->text($this->columnize("SUBTOTAL", number_format($total, 2)));
                $this->printer->text($this->columnize($order_discount_label, "-" . number_format($order_discount, 2)));
                $total -= $order_discount;
            }

            // TOTAL LINE
            $this->printer->setEmphasis(true);
            $this->printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            
            $doubleLimit = floor($this->charLimit / 2);
            $left = "TOTAL";
            $right = "P" . number_format($total, 2);
            $spaces = $doubleLimit - strlen($left) - strlen($right);
            if ($spaces < 1) $spaces = 1;
            
            $this->printer->text($left . str_repeat(" ", $spaces) . $right . "\n");
            
            $this->printer->setEmphasis(false);
            $this->printer->selectPrintMode(Printer::MODE_FONT_A); 

            $this->printer->feed(1);
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->text("Thank you for dining with us!\n");
        }

        $this->printer->feed(3);

        if (($options['cut'] ?? 0) == 1) {
            $this->printer->cut();
        } else {
            $this->printer->feed(3);
        }
        $this->printer->close();
    }
}