<?php

require_once 'db.php';


function getUser(int $id)
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT id, role_id, email, first_name, last_name, phone,
               profile_image, address, zip_code, city, iban,
               balance, is_active, is_verified, last_login, created_at
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}


