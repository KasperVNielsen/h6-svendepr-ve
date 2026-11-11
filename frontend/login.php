<?php
$pageTitle = 'Login | Bil kassen';
$basePath = '../';
$loginMessage = '';

require_once __DIR__ . '/../backend/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'logout') {
        logOut();
    }

    if ($action === 'login') {
        $loginMessage = login();
    }
}

if (isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

require __DIR__ . '/templates/header.php';
?>

<main class="mx-auto flex w-full max-w-6xl flex-1 items-center justify-center px-6 py-16">
    <section class="w-full max-w-md rounded-xl border border-primary/10 bg-surface p-8 shadow-sm">
        <h1 class="text-3xl font-bold text-content">Login</h1>
        <p class="mt-2 text-content/70">Log ind for at fortsætte til din bruger.</p>

        <?php if ($loginMessage !== ''): ?>
            <p class="mt-5 rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">
                <?= htmlspecialchars($loginMessage) ?>
            </p>
        <?php endif; ?>

        <form class="mt-8 space-y-5" method="post">
            <input type="hidden" name="action" value="login">

            <div>
                <label class="mb-2 block font-medium text-content" for="email">E-mail</label>
                <input class="w-full rounded-lg border border-primary/20 px-4 py-3 outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                       type="email"
                       id="email"
                       name="email"
                       autocomplete="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="navn@eksempel.dk"
                       required>
            </div>

            <div>
                <label class="mb-2 block font-medium text-content" for="password">Adgangskode</label>
                <input class="w-full rounded-lg border border-primary/20 px-4 py-3 outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                       type="password"
                       id="password"
                       name="password"
                       autocomplete="current-password"
                       required>
            </div>

            <button class="w-full rounded-lg bg-accent px-4 py-3 font-semibold text-white transition hover:bg-accent/80"
                    type="submit">
                Log ind
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-content/70">
            Har du ikke en bruger?
            <a class="font-semibold text-accent hover:underline" href="<?= $basePath ?>frontend/signup.php">
                Registrer dig
            </a>
        </p>
    </section>
</main>

<?php require __DIR__ . '/templates/footer.php'; ?>
