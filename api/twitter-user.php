<?php
require_once __DIR__ . '/../vendor/autoload.php';

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

header("Content-Type: application/json");

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

use Abraham\TwitterOAuth\TwitterOAuth;

writeLog('User endpoint başladı');
writeLog('Session durumu: ' . print_r($_SESSION, true));

if (isset($_SESSION['access_token'])) {
    $access_token = $_SESSION['access_token'];
    writeLog('Access token bulundu: ' . print_r($access_token, true));
    
    try {
        $connection = new TwitterOAuth(
            $_ENV['TWITTER_CLIENT_ID'],
            $_ENV['TWITTER_CLIENT_SECRET'],
            $access_token['oauth_token'],
            $access_token['oauth_token_secret']
        );

        // Rate limit için bekleme süresi ekle
        $connection->setTimeouts(10, 15);
        
        // Kullanıcı bilgilerini veritabanından al
        $stmt = $db->prepare("SELECT * FROM users WHERE twitter_username = ?");
        $stmt->execute([$access_token['screen_name']]);
        $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

        $response = [];
        
        if (!$dbUser) {
            // Kullanıcı veritabanında yoksa Twitter'dan bilgileri al
            $user = $connection->get('users/me', ['user.fields' => 'profile_image_url'], true);
            
            if ($connection->getLastHttpCode() == 200) {
                // Yeni kullanıcıyı veritabanına kaydet
                $stmt = $db->prepare("INSERT INTO users (twitter_username, total_points, oauth_token, oauth_token_secret, user_id) VALUES (?, 0, ?, ?, ?)");
                $stmt->execute([
                    $user->data->username,
                    $access_token['oauth_token'],
                    $access_token['oauth_token_secret'],
                    $access_token['user_id']
                ]);
                
                // Tweet etkileşimlerini kontrol et
                $hasLiked = checkInteraction($db, $user->data->username, $_ENV['VITE_TWEET_ID_TO_LIKE'], 'like');
                $hasRetweeted = checkInteraction($db, $user->data->username, $_ENV['VITE_TWEET_ID_TO_RETWEET'], 'retweet');
                
                $response = [
                    'success' => true,
                    'username' => $user->data->username,
                    'name' => $user->data->name,
                    'profile_image_url' => $user->data->profile_image_url,
                    'total_points' => 0,
                    'hasLiked' => $hasLiked,
                    'hasRetweeted' => $hasRetweeted
                ];
            } else {
                // Rate limit durumunda veritabanındaki bilgileri kullan
                $response = [
                    'success' => true,
                    'username' => $access_token['screen_name'],
                    'name' => $access_token['screen_name'],
                    'profile_image_url' => '',
                    'total_points' => 0,
                    'hasLiked' => false,
                    'hasRetweeted' => false
                ];
            }
        } else {
            // Tweet etkileşimlerini kontrol et
            $hasLiked = checkInteraction($db, $dbUser['twitter_username'], $_ENV['VITE_TWEET_ID_TO_LIKE'], 'like');
            $hasRetweeted = checkInteraction($db, $dbUser['twitter_username'], $_ENV['VITE_TWEET_ID_TO_RETWEET'], 'retweet');
            
            // Kullanıcı veritabanında varsa, mevcut bilgileri kullan
            $response = [
                'success' => true,
                'username' => $dbUser['twitter_username'],
                'name' => $dbUser['twitter_username'],
                'profile_image_url' => '',
                'total_points' => (int)$dbUser['total_points'],
                'hasLiked' => $hasLiked,
                'hasRetweeted' => $hasRetweeted
            ];
        }
        
        writeLog('Response: ' . json_encode($response));
        echo json_encode($response);
        
    } catch (Exception $e) {
        writeLog('Hata: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    writeLog('Session\'da access token yok');
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'No active session'
    ]);
}

// Etkileşim kontrolü için yardımcı fonksiyon
function checkInteraction($db, $username, $tweetId, $type) {
    $stmt = $db->prepare("SELECT id FROM interactions WHERE twitter_username = ? AND tweet_id = ? AND interaction_type = ?");
    $stmt->execute([$username, $tweetId, $type]);
    return $stmt->rowCount() > 0;
}