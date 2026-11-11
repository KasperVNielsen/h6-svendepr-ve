<?php

require_once 'db.php';


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (isset($_POST['action'])) {

        if (!isset($_SESSION['user_id'])) {
            die("You must be logged in.");
        }

        $id = (int) $_SESSION['user_id'];

        switch ($_POST['action']) {

            case 'update_profile':
                $message = updateProfile($id, $_POST);
                break;

            case 'change_password':
                $message = changePassword(
                    $id,
                    $_POST['current_password'],
                    $_POST['new_password']
                );
                break;
        }

        $_SESSION['message'] = $message ?? null;

        header("Location: profile.php");
        exit;
    }
}


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
            $value = trim($data[$field]);

            // Check required fields
            if (in_array($field, ['first_name', 'last_name', 'city']) && empty($value)) {
                return [
                    'success' => false,
                    'message' => ucfirst($field) . ' skal udfyldes.'
                ];
            }

            // Check maximum length
            if (strlen($value) > 100) {
                return [
                    'success' => false,
                    'message' => 'Et eller flere felter er for lange.'
                ];
            }

            // Validate IBAN if provided
            if ($field === 'iban' && !empty($value)) {
                $iban = strtoupper(str_replace(' ', '', $value));

                if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/', $iban)) {
                    return [
                        'success' => false,
                        'message' => 'Ugyldigt IBAN.'
                    ];
                }

                $value = $iban;
            }

            $fields[] = "$field = ?";
            $values[] = $value;
        }
    }

    if (empty($fields)) {
        return ['success' => false, 'message' => 'Ingen gyldige felter at opdatere.'];
    }

    $values[] = $id;

    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";

    $stmt = $pdo->prepare($sql);

    $stmt->execute($values);

    return [
        'success' => true,
        'message' => 'Profil opdateret!'
    ];
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
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Nuværende password er forkert.'];
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
