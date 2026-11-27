<?php
$basePath = '../';

require_once __DIR__ . '/../backend/listing.php';
require_once __DIR__ . '/../backend/transactions.php';
require_once __DIR__ . '/templates/listing-image.php';

$listingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$viewerIsLoggedIn = isLoggedIn();
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;
    $postedListingId = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
    $result = '';
    $success = false;

    if (!$viewerIsLoggedIn) {
        $result = 'Du skal være logget ind for at købe en bil.';
    } elseif (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $result = 'Formularen er udløbet. Prøv igen.';
    } elseif (($_POST['action'] ?? '') !== 'buy_car' || $postedListingId !== $listingId) {
        $result = 'Annoncen kunne ikke bekræftes.';
    } else {
        $result = buyCar();
        $success = str_starts_with($result, 'Bil ') && str_contains($result, 'Kommission');

        if ($success) {
            $result = 'Bilen er købt.';
        }
    }

    $_SESSION['purchase_flash'] = ['success' => $success, 'text' => $result];
    header('Location: ./bil?id=' . (int) $listingId);
    exit;
}

$purchaseFlash = $_SESSION['purchase_flash'] ?? null;
unset($_SESSION['purchase_flash']);

$car = $listingId && $listingId > 0 ? getCarInfo($listingId) : null;

if ($car === null) {
    http_response_code(404);
    $pageTitle = 'Bilannonce ikke fundet | Bil kassen';
    require __DIR__ . '/templates/header.php';
    ?>
    <main class="mx-auto w-full max-w-3xl flex-1 px-6 py-16 text-center">
        <h1 class="text-3xl font-bold text-content">Bilannoncen blev ikke fundet</h1>
        <p class="mt-3 text-content/65">Annoncen findes ikke eller er ikke længere aktiv.</p>
        <a class="mt-7 inline-flex rounded-lg bg-accent px-5 py-3 font-semibold text-white transition hover:bg-accent/80"
           href="./">Tilbage til bilerne</a>
    </main>
    <?php
    require __DIR__ . '/templates/footer.php';
    exit;
}

$car['brand'] = (string) ($car['brand_name'] ?? '');
$car['model'] = (string) ($car['model_name'] ?? '');

// Convert stored image paths to safe desktop and mobile URLs.
$imageUrls = [];
foreach ($car['images'] as $image) {
    $sources = listingImageSources((string) ($image['filename'] ?? ''));
    if ($sources !== null) {
        $imageUrls[] = $sources;
    }
}
$car['images'] = $imageUrls;
$car['equipment'] = array_values(array_filter(array_column($car['equipment'], 'name')));

