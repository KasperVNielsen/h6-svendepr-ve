<?php
$basePath = '../';

require_once __DIR__ . '/../backend/company.php';
require_once __DIR__ . '/templates/listing-image.php';

$companyId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$company = null;

if ($companyId && $companyId > 0) {
    try {
        $company = getCompanyInfo($companyId);
    } catch (PDOException $exception) {
        // Temporary fallback while the local database is missing companies.deleted_at.
        if (($exception->errorInfo[1] ?? null) !== 1054) {
            throw $exception;
        }

        $statement = $pdo->prepare("
            SELECT id, company_name, cvr, website, address, zip_code, city,
                   phone, email, logo, description, is_verified, created_at
            FROM companies
            WHERE id = ? AND is_active = 1
            LIMIT 1
        ");
        $statement->execute([$companyId]);
        $company = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if ($company === null) {
    http_response_code(404);
    $pageTitle = 'Virksomhed ikke fundet | Bil kassen';
    require __DIR__ . '/templates/header.php';
    ?>
    <main class="mx-auto w-full max-w-3xl flex-1 px-6 py-16 text-center">
        <h1 class="text-3xl font-bold text-content">Virksomheden blev ikke fundet</h1>
        <p class="mt-3 text-content/65">Virksomheden findes ikke eller er ikke længere aktiv.</p>
        <a class="mt-7 inline-flex rounded-lg bg-accent px-5 py-3 font-semibold text-white" href="./">Tilbage til bilerne</a>
    </main>
    <?php
    require __DIR__ . '/templates/footer.php';
    exit;
}

// getCompanyInfo() does not return listings yet, so load them separately.
$statement = $pdo->prepare("
    SELECT cl.id, cl.title, cl.price, cl.model_year, cl.mileage, cl.fuel_type, cl.main_image,
           b.name AS brand_name, m.name AS model_name
    FROM car_listings cl
    JOIN brands b ON b.id = cl.brand_id
    JOIN models m ON m.id = cl.model_id
    WHERE cl.seller_company_id = ?
      AND cl.status = 'aktiv'
      AND cl.is_active = 1
    ORDER BY cl.created_at DESC
");
$statement->execute([$companyId]);
$companyListings = $statement->fetchAll(PDO::FETCH_ASSOC);

$companyName = trim((string) $company['company_name']);
$nameParts = preg_split('/\s+/', $companyName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
$initials = '';
foreach (array_slice($nameParts, 0, 2) as $namePart) {
    $initials .= mb_strtoupper(mb_substr($namePart, 0, 1));
}
$initials = $initials !== '' ? $initials : '?';

$companyLogo = ltrim(str_replace('\\', '/', trim((string) ($company['logo'] ?? ''))), '/');
$companyLogoFile = __DIR__ . '/' . str_replace('/', DIRECTORY_SEPARATOR, $companyLogo);
$companyLogoUrl = $companyLogo !== '' && !str_contains($companyLogo, '..') && is_file($companyLogoFile)
    ? implode('/', array_map('rawurlencode', explode('/', $companyLogo)))
    : null;

$phone = trim((string) ($company['phone'] ?? ''));
$phoneLink = preg_replace('/[^0-9+]/', '', $phone);
$formattedPhone = preg_match('/^[0-9]{8}$/', $phone) ? implode(' ', str_split($phone, 2)) : $phone;

$website = trim((string) ($company['website'] ?? ''));
$websiteUrl = $website !== '' && !preg_match('~^https?://~i', $website) ? 'https://' . $website : $website;
$websiteUrl = filter_var($websiteUrl, FILTER_VALIDATE_URL) ? $websiteUrl : '';

$pageTitle = $companyName . ' | Bil kassen';
require __DIR__ . '/templates/header.php';
?>

<main class="mx-auto w-full max-w-7xl flex-1 px-4 py-10 sm:px-6 sm:py-12">
    <a class="text-sm font-semibold text-accent hover:underline" href="./">&larr; Tilbage til bilerne</a>

    <section class="mt-6 overflow-hidden rounded-xl border border-primary/10 bg-surface shadow-sm">
        <div class="bg-primary px-6 py-8 sm:px-8">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                <?php if ($companyLogoUrl !== null): ?>
                    <img class="h-20 w-20 shrink-0 rounded-xl bg-white object-contain p-1"
                         src="<?= htmlspecialchars($companyLogoUrl) ?>"
                         alt="Logo for <?= htmlspecialchars($companyName) ?>">
                <?php else: ?>
                    <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-xl bg-white/10 text-2xl font-bold text-white">
                        <?= htmlspecialchars($initials) ?>
                    </div>
                <?php endif; ?>
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-white/60">Virksomhed</p>
                    <h1 class="mt-1 text-3xl font-bold text-white"><?= htmlspecialchars($companyName) ?></h1>
                    <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-white/70">
                        <span>CVR <?= htmlspecialchars($company['cvr']) ?></span>
                        <?php if (!empty($company['is_verified'])): ?>
                            <span class="rounded-full bg-success px-3 py-1 font-semibold text-white">Verificeret</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-8 p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div>
                <h2 class="text-xl font-bold text-content">Om virksomheden</h2>
                <p class="mt-4 whitespace-pre-line leading-7 text-content/70"><?= htmlspecialchars((string) ($company['description'] ?: 'Virksomheden har ikke tilføjet en beskrivelse endnu.')) ?></p>
            </div>

            <address class="space-y-2 text-sm not-italic text-content/70">
                <p class="font-semibold text-content"><?= htmlspecialchars($company['address']) ?></p>
                <p><?= htmlspecialchars(trim($company['zip_code'] . ' ' . $company['city'])) ?></p>
                <?php if ($formattedPhone !== ''): ?><p><?= htmlspecialchars($formattedPhone) ?></p><?php endif; ?>
                <?php if (!empty($company['email'])): ?><p class="break-all"><?= htmlspecialchars($company['email']) ?></p><?php endif; ?>
                <?php if ($websiteUrl !== ''): ?>
                    <a class="inline-block break-all font-semibold text-accent hover:underline" href="<?= htmlspecialchars($websiteUrl) ?>" target="_blank" rel="noopener noreferrer">
                        <?= htmlspecialchars($website) ?>
                    </a>
                <?php endif; ?>
            </address>
        </div>
    </section>

    <section class="mt-10">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-accent">Biler til salg</p>
                <h2 class="mt-1 text-2xl font-bold text-content"><?= htmlspecialchars($companyName) ?>s annoncer</h2>
            </div>
            <p class="text-sm text-content/60"><?= count($companyListings) ?> biler</p>
        </div>

        <?php if (empty($companyListings)): ?>
            <div class="mt-6 rounded-xl border border-dashed border-primary/20 bg-surface px-6 py-12 text-center text-content/60">
                Virksomheden har ingen aktive bilannoncer lige nu.
            </div>
        <?php else: ?>
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <?php foreach ($companyListings as $listing): ?>
                    <?php
                    $carName = trim($listing['brand_name'] . ' ' . $listing['model_name']);
                    $imageSources = listingImageSources((string) ($listing['main_image'] ?? ''));
                    ?>
                    <article class="overflow-hidden rounded-xl border border-primary/10 bg-surface shadow-sm">
                        <a class="flex aspect-[4/3] items-center justify-center overflow-hidden bg-primary/5" href="bil?id=<?= (int) $listing['id'] ?>">
                            <?php if ($imageSources !== null): ?>
                                <picture class="block h-full w-full">
                                    <?php if ($imageSources['mobile'] !== null): ?>
                                        <source media="(max-width: 639px)" srcset="<?= htmlspecialchars($imageSources['mobile']) ?>">
                                    <?php endif; ?>
                                    <img class="h-full w-full object-cover"
                                         src="<?= htmlspecialchars($imageSources['desktop']) ?>"
                                         alt="<?= htmlspecialchars($carName) ?>"
                                         loading="lazy">
                                </picture>
                            <?php else: ?>
                                <span class="text-sm text-content/45">Intet billede</span>
                            <?php endif; ?>
                        </a>
                        <div class="p-5">
                            <p class="text-xl font-bold"><?= number_format((float) $listing['price'], 0, ',', '.') ?> kr.</p>
                            <h3 class="mt-2 font-bold"><a class="hover:text-accent" href="bil?id=<?= (int) $listing['id'] ?>"><?= htmlspecialchars($carName) ?></a></h3>
                            <p class="mt-1 text-sm text-content/60"><?= htmlspecialchars($listing['title']) ?></p>
                            <p class="mt-4 text-xs text-content/50">
                                <?= htmlspecialchars((string) ($listing['model_year'] ?: '—')) ?> ·
                                <?= $listing['mileage'] !== null ? number_format((int) $listing['mileage'], 0, ',', '.') . ' km' : '—' ?> ·
                                <?= htmlspecialchars((string) ($listing['fuel_type'] ?: '—')) ?>
                            </p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/templates/footer.php'; ?>
