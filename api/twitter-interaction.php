<?php
session_start();
header("Access-Control-Allow-Origin: https://bullfnf.xyz");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Debug için log
$logFile = __DIR__ . '/debug.log';
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

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
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (!isset($_SESSION['access_token'])) {
    writeLog('No access token found in session');
    die(json_encode(['success' => false, 'error' => 'Not authenticated']));
}

$input = json_decode(file_get_contents('php://input'), true);
writeLog('Received input: ' . print_r($input, true));

if (!isset($input['tweet_id']) || !isset($input['action'])) {
    writeLog('Missing tweet_id or action in request');
    die(json_encode(['success' => false, 'error' => 'Invalid request']));
}

$tweet_id = $input['tweet_id'];
$action = $input['action'];
$twitter_username = $_SESSION['screen_name'];

writeLog("Processing {$action} for tweet {$tweet_id} by user {$twitter_username}");

try {
    // Daha önce etkileşim yapılmış mı kontrol et
    $stmt = $db->prepare("SELECT id FROM interactions WHERE twitter_username = ? AND tweet_id = ? AND interaction_type = ?");
    $stmt->execute([$twitter_username, $tweet_id, $action]);
    
    if ($stmt->rowCount() > 0) {
        writeLog('User already interacted with this tweet');
        die(json_encode(['success' => false, 'error' => 'Already interacted with this tweet']));
    }

    // Twitter API ile etkileşim
    $connection = new TwitterOAuth(
        $_ENV['TWITTER_CLIENT_ID'],
        $_ENV['TWITTER_CLIENT_SECRET'],
        $_SESSION['access_token']['oauth_token'],
        $_SESSION['access_token']['oauth_token_secret']
    );

    // Rate limit için bekleme süresi ekle
    $connection->setTimeouts(10, 15);
    
    // API v2'yi kullan
    $connection->setApiVersion('2');

    writeLog('Twitter API credentials:');
    writeLog('Client ID: ' . $_ENV['TWITTER_CLIENT_ID']);
    writeLog('Access Token: ' . $_SESSION['access_token']['oauth_token']);

    if ($action === 'like') {
        writeLog('Attempting to like tweet');
        $endpoint = "users/" . $_SESSION['access_token']['user_id'] . "/likes";
        writeLog('Twitter API endpoint: ' . $endpoint);
        $result = $connection->post($endpoint, ["tweet_id" => $tweet_id]);
        writeLog('Twitter API response: ' . print_r($result, true));
        writeLog('HTTP Code: ' . $connection->getLastHttpCode());

        if ($connection->getLastHttpCode() == 429) {
            // Rate limit hatası - kullanıcıya özel mesaj
            writeLog('Rate limit error encountered');
            die(json_encode([
                'success' => false, 
                'error' => 'Twitter işlem limitine ulaşıldı. Lütfen birkaç dakika bekleyip tekrar deneyin.',
                'rate_limit' => true
            ]));
        } else if ($connection->getLastHttpCode() != 200) {
            writeLog('Twitter API error: ' . ($result->detail ?? 'Unknown error'));
            die(json_encode([
                'success' => false, 
                'error' => 'Twitter API hatası: ' . ($result->detail ?? 'Bilinmeyen hata')
            ]));
        }
    } else if ($action === 'retweet') {
        writeLog('Attempting to retweet');
        $endpoint = "users/" . $_SESSION['access_token']['user_id'] . "/retweets";
        writeLog('Twitter API endpoint: ' . $endpoint);
        $result = $connection->post($endpoint, ["tweet_id" => $tweet_id]);
        writeLog('Twitter API response: ' . print_r($result, true));
        writeLog('HTTP Code: ' . $connection->getLastHttpCode());

        if ($connection->getLastHttpCode() == 429) {
            // Rate limit hatası - kullanıcıya özel mesaj
            writeLog('Rate limit error encountered');
            die(json_encode([
                'success' => false, 
                'error' => 'Twitter işlem limitine ulaşıldı. Lütfen birkaç dakika bekleyip tekrar deneyin.',
                'rate_limit' => true
            ]));
        } else if ($connection->getLastHttpCode() != 200) {
            writeLog('Twitter API error: ' . ($result->detail ?? 'Unknown error'));
            die(json_encode([
                'success' => false, 
                'error' => 'Twitter API hatası: ' . ($result->detail ?? 'Bilinmeyen hata')
            ]));
        }
    }

    // Etkileşim başarılı oldu, veritabanına kaydet
    try {
        $stmt = $db->prepare("INSERT INTO interactions (twitter_username, tweet_id, interaction_type) VALUES (?, ?, ?)");
        $stmt->execute([$twitter_username, $tweet_id, $action]);
        
        // Kullanıcının toplam puanını güncelle
        $stmt = $db->prepare("UPDATE users SET total_points = total_points + 15000 WHERE twitter_username = ?");
        $stmt->execute([$twitter_username]);
        
        // Güncel puan bilgisini al
        $stmt = $db->prepare("SELECT total_points FROM users WHERE twitter_username = ?");
        $stmt->execute([$twitter_username]);
        $points = $stmt->fetchColumn();
        
        writeLog('Interaction saved successfully');
        echo json_encode([
            'success' => true, 
            'total_points' => (int)$points,
            'message' => ucfirst($action) . ' successful'
        ]);
    } catch (PDOException $e) {
        writeLog('Database error: ' . $e->getMessage());
        die(json_encode([
            'success' => false,
            'error' => 'Veritabanı hatası oluştu'
        ]));
    }
} catch (Exception $e) {
    writeLog('Error: ' . $e->getMessage());
    writeLog('Stack trace: ' . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while processing your request',
        'message' => $e->getMessage()
    ]);
}