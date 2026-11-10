<?php
$pageTitle = $pageTitle ?? 'Bil kassen';
$basePath = $basePath ?? '';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle) ?></title>

    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style type="text/tailwindcss">
        @theme {
            --color-primary: #0f172a;
            --color-accent: #2563eb;
            --color-page: #f8fafc;
            --color-surface: #ffffff;
            --color-content: #1e293b;
        }
    </style>
</head>
<body class="flex min-h-screen flex-col bg-page text-content">
    <header class="border-b border-white/10 bg-primary">
        <nav class="mx-auto flex max-w-6xl items-center justify-between px-6 py-2">
            <a href="<?= $basePath ?>index.php" aria-label="Gå til forsiden">
                <img class="h-16 w-auto sm:h-20"
                     src="<?= $basePath ?>frontend/images/Logo%20-%20Bil%20kassen.png"
                     alt="Bil kassen">
            </a>

            <a class="rounded-lg bg-accent px-4 py-2 font-semibold text-white transition hover:bg-accent/80"
               href="<?= $basePath ?>frontend/login.php">
                Login
            </a>
        </nav>
    </header>
