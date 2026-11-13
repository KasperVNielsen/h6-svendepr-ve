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
            case 'update_profile_image':
                if (!isset($_FILES['profile_image'])) {
                    $message = ['success' => false, 'message' => 'Ingen fil modtaget.'];
                    break;
                }
                $message = updateProfileImage($id, $_FILES['profile_image']);
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


function updateProfileImage(int $id, array $file)   
{
    global $pdo;

    //Check if a file was actually uploaded
    if (!isset($file['tmp_name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'message' => 'Vælg et billede først.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload fejlede. Prøv igen.'];
    }

    //Check file type via MIME (not the extension - it can be spoofed)
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!in_array($mime, $allowed)) {
        return ['success' => false, 'message' => 'Kun JPG, PNG, WEBP eller GIF er tilladt.'];
    }

    //Check size (max 5 MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'Billedet må max være 5 MB.'];
    }

    //Safe file extension based on MIME
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    };

    //Clean the original filename (strip everything except letters/numbers)
    $original = pathinfo($file['name'], PATHINFO_FILENAME);
    $original = preg_replace('/[^A-Za-z0-9\-_]/', '-', $original);

    $uploadDir = __DIR__ . '/../frontend/profile-images/';

    //Create the folder if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    //Filename: {id}_{name} automatically overwrites the same name
    $filename = $id . '_' . $original . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'message' => 'Kunne ikke gemme billedet.'];
    }

    // 7. Fetch the old image path and delete the file from disk
    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $old = $stmt->fetchColumn();

    if ($old) {
        $oldPath = __DIR__ . '/../frontend/' . $old;
        if (file_exists($oldPath)) {
            unlink($oldPath);
        }
    }

    // 8. Only store the relative path in the database
    $dbPath = 'profile-images/' . $filename;
    $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
    $stmt->execute([$dbPath, $id]);

    return ['success' => true, 'message' => 'Profilbillede opdateret!'];
}