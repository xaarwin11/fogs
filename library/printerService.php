<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector; // Added for LAN

class PrinterService
{
    private $printer = null;
    private $connector = null;
    private $charLimit;

    public function __construct($type, $path, $charLimit = 48)
    {
        $this->charLimit = $charLimit;
        try {
            // This detects the "connection_type" column from your database
            if ($type === 'network' || $type === 'lan') { 
                // Uses the IP address stored in the "path" column
                $this->connector = new \Mike42\Escpos\PrintConnectors\NetworkPrintConnector($path, 9100);
            } elseif ($type === 'usb') {
                // Uses the printer name stored in the "path" column
                $this->connector = new WindowsPrintConnector($path);
            } else {
                throw new Exception("Unsupported printer type: " . $type);
            }

            $this->printer = new Printer($this->connector);
        } catch (Exception $e) {
            $this->printer = null;
            // For debugging, you can log $e->getMessage();
        }
    }

    private function columnize($left, $right) {
        $spaces = $this->charLimit - strlen($left) - strlen($right);
        if ($spaces < 1) $spaces = 1;
        return $left . str_repeat(" ", $spaces) . $right . "\n";
    }

    public function isValid() {
        return $this->printer !== null;
    }

    public function printTicket($title, $items, $meta = [], $showPrice = true, $options = [])
    {
        if (!$this->printer) throw new Exception("Printer not initialized");

        $total = 0;

        // Beep only if explicitly requested and not suppressed by hardware
        // NEW CODE - Uses the proper ESC/POS beep command
        if (($options['beep'] ?? 0) == 1) {
            // \x1b\x42 is the command for "Buzzer" 
            // \x02\x02 means beep 2 times for 200ms
            $this->connector->write("\x1b\x42\x02\x02");
        }

        if ($showPrice) {
            try {
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
            $lineTotal = $qty * $price;
            $total += $lineTotal; 
            
            if ($showPrice) {
                $this->printer->text($this->columnize($qty . "x " . $name, number_format($lineTotal, 2)));
            } else {
                $this->printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH);
                $this->printer->text($qty . "x " . $name . "\n");
                $this->printer->selectPrintMode(Printer::MODE_FONT_A);
                $this->printer->text(str_repeat("-", $this->charLimit) . "\n"); 
            }
        }

        if ($showPrice) {
            $this->printer->text(str_repeat("=", $this->charLimit) . "\n");
            $this->printer->setEmphasis(true);
            $this->printer->text($this->columnize("TOTAL", "P" . number_format($total, 2)));
            $this->printer->setEmphasis(false);
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
        $this->printer->cut();
        $this->printer->close();
    }
}