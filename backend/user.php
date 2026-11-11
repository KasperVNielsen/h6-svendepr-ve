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

function updateProfile(int $id, array $data)
{
    global $pdo;
    
    $allowed = ['first_name', 'last_name', 'phone', 'address', 'zip_code', 'city', 'iban'];
    $fields  = [];
    $values  = [];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $data)) {
            $fields[] = "$field = ?";
            $values[] = $data[$field];
        }
    }

   if (empty($fields)) {
    return ['success' => false, 'message' => 'Ingen gyldige felter at opdatere.'];
    }

    $values[] = $id; 
    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($values);
}

function changePassword(int $id, string $currentPassword, string $newPassword)
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return ['success' => false, 'message' => 'Bruger ikke fundet.'];
    }


   if (password_verify($newPassword, $user['password_hash'])) {
    return ['success' => false, 'message' => 'Nyt password må ikke være det samme som det gamle.'];
    }

  
    if (strlen($newPassword) < 8) {
        return ['success' => false, 'message' => 'Nyt password skal være mindst 8 tegn.'];
    }

 
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$newHash, $id]);

    return ['success' => true, 'message' => 'Password ændret.'];
}
