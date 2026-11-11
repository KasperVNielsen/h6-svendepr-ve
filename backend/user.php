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

        if (!array_key_exists($field, $data)) {
            continue;
        }

        if (!is_string($data[$field])) {
            return [
                'success' => false,
                'message' => 'Ugyldig input.'
            ];
        }

        $value = trim($data[$field]);

        // Required fields
        if (in_array($field, ['first_name', 'last_name', 'city']) && $value === '') {
            return [
                'success' => false,
                'message' => ucfirst($field) . ' skal udfyldes.'
            ];
        }

        // Allow empty optional fields
        if ($value === '') {
            $fields[] = "$field = ?";
            $values[] = $value;
            continue;
        }

        // First name and last name: letters only
        if (in_array($field, ['first_name', 'last_name'])) {
            if (!preg_match('/\A[\p{L}]+\z/u', $value)) {
                return [
                    'success' => false,
                    'message' => 'Navn må kun indeholde bogstaver.'
                ];
            }
        }

        // Phone: exactly 8 digits
        if ($field === 'phone') {
            if (!preg_match('/\A[0-9]{8}\z/', $value)) {
                return [
                    'success' => false,
                    'message' => 'Telefonnummer skal være præcis 8 cifre.'
                ];
            }
        }

        // Address: letters, numbers and common address characters
        if ($field === 'address') {
            if (!preg_match('/\A[\p{L}0-9\s.,\'\-\/]+\z/u', $value)) {
                return [
                    'success' => false,
                    'message' => 'Adressen indeholder ugyldige tegn.'
                ];
            }
        }

        // ZIP code: exactly 4 digits
        if ($field === 'zip_code') {
            if (!preg_match('/\A[0-9]{4}\z/', $value)) {
                return [
                    'success' => false,
                    'message' => 'Postnummer skal være præcis 4 cifre.'
                ];
            }
        }

        // City: letters and spaces
        if ($field === 'city') {
            if (!preg_match('/\A[\p{L}\s]+\z/u', $value)) {
                return [
                    'success' => false,
                    'message' => 'By må kun indeholde bogstaver.'
                ];
            }
        }

        // IBAN: Danish IBAN (DK + 16 digits)
        if ($field === 'iban') {

            $iban = strtoupper(str_replace(' ', '', $value));

            // Add DK automatically if user only enters 16 digits
            if (preg_match('/\A[0-9]{16}\z/', $iban)) {
                $iban = 'DK' . $iban;
            }

            // Check Danish IBAN format
            if (!preg_match('/\ADK[0-9]{16}\z/', $iban)) {
                return [
                    'success' => false,
                    'message' => 'IBAN skal bestå af DK efterfulgt af 16 cifre.'
                ];
            }

            // Validate IBAN checksum (MOD-97)
            $rearranged = substr($iban, 4) . '1314' . substr($iban, 2, 2);

            $remainder = 0;

            foreach (str_split($rearranged) as $digit) {
                $remainder = ($remainder * 10 + (int)$digit) % 97;
            }

            if ($remainder !== 1) {
                return [
                    'success' => false,
                    'message' => 'IBAN er ikke gyldigt.'
                ];
            }

            $value = $iban;
        }

        $fields[] = "$field = ?";
        $values[] = $value;
    }

    if (empty($fields)) {
        return [
            'success' => false,
            'message' => 'Ingen gyldige felter at opdatere.'
        ];
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
