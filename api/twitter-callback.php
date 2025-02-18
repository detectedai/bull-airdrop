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

// Veritabanı bağlantısı
try {
    $db = new PDO(
        "mysql:host=localhost;dbname=bull_airdrop;charset=utf8mb4",
        "root",
        "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    writeLog('Database error: ' . $e->getMessage());
    die(json_encode(['error' => 'Database connection failed']));
}

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

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Abraham\TwitterOAuth\TwitterOAuth;

writeLog('Callback başladı');
writeLog('Request: ' . print_r($_REQUEST, true));
writeLog('Session: ' . print_r($_SESSION, true));

if (isset($_REQUEST['oauth_token']) && isset($_REQUEST['oauth_verifier'])) {
    writeLog('OAuth token ve verifier mevcut');
    
    if (!isset($_SESSION['oauth_token']) || !isset($_SESSION['oauth_token_secret'])) {
        writeLog('Session token\'ları eksik');
        die("<script>
            window.opener.postMessage('twitter-login-error', 'https://bullfnf.xyz');
            window.close();
        </script>");
    }

    $request_token = [
        'oauth_token' => $_SESSION['oauth_token'],
        'oauth_token_secret' => $_SESSION['oauth_token_secret']
    ];

    writeLog('Request token: ' . print_r($request_token, true));

    if ($request_token['oauth_token'] !== $_REQUEST['oauth_token']) {
        writeLog('Token uyuşmazlığı');
        die("<script>
            window.opener.postMessage('twitter-login-error', 'https://bullfnf.xyz');
            window.close();
        </script>");
    }

    try {
        writeLog('Access token alınıyor...');
        
        $connection = new TwitterOAuth(
            $_ENV['TWITTER_CLIENT_ID'],
            $_ENV['TWITTER_CLIENT_SECRET'],
            $request_token['oauth_token'],
            $request_token['oauth_token_secret']
        );

        // Rate limit için bekleme süresi ekle
        $connection->setTimeouts(10, 15);

        $access_token = $connection->oauth(
            'oauth/access_token',
            ['oauth_verifier' => $_REQUEST['oauth_verifier']]
        );

        writeLog('Access token alındı: ' . print_r($access_token, true));

        $_SESSION['access_token'] = $access_token;
        $_SESSION['screen_name'] = $access_token['screen_name'];
        
        // Kullanıcıyı veritabanına kaydet veya güncelle
        $stmt = $db->prepare("INSERT INTO users (twitter_username, oauth_token, oauth_token_secret, user_id, total_points) 
                             VALUES (?, ?, ?, ?, 0) 
                             ON DUPLICATE KEY UPDATE 
                             oauth_token = VALUES(oauth_token),
                             oauth_token_secret = VALUES(oauth_token_secret),
                             user_id = VALUES(user_id)");
        $stmt->execute([
            $access_token['screen_name'],
            $access_token['oauth_token'],
            $access_token['oauth_token_secret'],
            $access_token['user_id']
        ]);
        
        writeLog('Kullanıcı veritabanına kaydedildi');
        writeLog('Final session: ' . print_r($_SESSION, true));

        // JavaScript ile parent window'a mesaj gönder
        echo "<script>
            window.opener.postMessage('twitter-login-success', 'https://bullfnf.xyz');
            window.close();
        </script>";
        
        writeLog('Callback tamamlandı - başarılı');
    } catch (Exception $e) {
        writeLog('Hata: ' . $e->getMessage());
        writeLog('Stack trace: ' . $e->getTraceAsString());
        
        echo "<script>
            window.opener.postMessage('twitter-login-error', 'https://bullfnf.xyz');
            window.close();
        </script>";
    }
} else {
    writeLog('Geçersiz callback isteği - oauth_token veya oauth_verifier eksik');
    echo "<script>
        window.opener.postMessage('twitter-login-error', 'https://bullfnf.xyz');
        window.close();
    </script>";
}