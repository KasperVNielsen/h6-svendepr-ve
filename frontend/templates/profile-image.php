<?php

function saveProfileImage(int $userId, ?array $file): array
{
    global $pdo;

    if ($file === null || !isset($file['error'], $file['size'], $file['tmp_name'])) {
        return ['success' => false, 'message' => 'Vælg et billede, der skal uploades.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Billedet kunne ikke uploades. Prøv igen.'];
    }

    if ($file['size'] <= 0 || $file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'Billedet må højst fylde 5 MB.'];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Den uploadede fil er ikke gyldig.'];
    }

    $imageInfo = @getimagesize($file['tmp_name']);

    if ($imageInfo === false) {
        return ['success' => false, 'message' => 'Filen skal være et gyldigt billede.'];
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $mimeType = $imageInfo['mime'] ?? '';

    if (!isset($allowedTypes[$mimeType])) {
        return ['success' => false, 'message' => 'Billedet skal være JPG, PNG eller WebP.'];
    }

    $imageDirectory = dirname(__DIR__) . '/images/profile-images';

    if (!is_dir($imageDirectory) || !is_writable($imageDirectory)) {
        return ['success' => false, 'message' => 'Billedmappen er ikke klar til uploads.'];
    }

    try {
        $fileName = 'profile-' . $userId . '-' . bin2hex(random_bytes(8)) . '.' . $allowedTypes[$mimeType];

        $statement = $pdo->prepare('SELECT profile_image FROM users WHERE id = ?');
        $statement->execute([$userId]);
        $oldImage = $statement->fetchColumn();

        $newImagePath = $imageDirectory . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $newImagePath)) {
            return ['success' => false, 'message' => 'Billedet kunne ikke gemmes. Prøv igen.'];
        }

        $statement = $pdo->prepare('UPDATE users SET profile_image = ? WHERE id = ?');
        $statement->execute([$fileName, $userId]);

        if (!empty($oldImage)) {
            $oldImagePath = $imageDirectory . '/' . basename($oldImage);

            if (is_file($oldImagePath) && $oldImagePath !== $newImagePath) {
                unlink($oldImagePath);
            }
        }

        return ['success' => true, 'message' => 'Profilbilledet er opdateret.'];
    } catch (Throwable $exception) {
        if (isset($newImagePath) && is_file($newImagePath)) {
            unlink($newImagePath);
        }

        return ['success' => false, 'message' => 'Der opstod en fejl, mens billedet blev gemt.'];
    }
}
