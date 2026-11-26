<?php
$pageTitle = 'Min virksomhed | Bil kassen';
$basePath = '../';

// company.php handles POST by itself. Pause that router so this page can check CSRF first.
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

// Compatibility for the current deleteCompany(), which calls a missing helper.
if (!function_exists('requireLogin')) {
    function requireLogin(): void
    {
        if (!isLoggedIn()) {
            throw new RuntimeException('Du skal være logget ind.');
        }
    }
}

$userId = (int) $_SESSION['user_id'];

$statement = $pdo->prepare('SELECT first_name, last_name FROM users WHERE id = ? LIMIT 1');
$statement->execute([$userId]);
$currentUser = $statement->fetch(PDO::FETCH_ASSOC) ?: ['first_name' => '', 'last_name' => ''];
$currentUserName = trim($currentUser['first_name'] . ' ' . $currentUser['last_name']);

// Find a company the user owns or is employed by. Owners are preferred.
$statement = $pdo->prepare("
    SELECT c.*,
           ce.position,
           ce.can_create_listings,
           ce.can_edit_listings,
           ce.can_manage_transactions
    FROM companies c
    LEFT JOIN company_employees ce
      ON ce.company_id = c.id
     AND ce.user_id = ?
     AND ce.is_active = 1
    WHERE c.owner_user_id = ? OR ce.user_id = ?
    ORDER BY (c.owner_user_id = ?) DESC, c.is_active DESC
    LIMIT 1
");
$statement->execute([$userId, $userId, $userId, $userId]);
$company = $statement->fetch(PDO::FETCH_ASSOC) ?: null;

$isOwner = $company !== null && (int) $company['owner_user_id'] === $userId;
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;

if ($requestMethod === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;
    $action = (string) ($_POST['action'] ?? '');
    $result = '';
    $success = false;

    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $result = 'Formularen er udløbet. Prøv igen.';
    } elseif ($company === null) {
        $result = 'Der er ikke tilknyttet en virksomhed til din profil.';
    } elseif (!$isOwner) {
        $result = 'Kun virksomhedens ejer kan udføre denne handling.';
    } else {
        switch ($action) {
            case 'add_employee':
                $result = addEmployee();
                $success = $result === 'Medarbejder tilføjet!';
                break;

            case 'edit_employee':
                $result = editEmployee();
                $success = in_array($result, ['Medarbejder opdateret!', 'Ingen ændringer foretaget'], true);
                break;

            case 'edit_company':
                $result = editCompany();
                $success = in_array($result, ['Firmaoplysninger opdateret!', 'Ingen ændringer foretaget'], true);
                break;

            case 'delete_company':
                $result = deleteCompany();
                $success = str_starts_with($result, 'Firmaet er deaktiveret.');
                break;

            case 'reactivate_company':
                $result = reactivateCompany();
                $success = str_starts_with($result, 'Firma genaktiveret!');
                break;

            default:
                $result = 'Ukendt handling.';
        }
    }

    $_SESSION['company_flash'] = ['success' => $success, 'text' => $result];
    header('Location: ./min-virksomhed');
    exit;
}

$flash = $_SESSION['company_flash'] ?? null;
unset($_SESSION['company_flash']);

$employees = [];
$companyListings = [];
$companyTransactions = [];

