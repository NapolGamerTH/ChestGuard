<?php
namespace Napol;
class ChestGuardLogger {
    private $logResource;
    public function __construct($logPath) {
        touch($logPath);
        $this->logResource = fopen($logPath, 'a');
    }
    public function __destruct() {
        fclose($this->logResource);
    }
    public function log($message)
    {
        fwrite($this->logResource, "<" . date("Ymd") . ">" . $message . PHP_EOL);
    }
} 
