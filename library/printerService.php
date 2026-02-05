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
    private $charLimit; // 80mm = 48, 58mm = 32

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

    // Helper to align text to the far right based on character limit
    private function columnize($left, $right) {
        $spaces = $this->charLimit - strlen($left) - strlen($right);
        if ($spaces < 1) $spaces = 1;
        return $left . str_repeat(" ", $spaces) . $right . "\n";
    }

    public function isValid() {
        return $this->printer !== null;
    }

    /**
     * @param string $title
     * @param array $items
     * @param array $meta
     * @param bool $showPrice
     * @param array $options Added to handle hardware beep and cut
     */
    public function printTicket($title, $items, $meta = [], $showPrice = true, $options = [])
    {
        if (!$this->printer) {
            throw new Exception("Printer not initialized");
        }

        // --- 0. HARDWARE PRE-PRINT (BEEP) ---
        if (!empty($options['beep'])) {
            // Pulse triggers the internal buzzer/cash drawer port
            $this->printer->pulse(); 
        }

        // --- 1. HEADER ---
        $this->printer->setJustification(Printer::JUSTIFY_CENTER);
        $this->printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH);
        $this->printer->text($title . "\n");
        $this->printer->selectPrintMode(Printer::MODE_FONT_A); // Reset to normal
        $this->printer->text(str_repeat("-", $this->charLimit) . "\n");

        // --- 2. META INFO ---
        $this->printer->setJustification(Printer::JUSTIFY_LEFT);
        foreach ($meta as $k => $v) {
            $this->printer->text(strtoupper($k) . ": " . $v . "\n");
        }
        $this->printer->text(str_repeat("-", $this->charLimit) . "\n");

        // --- 3. ITEMS ---
        $total = 0;
        foreach ($items as $item) {
            $qty   = (int)$item['quantity'];
            $name  = $item['name'];
            $price = (float)$item['price'];
            $lineTotal = $qty * $price;
            $total += $lineTotal;

            if ($showPrice) {
                // BILL FORMAT: Normal text, aligned prices
                $leftStr = $qty . "x " . $name;
                $rightStr = number_format($lineTotal, 2);
                $this->printer->text($this->columnize($leftStr, $rightStr));
            } else {
                // KITCHEN FORMAT: Large text, NO prices
                $this->printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH);
                $this->printer->text($qty . "x " . $name . "\n");
                $this->printer->selectPrintMode(Printer::MODE_FONT_A);
            }
        }

        // --- 4. FOOTER (Only for Bills) ---
        if ($showPrice) {
            $this->printer->text(str_repeat("-", $this->charLimit) . "\n");
            $this->printer->setEmphasis(true);
            $this->printer->text($this->columnize("TOTAL", "P" . number_format($total, 2)));
            $this->printer->setEmphasis(false);
            $this->printer->text(str_repeat("-", $this->charLimit) . "\n");
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->text("Thank you for dining with us!\n");
        }

        // --- 5. FINISHING (FEED & CUT) ---
        $this->printer->feed(3);

        if (!empty($options['cut'])) {
            $this->printer->cut();
        }

        $this->printer->close();
    }
}