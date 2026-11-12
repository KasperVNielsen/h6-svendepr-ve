<?php
$pageTitle = $pageTitle ?? 'Bil kassen';
$basePath = $basePath ?? '';

require_once dirname(__DIR__, 2) . '/backend/auth.php';

$userIsLoggedIn = isLoggedIn();
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
            --color-danger: #dc2626;
            --color-success: #15803d;
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

            <?php if ($userIsLoggedIn): ?>
                <div class="flex items-center gap-3">
                    <a class="font-semibold text-white/80 transition hover:text-white"
                       href="<?= $basePath ?>frontend/profile.php">
                        Min profil
                    </a>

                    <form method="post" action="<?= $basePath ?>frontend/login.php">
                        <input type="hidden" name="action" value="logout">
                        <button class="rounded-lg bg-accent px-4 py-2 font-semibold text-white transition hover:bg-accent/80"
                                type="submit">
                            Log ud
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <a class="rounded-lg bg-accent px-4 py-2 font-semibold text-white transition hover:bg-accent/80"
                   href="<?= $basePath ?>frontend/login.php">
                    Log ind
                </a>
            <?php endif; ?>
        </nav>
    </header>
