<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

class PrinterService
{
    private $printer = null;
    private $connector = null;
    private $error = null;
    private $charLimit; 

    public function __construct($type, $path, $charLimit = 48)
    {
        $this->charLimit = $charLimit;
        try {
            if ($type !== 'usb') {
                throw new Exception("Unsupported printer type");
            }
            $this->connector = new WindowsPrintConnector($path);
            $this->printer   = new Printer($this->connector);
        } catch (Exception $e) {
            $this->printer = null;
            $this->error   = $e->getMessage();
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

        // 1. FIX: Initialize total to avoid "Undefined variable" error
        $total = 0;

        if (!empty($options['beep'])) $this->printer->pulse();

        if ($showPrice) {
            try {
                $logo = EscposImage::load(__DIR__ . "/../assets/print.png");

                // 1. Reset printer state
                $this->printer->initialize(); 

                // 2. Set justification to CENTER
                $this->printer->setJustification(Printer::JUSTIFY_CENTER);
                
                // 3. Print the image using bitImage. 
                // Mode 0 is the standard, non-stretched size.
                $this->printer->bitImage($logo, 0); 
                
                // 4. Feed a line and reset to LEFT for the billing text
                $this->printer->feed(1);

            } catch (Exception $e) {
                // Silence errors to prevent garbage text on paper
            }

            $this->printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $this->printer->text(($meta['Store'] ?? "FOGS RESTAURANT") . "\n");
            $this->printer->selectPrintMode(Printer::MODE_FONT_A);
            
            if (!empty($meta['Address'])) $this->printer->text($meta['Address'] . "\n");
            if (!empty($meta['Phone'])) $this->printer->text("Tel: " . $meta['Phone'] . "\n");
        } else {
            $this->printer->selectPrintMode(Printer::MODE_FONT_A);
        }
        // --- 2. SHARED INFO ---
        $this->printer->setJustification(Printer::JUSTIFY_LEFT);
        $this->printer->setEmphasis(true);
        if (isset($meta['Table'])) $this->printer->text("TABLE: " . $meta['Table'] . "\n");
        $this->printer->setEmphasis(false);
        
        if (isset($meta['Staff'])) $this->printer->text("STAFF: " . $meta['Staff'] . "\n");
        if (isset($meta['Date']))  $this->printer->text("TIME:  " . $meta['Date'] . "\n");


        if ($showPrice){
            $this->printer->feed(1);
            $this->printer->setEmphasis(true);
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT);
            $this->printer->text("BILL STATEMENT\n");
            $this->printer->setEmphasis(false);
            $this->printer->selectPrintMode(Printer::MODE_FONT_A);
            $this->printer->text(str_repeat("=", $this->charLimit) . "\n");
        }
            

        // --- 3. ITEMS LOOP ---
        foreach ($items as $item) {
            $qty   = (int)$item['quantity'];
            $name  = $item['name'];
            $price = (float)($item['price'] ?? 0);
            $lineTotal = $qty * $price;
            $total += $lineTotal; 
            
            if ($showPrice) {
                $leftStr = $qty . "x " . $name;
                $rightStr = number_format($lineTotal, 2);
                $this->printer->text($this->columnize($leftStr, $rightStr));
            } else {
                $this->printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH);
                $this->printer->text($qty . "x " . $name . "\n");
                $this->printer->selectPrintMode(Printer::MODE_FONT_A);
                $this->printer->text(str_repeat("-", $this->charLimit) . "\n"); 
            }
        }

        // --- 4. FOOTER ---
        if ($showPrice) {
            $this->printer->text(str_repeat("=", $this->charLimit) . "\n");
            $this->printer->setEmphasis(true);
            $this->printer->text($this->columnize("TOTAL", "P" . number_format($total, 2)));
            $this->printer->setEmphasis(false);
            $this->printer->feed(1);
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->text("Thank you for dining with us!\n");
            $this->printer->text("This is not your OFFICIAL RECEIPT!\n");
        }

        // --- 5. FINISHING ---
        $this->printer->feed(3);
        if (!empty($options['cut'])) {
            $this->printer->cut();
        }
        $this->printer->close();
    }
}