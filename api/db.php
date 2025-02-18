<?php
header("Access-Control-Allow-Origin: https://bullfnf.xyz");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// OPTIONS request için erken yanıt
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = "localhost";
$user = "root";
$password = "";
$database = "bull_airdrop";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

// Create table if not exists
$sql = "CREATE TABLE IF NOT EXISTS registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    x_account VARCHAR(255) NOT NULL,
    wallet VARCHAR(255) NOT NULL,
    reference_code VARCHAR(255),
    points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$conn->query($sql);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $xAccount = $conn->real_escape_string($data["xAccount"]);
    $wallet = $conn->real_escape_string($data["wallet"]);
    $referenceCode = isset($data["referenceCode"]) ? $conn->real_escape_string($data["referenceCode"]) : '';

    if (empty($xAccount) || empty($wallet)) {
        echo json_encode(["error" => "X Account and Wallet are required!"]);
        exit;
    }

    // Önce bu X hesabının daha önce kayıt olup olmadığını kontrol et
    $checkExisting = $conn->query("SELECT * FROM registrations WHERE x_account = '$xAccount'");
    if ($checkExisting->num_rows > 0) {
        echo json_encode(["error" => "This X account is already registered!"]);
        exit;
    }

    if (!empty($referenceCode)) {
        // Referans kodu verilmiş, kontrolleri yapalım
        
        // 1. Referans veren kullanıcı users tablosunda var mı?
        $referrerCheck = $conn->query("SELECT * FROM users WHERE twitter_username = '$referenceCode'");
        if ($referrerCheck->num_rows === 0) {
            // Referans kodu geçersiz, ama yine de kaydı yapalım
            $referenceCode = ''; // Referans kodunu temizle
        } else {
            // 2. Bu referans veren kullanıcı daha önce referans vermiş mi?
            $referralCheck = $conn->query("SELECT * FROM registrations WHERE reference_code = '$referenceCode'");
            if ($referralCheck->num_rows > 0) {
                // Referans zaten kullanılmış, ama yine de kaydı yapalım
                $referenceCode = ''; // Referans kodunu temizle
            } else {
                // 3. Referans veren kullanıcıya 2000 puan ekle
                $conn->query("UPDATE users SET total_points = total_points + 2000 WHERE twitter_username = '$referenceCode'");
            }
        }
    }

    // Yeni kaydı oluştur
    $sql = "INSERT INTO registrations (x_account, wallet, reference_code) VALUES ('$xAccount', '$wallet', '$referenceCode')";
    
    if ($conn->query($sql) === TRUE) {
        echo json_encode([
            "success" => true, 
            "message" => "Registration successful!",
            "referralApplied" => !empty($referenceCode)
        ]);
    } else {
        echo json_encode(["error" => "Error occurred: " . $conn->error]);
    }
}

$conn->close();
?>