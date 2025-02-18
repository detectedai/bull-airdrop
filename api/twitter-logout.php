<?php
session_start();
header("Access-Control-Allow-Origin: https://bullfnf.xyz");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Debug için log
$logFile = __DIR__ . '/debug.log';
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog("Logout starting...");
writeLog("Session about to clean: " . print_r($_SESSION, true));

try {
    // Session'ı temizle
    session_unset();
    session_destroy();
    
    writeLog("Session cleaned successfully");
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    writeLog("Hata oluştu: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}