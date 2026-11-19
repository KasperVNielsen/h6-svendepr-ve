<?php
$pageTitle = 'BMW iX3 | Bil kassen';
$basePath = '../';
$listingId = max(1, (int) ($_GET['id'] ?? 1));

// Hardcodet test data.
$car = [
    'id' => $listingId,
    'brand' => 'BMW',
    'model' => 'iX3',
    'title' => 'Charged Plus M-Sport 5d',
    'price' => 338995,
    'condition' => 'brugt',
    'model_year' => 2023,
    'first_registration' => '4/2023',
    'mileage' => 99000,
    'fuel_type' => 'El',
    'engine_info' => 'Elektrisk',
    'horsepower' => 286,
    'torque' => 400,
    'acceleration' => '6,8 sek.',
    'top_speed' => '180 km/t',
    'gear_type' => 'Automatisk',
    'drive_wheels' => 'Baghjulstræk',
    'periodic_tax' => '920 kr. / år',
    'weight' => '2.260 kg',
    'max_towing_brake' => '750 kg',
    'doors' => 5,
    'color' => 'Hvid',
    'color_type' => 'Metallic',
    'body_type' => 'SUV',
    'description' => 'Flot og velholdt elektrisk SUV med M-Sport-pakke, høj komfort og masser af udstyr. Bilen står velplejet og er klar til levering. Kontakt forhandleren for fremvisning og prøvekørsel.',
    'images' => [
        'sedan-white.webp',
        'electric-blue.webp',
        'stationcar-silver.webp',
        'suv-red.webp',
    ],
    'equipment' => [
        'Adaptiv fartpilot',
        'Panoramasoltag',
        '360° kamera',
        'Parkeringssensor for og bag',
        'Head-up display',
        'Navigation',
        'Apple CarPlay og Android Auto',
        'El-sæder med memory',
        'Sædevarme',
        'Ratvarme',
        'Varmepumpe',
        'Elektrisk bagklap',
    ],
];

$seller = [
    'company_name' => 'Nordic Auto Aarhus',
    'address' => 'Bilvejen 24',
    'zip_code' => '8260',
    'city' => 'Viby J',
    'phone' => '86123456',
    'email' => 'salg@nordic-auto.dk',
    'website' => 'www.nordic-auto.dk',
    'description' => 'Autoriseret bilforhandler med fokus på nyere brugte biler og personlig rådgivning.',
    'is_verified' => true,
];

$formattedPhone = implode(' ', str_split($seller['phone'], 2));

require __DIR__ . '/templates/header.php';
?>

