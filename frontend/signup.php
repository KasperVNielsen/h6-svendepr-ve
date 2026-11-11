<?php
$pageTitle = 'Opret bruger | Bil kassen';
$basePath = '../';
$signupMessage = '';

require_once __DIR__ . '/../backend/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'signup') {

    $signupMessage = !empty($message) ? $message : createUser();
}

$signupSucceeded = $signupMessage === 'Bruger oprettet!';
$formValues = $signupSucceeded ? [] : $_POST;

require __DIR__ . '/templates/header.php';
?>

<main class="mx-auto flex w-full max-w-6xl flex-1 items-center justify-center px-6 py-16">
    <section class="w-full max-w-xl rounded-xl border border-primary/10 bg-surface p-8 shadow-sm">
        <h1 class="text-3xl font-bold text-content">Opret bruger</h1>
        <p class="mt-2 text-content/70">Udfyld oplysningerne for at oprette din bruger.</p>

        <?php if ($signupMessage !== ''): ?>
            <p class="mt-5 rounded-lg border px-4 py-3 text-sm <?= $signupSucceeded
                ? 'border-success/20 bg-success/10 text-success'
                : 'border-danger/20 bg-danger/10 text-danger' ?>">
                <?= htmlspecialchars($signupMessage) ?>
            </p>
        <?php endif; ?>

        <form class="mt-8 space-y-5" method="post">
            <input type="hidden" name="action" value="signup">

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-2 block font-medium text-content" for="first_name">Fornavn</label>
                    <input class="w-full rounded-lg border border-primary/20 px-4 py-3 outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                           type="text"
                           id="first_name"
                           name="first_name"
                           autocomplete="given-name"
                           value="<?= htmlspecialchars($formValues['first_name'] ?? '') ?>"
                           required>
                </div>

                <div>
                    <label class="mb-2 block font-medium text-content" for="last_name">Efternavn</label>
                    <input class="w-full rounded-lg border border-primary/20 px-4 py-3 outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                           type="text"
                           id="last_name"
                           name="last_name"
                           autocomplete="family-name"
                           value="<?= htmlspecialchars($formValues['last_name'] ?? '') ?>"
                           required>
                </div>
            </div>

            <div>
                <label class="mb-2 block font-medium text-content" for="city">By</label>
                <input class="w-full rounded-lg border border-primary/20 px-4 py-3 outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                       type="text"
                       id="city"
                       name="city"
                       autocomplete="address-level2"
                       value="<?= htmlspecialchars($formValues['city'] ?? '') ?>"
                       required>
            </div>

            <div>
                <label class="mb-2 block font-medium text-content" for="email">E-mail</label>
                <input class="w-full rounded-lg border border-primary/20 px-4 py-3 outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                       type="email"
                       id="email"
                       name="email"
                       autocomplete="email"
                       value="<?= htmlspecialchars($formValues['email'] ?? '') ?>"
                       placeholder="navn@eksempel.dk"
                       required>
            </div>

            <div>
                <label class="mb-2 block font-medium text-content" for="password">Adgangskode</label>
                <input class="w-full rounded-lg border border-primary/20 px-4 py-3 outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                       type="password"
                       id="password"
                       name="password"
                       autocomplete="new-password"
                       required>
            </div>

            <button class="w-full rounded-lg bg-accent px-4 py-3 font-semibold text-white transition hover:bg-accent/80"
                    type="submit">
                Opret bruger
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-content/70">
            Har du allerede en bruger?
            <a class="font-semibold text-accent hover:underline" href="<?= $basePath ?>frontend/login.php">
                Log ind
            </a>
        </p>
    </section>
</main>

<?php require __DIR__ . '/templates/footer.php'; ?>
