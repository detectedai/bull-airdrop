<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once 'db_connect.php';

try {
    $query = "SELECT twitter_username, total_points 
              FROM users 
              WHERE total_points > 0
              ORDER BY total_points DESC 
              LIMIT 10";
              
    $result = $conn->query($query);
    $users = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $users
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}
?>