<main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 sm:py-10">
    <nav class="mb-6 flex flex-wrap items-center gap-2 text-sm text-content/60" aria-label="Brødkrummer">
        <a class="hover:text-accent" href="<?= $basePath ?>index.php">Biler</a>
        <span aria-hidden="true">/</span>
        <span><?= htmlspecialchars($car['brand']) ?></span>
        <span aria-hidden="true">/</span>
        <span class="text-content"><?= htmlspecialchars($car['model']) ?></span>
    </nav>

    <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div class="min-w-0 space-y-8">
            <section>
                <div class="overflow-hidden rounded-xl bg-primary/5">
                    <img class="aspect-[16/10] w-full object-cover"
                         src="images/cars/<?= htmlspecialchars($car['images'][0]) ?>"
                         alt="<?= htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' ' . $car['title']) ?>">
                </div>

                <div class="mt-3 grid grid-cols-3 gap-3">
                    <?php foreach (array_slice($car['images'], 1) as $imageIndex => $image): ?>
                        <div class="overflow-hidden rounded-lg bg-primary/5">
                            <img class="aspect-[4/3] h-full w-full object-cover"
                                 src="images/cars/<?= htmlspecialchars($image) ?>"
                                 alt="Ekstra billede <?= $imageIndex + 2 ?> af bilen"
                                 loading="lazy">
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

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
                        <dd class="mt-1 font-semibold text-content"><?= htmlspecialchars((string) $car['model_year']) ?></dd>
                    </div>
                    <div class="bg-page p-4">
                        <dt class="text-xs text-content/50">1. registrering</dt>
                        <dd class="mt-1 font-semibold text-content"><?= htmlspecialchars($car['first_registration']) ?></dd>
                    </div>
                    <div class="bg-page p-4">
                        <dt class="text-xs text-content/50">Kilometertal</dt>
                        <dd class="mt-1 font-semibold text-content"><?= number_format($car['mileage'], 0, ',', '.') ?> km</dd>
                    </div>
                    <div class="bg-page p-4">
                        <dt class="text-xs text-content/50">Drivmiddel</dt>
                        <dd class="mt-1 font-semibold text-content"><?= htmlspecialchars($car['fuel_type']) ?></dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
                <h2 class="text-2xl font-bold text-content">Detaljer</h2>
                <dl class="mt-6 grid gap-x-10 sm:grid-cols-2">
                    <?php
                    $details = [
                        'Motor' => $car['engine_info'],
                        'Ydelse' => $car['horsepower'] . ' hk / ' . $car['torque'] . ' Nm',
                        'Acceleration 0-100' => $car['acceleration'],
                        'Tophastighed' => $car['top_speed'],
                        'Gearkasse' => $car['gear_type'],
                        'Træk' => $car['drive_wheels'],
                        'Periodisk afgift' => $car['periodic_tax'],
                        'Egenvægt' => $car['weight'],
                        'Trækvægt med bremser' => $car['max_towing_brake'],
                        'Antal døre' => $car['doors'],
                        'Farve' => $car['color'] . ' ' . strtolower($car['color_type']),
                        'Karrosseri' => $car['body_type'],
                    ];
                    ?>
                    <?php foreach ($details as $label => $value): ?>
                        <div class="flex items-center justify-between gap-4 border-b border-primary/10 py-3">
                            <dt class="text-sm text-content/60"><?= htmlspecialchars($label) ?></dt>
                            <dd class="text-right text-sm font-semibold text-content"><?= htmlspecialchars((string) $value) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </section>

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

            <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
                <h2 class="text-2xl font-bold text-content">Beskrivelse</h2>
                <p class="mt-5 whitespace-pre-line leading-7 text-content/75"><?= htmlspecialchars($car['description']) ?></p>
            </section>
        </div>

        <aside class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm lg:sticky lg:top-6">
            <p class="text-sm font-semibold uppercase tracking-wider text-accent">Forhandler</p>
            <div class="mt-3 flex items-start gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-primary text-lg font-bold text-white">NA</div>
                <div>
                    <h2 class="text-xl font-bold text-content"><?= htmlspecialchars($seller['company_name']) ?></h2>
                    <?php if ($seller['is_verified']): ?>
                        <p class="mt-1 flex items-center gap-1.5 text-sm font-medium text-success">
                            <span class="flex h-4 w-4 items-center justify-center rounded-full bg-success text-[10px] text-white">✓</span>
                            Verificeret forhandler
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <p class="mt-5 text-sm leading-6 text-content/65"><?= htmlspecialchars($seller['description']) ?></p>

            <address class="mt-5 space-y-2 border-t border-primary/10 pt-5 text-sm not-italic text-content/70">
                <p><?= htmlspecialchars($seller['address']) ?></p>
                <p><?= htmlspecialchars($seller['zip_code'] . ' ' . $seller['city']) ?></p>
                <p><?= htmlspecialchars($formattedPhone) ?></p>
                <p class="break-all"><?= htmlspecialchars($seller['email']) ?></p>
            </address>

            <div class="mt-6 space-y-3">
                <a class="flex w-full items-center justify-center rounded-lg bg-accent px-4 py-3 font-semibold text-white transition hover:bg-accent/80"
                   href="tel:+45<?= htmlspecialchars($seller['phone']) ?>">
                    Ring til forhandler
                </a>
                <a class="flex w-full items-center justify-center rounded-lg border border-primary/15 px-4 py-3 font-semibold text-content transition hover:border-accent hover:text-accent"
                   href="mailto:<?= htmlspecialchars($seller['email']) ?>?subject=Forespørgsel på <?= rawurlencode($car['brand'] . ' ' . $car['model']) ?>">
                    Skriv til forhandler
                </a>
            </div>
        </aside>
    </div>
</main>

<?php require __DIR__ . '/templates/footer.php'; ?>
