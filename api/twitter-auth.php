<?php
// Debug için log
$logFile = __DIR__ . '/debug.log';
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// CORS ayarları
$allowed_origins = [
    'https://bullfnf.xyz',
    'https://bullfnf.xyz:443',
    'https://46.229.250.143',
    'https://46.229.250.143:443'
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

// Debug için origin bilgisini logla
writeLog('Request origin: ' . $origin);

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $origin);
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
}

session_start();

// Kesin yol kullanarak vendor'a erişim
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
error_log("Trying to load autoload.php from: " . $autoloadPath);

if (!file_exists($autoloadPath)) {
    die(json_encode([
        'error' => 'Autoload not found',
        'path_tried' => $autoloadPath,
        'current_dir' => __DIR__,
        'parent_dir' => dirname(__DIR__),
        'files_in_parent' => scandir(dirname(__DIR__))
    ]));
}

require_once $autoloadPath;

// .env dosyasını yükle
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

use Abraham\TwitterOAuth\TwitterOAuth;

try {
    writeLog("Twitter auth başlatılıyor...");
    writeLog("Session öncesi: " . print_r($_SESSION, true));
    
    $connection = new TwitterOAuth(
        $_ENV['TWITTER_CLIENT_ID'],
        $_ENV['TWITTER_CLIENT_SECRET']
    );
    
    writeLog("OAuth nesnesi oluşturuldu");
    
    $request_token = $connection->oauth('oauth/request_token', [
        'oauth_callback' => $_ENV['TWITTER_CALLBACK_URL']
    ]);
    
    writeLog("Request token alındı: " . print_r($request_token, true));
    
    $_SESSION['oauth_token'] = $request_token['oauth_token'];
    $_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];
    
    writeLog("Session güncellendi: " . print_r($_SESSION, true));
    
    $url = $connection->url('oauth/authorize', [
        'oauth_token' => $request_token['oauth_token']
    ]);
    
    writeLog("Auth URL oluşturuldu: " . $url);
    
    header('Location: ' . $url);
} catch (Exception $e) {
    writeLog("HATA: " . $e->getMessage());
    writeLog("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}