<?php
$pageTitle = 'Bil kassen';
$basePath = '';

require_once __DIR__ . '/backend/listing.php';

$cars = getListings();

$fuelTypes = $pdo->query("
    SELECT DISTINCT fuel_type
    FROM car_listings
    WHERE status = 'aktiv'
      AND is_active = 1
      AND fuel_type IS NOT NULL
      AND TRIM(fuel_type) <> ''
    ORDER BY fuel_type
")->fetchAll(PDO::FETCH_COLUMN);

require __DIR__ . '/frontend/templates/header.php';
?>

<main class="flex-1">
    <section class="bg-primary px-4 py-10 text-white sm:px-6 sm:py-14">
        <div class="mx-auto max-w-7xl">
            <h1 class="mt-2 max-w-2xl text-3xl font-bold sm:text-4xl">Find din næste bil</h1>
            <p class="mt-3 max-w-2xl text-white/70">Se et udvalg af brugte biler fra hele Danmark.</p>

            <div class="mt-7 grid gap-3 rounded-xl bg-white p-3 shadow-lg sm:grid-cols-[1fr_180px_auto]">
                <label class="sr-only" for="car-search">Søg efter bil</label>
                <input class="min-w-0 rounded-lg border border-primary/15 px-4 py-3 text-content outline-none focus:border-accent focus:ring-2 focus:ring-accent/20"
                       type="search" id="car-search" placeholder="Søg efter mærke eller model">

                <label class="sr-only" for="fuel-filter">Brændstof</label>
                <select class="rounded-lg border border-primary/15 px-4 py-3 text-content outline-none focus:border-accent focus:ring-2 focus:ring-accent/20"
                        id="fuel-filter">
                    <option value="">Alle drivmidler</option>
                    <?php foreach ($fuelTypes as $fuelType): ?>
                        <option value="<?= htmlspecialchars($fuelType) ?>"><?= htmlspecialchars($fuelType) ?></option>
                    <?php endforeach; ?>
                </select>

                <button class="rounded-lg bg-accent px-6 py-3 font-semibold text-white transition hover:bg-accent/80"
                        type="button">
                    Søg biler
                </button>
            </div>
        </div>
    </section>

    <section class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 sm:py-14">
        <div class="mb-7 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-accent">Udvalgte biler</p>
                <h2 class="mt-1 text-2xl font-bold text-content sm:text-3xl">Brugte biler til salg</h2>
            </div>
            <p class="text-sm text-content/60"><?= count($cars) ?> biler fundet</p>
        </div>

        <?php if (empty($cars)): ?>
            <div class="rounded-xl border border-dashed border-primary/20 bg-surface px-6 py-12 text-center">
                <p class="font-semibold text-content">Der er ingen biler til salg lige nu.</p>
                <p class="mt-1 text-sm text-content/60">Kom tilbage senere og se nye annoncer.</p>
            </div>
        <?php else: ?>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <?php foreach ($cars as $car): ?>
                <?php
                $listingId = (int) $car['id'];
                $carName = trim(($car['brand_name'] ?? '') . ' ' . ($car['model_name'] ?? ''));
                $mainImage = trim((string) ($car['main_image'] ?? ''));
                $hasMainImage = $mainImage !== '' && !str_contains($mainImage, '..');
                $imageUrl = $hasMainImage
                    ? 'images/' . implode('/', array_map('rawurlencode', explode('/', ltrim(str_replace('\\', '/', $mainImage), '/'))))
                    : null;
                ?>
                <article class="group flex h-full flex-col overflow-hidden rounded-xl border border-primary/10 bg-surface shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-lg">
                    <div class="relative aspect-[4/3] overflow-hidden bg-primary/5">
                        <a class="flex h-full items-center justify-center" href="bil?id=<?= $listingId ?>">
                            <?php if ($imageUrl !== null): ?>
                                <img class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
                                     src="<?= htmlspecialchars($imageUrl) ?>"
                                     alt="<?= htmlspecialchars($carName . ' ' . ($car['title'] ?? '')) ?>"
                                     loading="lazy">
                            <?php else: ?>
                                <span class="text-sm font-medium text-content/45">Intet billede</span>
                            <?php endif; ?>
                        </a>

                        <?php if (!empty($car['is_featured'])): ?>
                            <span class="absolute left-3 top-3 rounded-full bg-primary px-3 py-1 text-xs font-semibold text-white">
                                Fremhævet
                            </span>
                        <?php endif; ?>

                        <?php if ($userIsLoggedIn): ?>
                            <button class="absolute right-3 top-3 flex h-10 w-10 items-center justify-center rounded-full bg-white/95 text-content shadow-sm transition hover:text-danger"
                                    type="button" aria-label="Gem <?= htmlspecialchars($carName) ?> som favorit">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                          d="M21 8.25c0 5.25-9 10.5-9 10.5s-9-5.25-9-10.5a4.5 4.5 0 0 1 8.25-2.5L12 6.75l.75-1A4.5 4.5 0 0 1 21 8.25Z" />
                                </svg>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="flex flex-1 flex-col p-5">
                        <p class="text-2xl font-bold text-content"><?= number_format((float) $car['price'], 0, ',', '.') ?> kr.</p>
                        <h3 class="mt-3 text-lg font-bold text-content">
                            <a class="hover:text-accent" href="bil?id=<?= $listingId ?>">
                                <?= htmlspecialchars($carName) ?>
                            </a>
                        </h3>
                        <p class="mt-1 min-h-10 text-sm leading-5 text-content/65"><?= htmlspecialchars($car['title'] ?? '') ?></p>

                        <dl class="mt-5 grid grid-cols-3 gap-2 border-y border-primary/10 py-4 text-sm">
                            <div>
                                <dt class="text-xs text-content/50">Årgang</dt>
                                <dd class="mt-1 font-semibold text-content"><?= htmlspecialchars((string) ($car['model_year'] ?? '—')) ?></dd>
                            </div>
                            <div>
                                <dt class="text-xs text-content/50">Kilometer</dt>
                                <dd class="mt-1 font-semibold text-content">
                                    <?= isset($car['mileage']) ? number_format((int) $car['mileage'], 0, ',', '.') . ' km' : '—' ?>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-content/50">Drivmiddel</dt>
                                <dd class="mt-1 truncate font-semibold text-content" title="<?= htmlspecialchars($car['fuel_type'] ?? '') ?>">
                                    <?= htmlspecialchars($car['fuel_type'] ?? '—') ?>
                                </dd>
                            </div>
                        </dl>

                        <p class="mt-auto pt-4 text-sm capitalize text-content/60">
                            <?= htmlspecialchars($car['condition'] ?? '') ?>
                        </p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/frontend/templates/footer.php'; ?>
