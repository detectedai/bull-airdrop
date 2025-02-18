<?php
$db = new PDO(
    "mysql:host=localhost;dbname=bull_airdrop;charset=utf8mb4",
    "root",
    "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);