// getCarInfo() returns seller IDs; load the matching public contact details.
if (!empty($car['seller_company_id'])) {
    $statement = $pdo->prepare("
        SELECT company_name, address, zip_code, city, phone, email, website, description, logo, is_verified
        FROM companies
        WHERE id = ? AND is_active = 1
        LIMIT 1
    ");
    $statement->execute([(int) $car['seller_company_id']]);
    $seller = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    $sellerType = 'Forhandler';
    $sellerCompanyUrl = 'virksomhed?id=' . (int) $car['seller_company_id'];
} else {
    $statement = $pdo->prepare("
        SELECT CONCAT(first_name, ' ', last_name) AS company_name,
               address, zip_code, city, phone, email,
               NULL AS website, NULL AS description, NULL AS logo, is_verified
        FROM users
        WHERE id = ? AND is_active = 1
        LIMIT 1
    ");
    $statement->execute([(int) $car['seller_user_id']]);
    $seller = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    $sellerType = 'Privat sælger';
    $sellerCompanyUrl = null;
}

$seller ??= [
    'company_name' => 'Ukendt sælger',
    'address' => '',
    'zip_code' => '',
    'city' => '',
    'phone' => '',
    'email' => '',
    'website' => '',
    'description' => '',
    'logo' => '',
    'is_verified' => 0,
];

$sellerName = trim((string) $seller['company_name']);
$nameParts = preg_split('/\s+/', $sellerName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
$sellerInitials = '';
foreach (array_slice($nameParts, 0, 2) as $namePart) {
    $sellerInitials .= mb_strtoupper(mb_substr($namePart, 0, 1));
}
$sellerInitials = $sellerInitials !== '' ? $sellerInitials : '?';

$sellerLogo = ltrim(str_replace('\\', '/', trim((string) ($seller['logo'] ?? ''))), '/');
$sellerLogoFile = __DIR__ . '/' . str_replace('/', DIRECTORY_SEPARATOR, $sellerLogo);
$sellerLogoUrl = $sellerLogo !== '' && !str_contains($sellerLogo, '..') && is_file($sellerLogoFile)
    ? implode('/', array_map('rawurlencode', explode('/', $sellerLogo)))
    : null;

$sellerPhone = trim((string) ($seller['phone'] ?? ''));
$phoneLink = preg_replace('/[^0-9+]/', '', $sellerPhone);
$formattedPhone = preg_match('/^[0-9]{8}$/', $sellerPhone)
    ? implode(' ', str_split($sellerPhone, 2))
    : $sellerPhone;

$pageTitle = trim($car['brand'] . ' ' . $car['model']) . ' | Bil kassen';

require __DIR__ . '/templates/header.php';
?>

<main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 sm:py-10">
    <?php if ($purchaseFlash !== null): ?>
        <div class="mb-7 rounded-xl border px-5 py-4 <?= $purchaseFlash['success'] ? 'border-success/20 bg-success/10 text-success' : 'border-danger/20 bg-danger/10 text-danger' ?>">
            <p class="font-semibold"><?= htmlspecialchars($purchaseFlash['text']) ?></p>
        </div>
    <?php endif; ?>

    <nav class="mb-6 flex flex-wrap items-center gap-2 text-sm text-content/60" aria-label="Brødkrummer">
        <a class="hover:text-accent" href="./">Biler</a>
        <span aria-hidden="true">/</span>
        <span><?= htmlspecialchars($car['brand']) ?></span>
        <span aria-hidden="true">/</span>
        <span class="text-content"><?= htmlspecialchars($car['model']) ?></span>
    </nav>

    <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div class="min-w-0 space-y-8">
            <?php if (!empty($car['images'])): ?>
                <section>
                    <div class="overflow-hidden rounded-xl bg-primary/5">
                        <picture>
                            <?php if ($car['images'][0]['mobile'] !== null): ?>
                                <source media="(max-width: 639px)" srcset="<?= htmlspecialchars($car['images'][0]['mobile']) ?>">
                            <?php endif; ?>
                            <img class="aspect-[16/10] w-full object-cover"
                                 src="<?= htmlspecialchars($car['images'][0]['desktop']) ?>"
                                 alt="<?= htmlspecialchars(trim($car['brand'] . ' ' . $car['model'] . ' ' . $car['title'])) ?>">
                        </picture>
                    </div>

                    <?php if (count($car['images']) > 1): ?>
                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <?php foreach (array_slice($car['images'], 1) as $imageIndex => $image): ?>
                                <div class="overflow-hidden rounded-lg bg-primary/5">
                                    <picture>
                                        <?php if ($image['mobile'] !== null): ?>
                                            <source media="(max-width: 639px)" srcset="<?= htmlspecialchars($image['mobile']) ?>">
                                        <?php endif; ?>
                                        <img class="aspect-[4/3] h-full w-full object-cover"
                                             src="<?= htmlspecialchars($image['desktop']) ?>"
                                             alt="Ekstra billede <?= $imageIndex + 2 ?> af bilen"
                                             loading="lazy">
                                    </picture>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wider text-accent">
                            <?= htmlspecialchars(ucfirst($car['condition'])) ?> bil
                        </p>
                        <h1 class="mt-2 text-3xl font-bold text-content">
                            <?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?>
                        </h1>
                        <p class="mt-1 text-lg text-content/65"><?= htmlspecialchars($car['title']) ?></p>
                    </div>

                    <?php if ($userIsLoggedIn): ?>
                        <button class="inline-flex items-center justify-center gap-2 rounded-lg border border-primary/15 px-4 py-2.5 font-semibold text-content transition hover:border-accent hover:text-accent"
                                type="button">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                      d="M21 8.25c0 5.25-9 10.5-9 10.5s-9-5.25-9-10.5a4.5 4.5 0 0 1 8.25-2.5L12 6.75l.75-1A4.5 4.5 0 0 1 21 8.25Z" />
                            </svg>
                            Gem favorit
                        </button>
                    <?php endif; ?>
                </div>

                <p class="mt-6 text-3xl font-bold text-content"><?= number_format($car['price'], 0, ',', '.') ?> kr.</p>

                <dl class="mt-7 grid grid-cols-2 gap-px overflow-hidden rounded-xl bg-primary/10 sm:grid-cols-4">
                    <div class="bg-page p-4">
                        <dt class="text-xs text-content/50">Modelår</dt>
                        <dd class="mt-1 font-semibold text-content"><?= htmlspecialchars((string) ($car['model_year'] ?: 'Ikke angivet')) ?></dd>
                    </div>
                    <div class="bg-page p-4">
                        <dt class="text-xs text-content/50">1. registrering</dt>
                        <dd class="mt-1 font-semibold text-content"><?= htmlspecialchars((string) ($car['first_registration'] ?: 'Ikke angivet')) ?></dd>
                    </div>
                    <div class="bg-page p-4">
                        <dt class="text-xs text-content/50">Kilometertal</dt>
                        <dd class="mt-1 font-semibold text-content"><?= $car['mileage'] !== null ? number_format((float) $car['mileage'], 0, ',', '.') . ' km' : 'Ikke angivet' ?></dd>
                    </div>
                    <div class="bg-page p-4">
                        <dt class="text-xs text-content/50">Drivmiddel</dt>
                        <dd class="mt-1 font-semibold text-content"><?= htmlspecialchars((string) ($car['fuel_type'] ?: 'Ikke angivet')) ?></dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
                <h2 class="text-2xl font-bold text-content">Detaljer</h2>
                <dl class="mt-6 grid gap-x-10 sm:grid-cols-2">
                    <?php
                    $details = [
                        'Motor' => $car['engine_info'] ?: null,
                        'Ydelse' => implode(' / ', array_filter([
                            $car['horsepower'] ? $car['horsepower'] . ' hk' : null,
                            $car['torque'] ? $car['torque'] . ' Nm' : null,
                        ])),
                        'Acceleration 0-100' => $car['acceleration'] ? $car['acceleration'] . ' sek.' : null,
                        'Tophastighed' => $car['top_speed'] ? $car['top_speed'] . ' km/t' : null,
                        'Gearkasse' => $car['gear_type'] ?: null,
                        'Træk' => $car['drive_wheels'] ?: null,
                        'Periodisk afgift' => $car['periodic_tax'] ? number_format((float) $car['periodic_tax'], 0, ',', '.') . ' kr. / år' : null,
                        'Egenvægt' => $car['weight'] ? number_format((float) $car['weight'], 0, ',', '.') . ' kg' : null,
                        'Trækvægt med bremser' => $car['max_towing_brake'] ? number_format((float) $car['max_towing_brake'], 0, ',', '.') . ' kg' : null,
                        'Antal døre' => $car['doors'] ?: null,
                        'Farve' => trim(($car['color'] ?? '') . ' ' . strtolower((string) ($car['color_type'] ?? ''))),
                        'Karrosseri' => $car['body_type'] ?: null,
                    ];
                    $details = array_filter($details, static fn($value): bool => $value !== null && $value !== '');
                    ?>
                    <?php if (!empty($details)): ?>
                        <?php foreach ($details as $label => $value): ?>
                            <div class="flex items-center justify-between gap-4 border-b border-primary/10 py-3">
                                <dt class="text-sm text-content/60"><?= htmlspecialchars($label) ?></dt>
                                <dd class="text-right text-sm font-semibold text-content"><?= htmlspecialchars((string) $value) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="py-4 text-sm text-content/60 sm:col-span-2">Der er ikke angivet tekniske detaljer.</p>
                    <?php endif; ?>
                </dl>
            </section>

            <?php if (!empty($car['equipment'])): ?>
                <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
                    <h2 class="text-2xl font-bold text-content">Udstyr</h2>
                    <ul class="mt-6 grid gap-3 sm:grid-cols-2">
                        <?php foreach ($car['equipment'] as $equipment): ?>
                            <li class="flex items-start gap-3 text-content/80">
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 4 4L19 6" />
                                </svg>
                                <?= htmlspecialchars($equipment) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
                <h2 class="text-2xl font-bold text-content">Beskrivelse</h2>
                <p class="mt-5 whitespace-pre-line leading-7 text-content/75"><?= htmlspecialchars((string) ($car['description'] ?: 'Der er ikke tilføjet en beskrivelse.')) ?></p>
            </section>
        </div>

        <aside class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm lg:sticky lg:top-6">
            <?php if (($car['status'] ?? '') === 'aktiv'): ?>
                <div class="mb-6 border-b border-primary/10 pb-6">
                    <p class="text-sm text-content/60">Pris</p>
                    <p class="mt-1 text-2xl font-bold"><?= number_format((float) $car['price'], 0, ',', '.') ?> kr.</p>

                    <?php if (!$viewerIsLoggedIn): ?>
                        <a class="mt-4 flex w-full items-center justify-center rounded-lg bg-accent px-4 py-3 font-semibold text-white"
                           href="login">Log ind for at købe bilen</a>
                    <?php elseif ((int) $car['seller_user_id'] === (int) $_SESSION['user_id']): ?>
                        <p class="mt-4 rounded-lg bg-primary/5 p-3 text-sm text-content/65">Du kan ikke købe din egen bil.</p>
                    <?php else: ?>
                        <form class="mt-4" method="post" onsubmit="return confirm('Vil du købe bilen for <?= number_format((float) $car['price'], 0, ',', '.') ?> kr.?');">
                            <input type="hidden" name="action" value="buy_car">
                            <input type="hidden" name="listing_id" value="<?= (int) $listingId ?>">
                            <input type="hidden" name="payment_method" value="intern">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <button class="w-full rounded-lg bg-accent px-4 py-3 font-semibold text-white transition hover:bg-accent/80" type="submit">Køb bilen</button>
                        </form>
                        <p class="mt-2 text-xs text-content/50">Beløbet trækkes fra din private saldo.</p>
                    <?php endif; ?>
                </div>
            <?php elseif (($car['status'] ?? '') === 'solgt'): ?>
                <p class="mb-6 rounded-lg bg-primary/5 p-4 text-center font-semibold text-content/70">Bilen er solgt</p>
            <?php endif; ?>

            <p class="text-sm font-semibold uppercase tracking-wider text-accent"><?= htmlspecialchars($sellerType) ?></p>
            <div class="mt-3 flex items-start gap-4">
                <?php if ($sellerLogoUrl !== null): ?>
                    <img class="h-14 w-14 shrink-0 rounded-lg border border-primary/10 bg-white object-contain p-1"
                         src="<?= htmlspecialchars($sellerLogoUrl) ?>"
                         alt="Logo for <?= htmlspecialchars($seller['company_name']) ?>">
                <?php else: ?>
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-primary text-lg font-bold text-white"><?= htmlspecialchars($sellerInitials) ?></div>
                <?php endif; ?>
                <div>
                    <h2 class="text-xl font-bold text-content">
                        <?php if ($sellerCompanyUrl !== null): ?>
                            <a class="hover:text-accent" href="<?= htmlspecialchars($sellerCompanyUrl) ?>"><?= htmlspecialchars($seller['company_name']) ?></a>
                        <?php else: ?>
                            <?= htmlspecialchars($seller['company_name']) ?>
                        <?php endif; ?>
                    </h2>
                    <?php if ($seller['is_verified']): ?>
                        <p class="mt-1 flex items-center gap-1.5 text-sm font-medium text-success">
                            <span class="flex h-4 w-4 items-center justify-center rounded-full bg-success text-[10px] text-white">&#10003;</span>
                            Verificeret sælger
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($seller['description'])): ?>
                <p class="mt-5 text-sm leading-6 text-content/65"><?= htmlspecialchars($seller['description']) ?></p>
            <?php endif; ?>

            <?php if (!empty($seller['address']) || !empty($seller['city']) || $formattedPhone !== '' || !empty($seller['email'])): ?>
                <address class="mt-5 space-y-2 border-t border-primary/10 pt-5 text-sm not-italic text-content/70">
                    <?php if (!empty($seller['address'])): ?>
                        <p><?= htmlspecialchars($seller['address']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($seller['zip_code']) || !empty($seller['city'])): ?>
                        <p><?= htmlspecialchars(trim($seller['zip_code'] . ' ' . $seller['city'])) ?></p>
                    <?php endif; ?>
                    <?php if ($formattedPhone !== ''): ?>
                        <p><?= htmlspecialchars($formattedPhone) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($seller['email'])): ?>
                        <p class="break-all"><?= htmlspecialchars($seller['email']) ?></p>
                    <?php endif; ?>
                </address>
            <?php endif; ?>

            <?php if ($phoneLink !== '' || !empty($seller['email'])): ?>
                <div class="mt-6 space-y-3">
                    <?php if ($phoneLink !== ''): ?>
                        <a class="flex w-full items-center justify-center rounded-lg bg-accent px-4 py-3 font-semibold text-white transition hover:bg-accent/80"
                           href="tel:<?= htmlspecialchars($phoneLink) ?>">Ring til sælger</a>
                    <?php endif; ?>
                    <?php if (!empty($seller['email'])): ?>
                        <a class="flex w-full items-center justify-center rounded-lg border border-primary/15 px-4 py-3 font-semibold text-content transition hover:border-accent hover:text-accent"
                           href="mailto:<?= htmlspecialchars($seller['email']) ?>?subject=<?= rawurlencode('Forespørgsel på ' . $car['brand'] . ' ' . $car['model']) ?>">
                            Skriv til sælger
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</main>

<?php require __DIR__ . '/templates/footer.php'; ?>
