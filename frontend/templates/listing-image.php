<?php

//Return safe desktop and mobile URLs for a stored listing image.
function listingImageSources(string $storedPath): ?array
{
    $relativePath = ltrim(str_replace('\\', '/', trim($storedPath)), '/');

    if ($relativePath === '' || str_contains($relativePath, '..')) {
        return null;
    }

    $imageRoot = dirname(__DIR__) . '/images/';
    $desktopFile = $imageRoot . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (!is_file($desktopFile)) {
        return null;
    }

    $directory = pathinfo($relativePath, PATHINFO_DIRNAME);
    $mobileName = pathinfo($relativePath, PATHINFO_FILENAME) . '_mobile.webp';
    $mobilePath = ($directory === '.' ? '' : $directory . '/') . $mobileName;
    $mobileFile = $imageRoot . str_replace('/', DIRECTORY_SEPARATOR, $mobilePath);

    $encodePath = static fn(string $path): string =>
        'images/' . implode('/', array_map('rawurlencode', explode('/', $path)));

    return [
        'desktop' => $encodePath($relativePath),
        'mobile' => is_file($mobileFile) ? $encodePath($mobilePath) : null,
    ];
}
