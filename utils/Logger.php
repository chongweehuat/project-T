<?php
$debug = false; // Global debug flag

function logMessage($message) {
    global $debug;
    if ($debug) {
        $logFile = '/var/www/api/logs/debug.log'; // Path to log file
        $timestamp = date('Y-m-d H:i:s');
        error_log("[$timestamp] $message\n", 3, $logFile);
    }
}
?>
