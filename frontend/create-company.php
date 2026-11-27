<?php
$pageTitle = 'Opret firma | Bil kassen';
$basePath = '../';

// company.php handles POST requests when it is included. Temporarily expose this
// request as GET so this page can validate CSRF before calling createCompany().
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
require_once __DIR__ . '/../backend/company.php';
ob_end_clean();
$_SERVER['REQUEST_METHOD'] = $requestMethod;

if (!isLoggedIn()) {
    header('Location: ./login');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$statement = $pdo->prepare("
    SELECT c.id, c.company_name
    FROM companies c
    LEFT JOIN company_employees ce
      ON ce.company_id = c.id
     AND ce.user_id = ?
     AND ce.is_active = 1
    WHERE c.owner_user_id = ? OR ce.user_id = ?
    ORDER BY (c.owner_user_id = ?) DESC
    LIMIT 1
");
$statement->execute([$userId, $userId, $userId, $userId]);
$existingCompany = $statement->fetch(PDO::FETCH_ASSOC) ?: null;

$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
$message = null;

if ($requestMethod === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;

    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $message = 'Formularen er udløbet. Prøv igen.';
    } elseif ($existingCompany !== null) {
        $message = 'Du er allerede tilknyttet en virksomhed.';
    } else {
        // createCompany() starts the session itself, so close the active session first.
        session_write_close();
        $result = createCompany();

        if ($result === 'Firma oprettet!') {
            header('Location: ./profil?company_created=1');
            exit;
        }

        $message = $result;
    }
}

$old = static function (string $field): string {
    return htmlspecialchars(trim((string) ($_POST[$field] ?? '')));
};

require __DIR__ . '/templates/header.php';
?>

<main class="mx-auto w-full max-w-3xl flex-1 px-4 py-10 sm:px-6 sm:py-12">
    <div class="mb-8">
        <a class="text-sm font-semibold text-accent hover:underline" href="profil">&larr; Tilbage til min profil</a>
        <p class="mt-5 text-sm font-semibold uppercase tracking-wider text-accent">Firmaprofil</p>
        <h1 class="mt-1 text-3xl font-bold text-content">Opret firma</h1>
        <p class="mt-3 leading-7 text-content/65">Du kan oprette ét firma på din profil. Du bliver automatisk registreret som firmaets ejer.</p>
    </div>

    <?php if ($existingCompany !== null): ?>
        <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
            <h2 class="text-xl font-bold text-content">Du er allerede tilknyttet en virksomhed</h2>
            <p class="mt-2 text-content/65"><?= htmlspecialchars($existingCompany['company_name']) ?> er tilknyttet din profil.</p>
            <a class="mt-6 inline-flex rounded-lg bg-accent px-5 py-3 font-semibold text-white transition hover:bg-accent/80"
               href="min-virksomhed">Gå til virksomheden</a>
        </section>
    <?php else: ?>
        <?php if ($message !== null): ?>
            <div class="mb-7 rounded-xl border border-danger/20 bg-danger/10 px-5 py-4 text-danger">
                <p class="font-semibold"><?= htmlspecialchars($message) ?></p>
            </div>
        <?php endif; ?>

        <form class="space-y-8" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
                <h2 class="text-xl font-bold text-content">Firmaoplysninger</h2>
                <p class="mt-1 text-sm text-content/60">Felter markeret med * skal udfyldes.</p>

                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-2 block font-medium" for="company_name">Firmanavn *</label>
                        <input class="w-full rounded-lg border border-primary/20 px-4 py-3"
                               id="company_name" name="company_name" maxlength="255"
                               value="<?= $old('company_name') ?>" autocomplete="organization" required>
                    </div>
                    <div>
                        <label class="mb-2 block font-medium" for="cvr">CVR-nummer *</label>
                        <input class="w-full rounded-lg border border-primary/20 px-4 py-3"
                               id="cvr" name="cvr" maxlength="8" inputmode="numeric" pattern="[0-9]{8}"
                               value="<?= $old('cvr') ?>" aria-describedby="cvr_help" required>
                        <p class="mt-2 text-xs text-content/55" id="cvr_help">Indtast CVR-nummerets 8 cifre.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-2 block font-medium" for="address">Adresse *</label>
                        <input class="w-full rounded-lg border border-primary/20 px-4 py-3"
                               id="address" name="address" maxlength="255"
                               value="<?= $old('address') ?>" autocomplete="street-address" required>
                    </div>
                    <div>
                        <label class="mb-2 block font-medium" for="zip_code">Postnummer *</label>
                        <input class="w-full rounded-lg border border-primary/20 px-4 py-3"
                               id="zip_code" name="zip_code" maxlength="10" inputmode="numeric"
                               value="<?= $old('zip_code') ?>" autocomplete="postal-code" required>
                    </div>
                    <div>
                        <label class="mb-2 block font-medium" for="city">By *</label>
                        <input class="w-full rounded-lg border border-primary/20 px-4 py-3"
                               id="city" name="city" maxlength="100"
                               value="<?= $old('city') ?>" autocomplete="address-level2" required>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
                <h2 class="text-xl font-bold text-content">Kontakt og beskrivelse</h2>
                <p class="mt-1 text-sm text-content/60">Disse oplysninger er valgfrie og kan bruges som firmaets kontaktoplysninger.</p>

                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-2 block font-medium" for="email">E-mail</label>
                        <input class="w-full rounded-lg border border-primary/20 px-4 py-3"
                               type="email" id="email" name="email" maxlength="255"
                               value="<?= $old('email') ?>" autocomplete="email">
                    </div>
                    <div>
                        <label class="mb-2 block font-medium" for="phone">Telefon</label>
                        <input class="w-full rounded-lg border border-primary/20 px-4 py-3"
                               type="tel" id="phone" name="phone" maxlength="20"
                               value="<?= $old('phone') ?>" autocomplete="tel">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-2 block font-medium" for="website">Hjemmeside</label>
                        <input class="w-full rounded-lg border border-primary/20 px-4 py-3"
                               type="url" id="website" name="website" maxlength="255"
                               placeholder="https://eksempel.dk" value="<?= $old('website') ?>" autocomplete="url">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-2 block font-medium" for="description">Beskrivelse</label>
                        <textarea class="min-h-32 w-full rounded-lg border border-primary/20 px-4 py-3"
                                  id="description" name="description"><?= $old('description') ?></textarea>
                    </div>
                </div>
            </section>

            <button class="w-full rounded-lg bg-accent px-6 py-3.5 font-semibold text-white transition hover:bg-accent/80 sm:w-auto"
                    type="submit">Opret firma</button>
        </form>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/templates/footer.php'; ?>