if ($company !== null && !empty($company['is_active'])) {
    $statement = $pdo->prepare("
        SELECT ce.user_id, ce.position, ce.can_create_listings, ce.can_edit_listings,
               ce.can_manage_transactions, ce.is_active,
               u.first_name, u.last_name, u.email
        FROM company_employees ce
        JOIN users u ON u.id = ce.user_id
        WHERE ce.company_id = ? AND ce.is_active = 1
        ORDER BY (ce.user_id = ?) DESC, u.first_name, u.last_name
    ");
    $statement->execute([(int) $company['id'], (int) $company['owner_user_id']]);
    $employees = $statement->fetchAll(PDO::FETCH_ASSOC);

    $statement = $pdo->prepare("
        SELECT cl.id, cl.title, cl.price, cl.status, cl.main_image,
               b.name AS brand_name, m.name AS model_name
        FROM car_listings cl
        JOIN brands b ON b.id = cl.brand_id
        JOIN models m ON m.id = cl.model_id
        WHERE cl.seller_company_id = ? AND cl.is_active = 1
        ORDER BY cl.created_at DESC
    ");
    $statement->execute([(int) $company['id']]);
    $companyListings = $statement->fetchAll(PDO::FETCH_ASSOC);
}

$canCreate = $isOwner || !empty($company['can_create_listings']);
$canEdit = $isOwner || !empty($company['can_edit_listings']);
$canManage = $isOwner || !empty($company['can_manage_transactions']);

if ($company !== null && !empty($company['is_active']) && $canManage) {
    $statement = $pdo->prepare("
        SELECT id, type, amount, fee, method, status, description, created_at
        FROM transactions
        WHERE company_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT 50
    ");
    $statement->execute([(int) $company['id']]);
    $companyTransactions = $statement->fetchAll(PDO::FETCH_ASSOC);
}

require __DIR__ . '/templates/header.php';
?>

<main class="mx-auto w-full max-w-6xl flex-1 px-4 py-10 sm:px-6 sm:py-12">
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-accent">Virksomhed</p>
            <h1 class="mt-1 text-3xl font-bold text-content">Min virksomhed</h1>
            <?php if ($currentUserName !== ''): ?>
                <p class="mt-2 text-content/65">Velkommen, <?= htmlspecialchars($currentUserName) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($company !== null && !empty($company['is_active'])): ?>
            <a class="rounded-lg border border-primary/15 px-4 py-2.5 font-semibold hover:border-accent hover:text-accent"
               href="virksomhed?id=<?= (int) $company['id'] ?>">Se offentlig side</a>
        <?php endif; ?>
    </div>

    <?php if ($flash !== null): ?>
        <div class="mb-7 rounded-xl border px-5 py-4 <?= $flash['success'] ? 'border-success/20 bg-success/10 text-success' : 'border-danger/20 bg-danger/10 text-danger' ?>">
            <p class="font-semibold"><?= htmlspecialchars($flash['text']) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($company === null): ?>
        <section class="rounded-xl border border-dashed border-primary/20 bg-surface px-6 py-12 text-center">
            <h2 class="text-xl font-bold">Du er ikke tilknyttet en virksomhed</h2>
            <p class="mt-2 text-content/65">Du kan oprette din egen virksomhed eller blive tilføjet af en virksomhedsejer.</p>
            <a class="mt-6 inline-flex rounded-lg bg-accent px-5 py-3 font-semibold text-white" href="opret-firma">Opret virksomhed</a>
        </section>
    <?php elseif (empty($company['is_active'])): ?>
        <section class="rounded-xl border border-danger/20 bg-surface p-6 shadow-sm sm:p-8">
            <h2 class="text-xl font-bold text-content"><?= htmlspecialchars($company['company_name']) ?> er deaktiveret</h2>
            <p class="mt-2 text-content/65">Virksomheden kan genaktiveres i op til 30 dage efter deaktiveringen.</p>
            <?php if ($isOwner): ?>
                <form class="mt-6" method="post">
                    <input type="hidden" name="action" value="reactivate_company">
                    <input type="hidden" name="company_id" value="<?= (int) $company['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <button class="rounded-lg bg-accent px-5 py-3 font-semibold text-white" type="submit">Genaktiver virksomhed</button>
                </form>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold"><?= htmlspecialchars($company['company_name']) ?></h2>
                    <p class="mt-1 text-content/60">CVR <?= htmlspecialchars($company['cvr']) ?> · <?= htmlspecialchars(trim($company['zip_code'] . ' ' . $company['city'])) ?></p>
                    <p class="mt-2 text-sm font-semibold text-accent"><?= $isOwner ? 'Ejer' : htmlspecialchars((string) ($company['position'] ?: 'Medarbejder')) ?></p>
                </div>
                <?php if ($canCreate): ?>
                    <a class="rounded-lg bg-accent px-4 py-2.5 text-center font-semibold text-white" href="opret-annonce">Opret bilannonce</a>
                <?php endif; ?>
            </div>

            <?php if (!$isOwner): ?>
                <div class="mt-6 grid gap-3 border-t border-primary/10 pt-6 sm:grid-cols-3">
                    <p class="rounded-lg bg-primary/5 p-3 text-sm"><strong>Opret annoncer:</strong> <?= $canCreate ? 'Ja' : 'Nej' ?></p>
                    <p class="rounded-lg bg-primary/5 p-3 text-sm"><strong>Rediger annoncer:</strong> <?= $canEdit ? 'Ja' : 'Nej' ?></p>
                    <p class="rounded-lg bg-primary/5 p-3 text-sm"><strong>Transaktioner:</strong> <?= $canManage ? 'Ja' : 'Nej' ?></p>
                </div>
            <?php endif; ?>
        </section>

        <section class="mt-8 rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-bold">Virksomhedens bilannoncer</h2>
                <span class="text-sm text-content/60"><?= count($companyListings) ?> annoncer</span>
            </div>
            <?php if (empty($companyListings)): ?>
                <p class="mt-6 rounded-lg border border-dashed border-primary/20 p-8 text-center text-content/60">Virksomheden har ingen aktive annoncer.</p>
            <?php else: ?>
                <div class="mt-6 divide-y divide-primary/10 overflow-hidden rounded-xl border border-primary/10">
                    <?php foreach ($companyListings as $listing): ?>
                        <article class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-bold"><?= htmlspecialchars(trim($listing['brand_name'] . ' ' . $listing['model_name'])) ?></p>
                                <p class="mt-1 text-sm text-content/60"><?= htmlspecialchars($listing['title']) ?></p>
                                <p class="mt-1 text-sm font-semibold"><?= number_format((float) $listing['price'], 0, ',', '.') ?> kr.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <a class="rounded-lg border border-primary/15 px-3 py-2 text-sm font-semibold" href="bil?id=<?= (int) $listing['id'] ?>">Se</a>
                                <?php if ($canEdit): ?>
                                    <a class="rounded-lg border border-accent px-3 py-2 text-sm font-semibold text-accent" href="rediger-annonce?id=<?= (int) $listing['id'] ?>">Rediger</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($canManage): ?>
            <section class="mt-8 rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wider text-accent">Virksomhedskonto</p>
                        <h2 class="mt-1 text-xl font-bold">Saldo og transaktioner</h2>
                    </div>
                    <p class="text-3xl font-bold"><?= number_format((float) ($company['balance'] ?? 0), 2, ',', '.') ?> kr.</p>
                </div>

                <p class="mt-3 text-sm text-content/60">Virksomheden modtager salgsbeløbet efter kommission. Ind- og udbetaling understøttes endnu kun på private konti.</p>

                <?php if (empty($companyTransactions)): ?>
                    <p class="mt-6 rounded-lg border border-dashed border-primary/20 p-8 text-center text-content/60">Virksomheden har ingen transaktioner endnu.</p>
                <?php else: ?>
                    <div class="mt-6 divide-y divide-primary/10">
                        <?php foreach ($companyTransactions as $transaction): ?>
                            <?php
                            $isOutgoing = in_array($transaction['type'], ['køb', 'udbetaling'], true);
                            $displayAmount = (float) $transaction['amount'];
                            if ($transaction['type'] === 'salg') {
                                $displayAmount -= (float) $transaction['fee'];
                            }
                            ?>
                            <article class="flex flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-semibold capitalize"><?= htmlspecialchars($transaction['type']) ?></p>
                                    <p class="mt-1 text-sm text-content/60"><?= htmlspecialchars((string) $transaction['description']) ?></p>
                                    <p class="mt-1 text-xs text-content/45"><?= date('d.m.Y H:i', strtotime($transaction['created_at'])) ?></p>
                                </div>
                                <div class="sm:text-right">
                                    <p class="font-bold <?= $isOutgoing ? 'text-danger' : 'text-success' ?>">
                                        <?= $isOutgoing ? '−' : '+' ?><?= number_format($displayAmount, 2, ',', '.') ?> kr.
                                    </p>
                                    <?php if ((float) $transaction['fee'] > 0): ?>
                                        <p class="mt-1 text-xs text-content/50">Kommission: <?= number_format((float) $transaction['fee'], 2, ',', '.') ?> kr.</p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($isOwner): ?>
            <div class="mt-8 grid gap-8 lg:grid-cols-2">
                <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
                    <h2 class="text-xl font-bold">Tilføj medarbejder</h2>
                    <p class="mt-1 text-sm text-content/60">Brugeren skal allerede have en konto på Bil kassen.</p>
                    <form class="mt-6 space-y-4" method="post">
                        <input type="hidden" name="action" value="add_employee">
                        <input type="hidden" name="company_id" value="<?= (int) $company['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <div>
                            <label class="mb-2 block font-medium" for="employee_email">Medarbejderens e-mail</label>
                            <input class="w-full rounded-lg border border-primary/20 px-4 py-3" type="email" id="employee_email" name="employee_email" required>
                        </div>
                        <div>
                            <label class="mb-2 block font-medium" for="position">Stilling</label>
                            <input class="w-full rounded-lg border border-primary/20 px-4 py-3" id="position" name="position" value="Medarbejder" maxlength="100">
                        </div>
                        <div class="space-y-2 text-sm">
                            <label class="flex items-center gap-2"><input type="checkbox" name="can_create" value="1"> Må oprette bilannoncer</label>
                            <label class="flex items-center gap-2"><input type="checkbox" name="can_edit" value="1"> Må redigere bilannoncer</label>
                            <label class="flex items-center gap-2"><input type="checkbox" name="can_manage" value="1"> Må administrere transaktioner</label>
                        </div>
                        <button class="rounded-lg bg-accent px-5 py-3 font-semibold text-white" type="submit">Tilføj medarbejder</button>
                    </form>
                </section>

                <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
                    <h2 class="text-xl font-bold">Rediger virksomheden</h2>
                    <form class="mt-6 grid gap-4 sm:grid-cols-2" method="post">
                        <input type="hidden" name="action" value="edit_company">
                        <input type="hidden" name="company_id" value="<?= (int) $company['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input class="rounded-lg border border-primary/20 px-4 py-3 sm:col-span-2" name="company_name" value="<?= htmlspecialchars($company['company_name']) ?>" placeholder="Firmanavn" required>
                        <input class="rounded-lg border border-primary/20 px-4 py-3 sm:col-span-2" name="address" value="<?= htmlspecialchars($company['address']) ?>" placeholder="Adresse" required>
                        <input class="rounded-lg border border-primary/20 px-4 py-3" name="zip_code" value="<?= htmlspecialchars($company['zip_code']) ?>" placeholder="Postnummer" required>
                        <input class="rounded-lg border border-primary/20 px-4 py-3" name="city" value="<?= htmlspecialchars($company['city']) ?>" placeholder="By" required>
                        <input class="rounded-lg border border-primary/20 px-4 py-3" type="email" name="email" value="<?= htmlspecialchars((string) $company['email']) ?>" placeholder="E-mail">
                        <input class="rounded-lg border border-primary/20 px-4 py-3" name="phone" value="<?= htmlspecialchars((string) $company['phone']) ?>" placeholder="Telefon">
                        <input class="rounded-lg border border-primary/20 px-4 py-3 sm:col-span-2" name="website" value="<?= htmlspecialchars((string) $company['website']) ?>" placeholder="Hjemmeside">
                        <textarea class="min-h-28 rounded-lg border border-primary/20 px-4 py-3 sm:col-span-2" name="description" placeholder="Beskrivelse"><?= htmlspecialchars((string) $company['description']) ?></textarea>
                        <button class="rounded-lg bg-accent px-5 py-3 font-semibold text-white sm:col-span-2 sm:justify-self-start" type="submit">Gem virksomhedsoplysninger</button>
                    </form>
                </section>
            </div>

            <section class="mt-8 rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
                <h2 class="text-xl font-bold">Medarbejdere</h2>
                <div class="mt-6 space-y-4">
                    <?php foreach ($employees as $employee): ?>
                        <article class="rounded-xl border border-primary/10 p-4">
                            <div class="mb-4">
                                <p class="font-bold"><?= htmlspecialchars(trim($employee['first_name'] . ' ' . $employee['last_name'])) ?></p>
                                <p class="text-sm text-content/60"><?= htmlspecialchars($employee['email']) ?></p>
                            </div>
                            <?php if ((int) $employee['user_id'] === (int) $company['owner_user_id']): ?>
                                <p class="text-sm font-semibold text-accent">Ejer · Alle rettigheder</p>
                            <?php else: ?>
                                <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_auto_auto_auto_auto] lg:items-center" method="post">
                                    <input type="hidden" name="action" value="edit_employee">
                                    <input type="hidden" name="company_id" value="<?= (int) $company['id'] ?>">
                                    <input type="hidden" name="employee_id" value="<?= (int) $employee['user_id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input class="rounded-lg border border-primary/20 px-3 py-2" name="position" value="<?= htmlspecialchars($employee['position']) ?>" maxlength="100">
                                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="can_create" value="1"<?= !empty($employee['can_create_listings']) ? ' checked' : '' ?>> Opret</label>
                                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="can_edit" value="1"<?= !empty($employee['can_edit_listings']) ? ' checked' : '' ?>> Rediger</label>
                                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="can_manage" value="1"<?= !empty($employee['can_manage_transactions']) ? ' checked' : '' ?>> Transaktioner</label>
                                    <button class="rounded-lg border border-accent px-4 py-2 text-sm font-semibold text-accent" type="submit">Gem</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="mt-8 rounded-xl border border-danger/20 bg-danger/5 p-6 sm:p-8">
                <h2 class="text-xl font-bold text-danger">Deaktiver virksomhed</h2>
                <p class="mt-2 text-sm text-content/65">Backend gemmer virksomheden i 30 dage, hvor ejeren kan genaktivere den.</p>
                <form class="mt-5" method="post" onsubmit="return confirm('Er du sikker på, at virksomheden skal deaktiveres?');">
                    <input type="hidden" name="action" value="delete_company">
                    <input type="hidden" name="company_id" value="<?= (int) $company['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <button class="rounded-lg bg-danger px-5 py-3 font-semibold text-white" type="submit">Deaktiver virksomhed</button>
                </form>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/templates/footer.php'; ?>
