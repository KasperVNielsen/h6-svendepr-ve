<?php
$pageTitle = 'Rediger bilannonce | Bil kassen';
$basePath = '../';

require_once __DIR__ . '/../backend/listing.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Validate the listing ID from the URL.
$listingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$listingId || $listingId < 1) {
    http_response_code(404);
    exit('Annoncen blev ikke fundet.');
}

// Load the listing and check ownership.
$seller = findSeller((int) $_SESSION['user_id']);
$statement = $pdo->prepare('SELECT * FROM car_listings WHERE id = ?');
$statement->execute([$listingId]);
$listing = $statement->fetch(PDO::FETCH_ASSOC);

$ownsAsUser = $listing && (int) $listing['seller_user_id'] === (int) $seller['user_id'];
$ownsAsCompany = $listing
    && $seller['company_id'] !== null
    && (int) $listing['seller_company_id'] === (int) $seller['company_id'];

if (!$listing || (!$ownsAsUser && !$ownsAsCompany)) {
    http_response_code(404);
    exit('Annoncen blev ikke fundet.');
}

// Load the options used in the form.
$brands = $pdo->query('SELECT id, name FROM brands WHERE is_active = 1 ORDER BY name')
    ->fetchAll(PDO::FETCH_ASSOC);
$models = $pdo->query('SELECT id, brand_id, name FROM models WHERE is_active = 1 ORDER BY name')
    ->fetchAll(PDO::FETCH_ASSOC);
$equipmentOptions = $pdo->query('SELECT id, name, category FROM equipment WHERE is_active = 1 ORDER BY category, name')
    ->fetchAll(PDO::FETCH_ASSOC);

// Load the selected equipment.
$statement = $pdo->prepare('SELECT equipment_id FROM listing_equipment WHERE listing_id = ?');
$statement->execute([$listingId]);
$savedEquipment = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));

// Load the current images.
$statement = $pdo->prepare('SELECT filename, is_main FROM car_images WHERE listing_id = ? ORDER BY sort_order, id');
$statement->execute([$listingId]);
$listingImages = $statement->fetchAll(PDO::FETCH_ASSOC);

// Protect edit and delete requests against CSRF.
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
$message = null;
$requestAction = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['action'] ?? '') : '';
$isEditPost = $requestAction === 'edit_listing';
$isDeletePost = $requestAction === 'delete_listing';

// Handle edit and delete requests.
if ($isEditPost || $isDeletePost) {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        $message = ['success' => false, 'text' => 'Formularen er udløbet. Prøv igen.'];
    } elseif ($isDeletePost) {
        $result = deleteListing($listingId);

        if ($result === 'Annoncen er slettet.') {
            header('Location: profile.php?deleted=1');
            exit;
        }

        $message = ['success' => false, 'text' => $result];
    } else {
        $result = editListing($listingId);
        $success = $result === 'Annonce opdateret!';

        if ($success) {
            header('Location: edit-listing.php?id=' . $listingId . '&updated=1');
            exit;
        }

        $message = ['success' => false, 'text' => $result];
    }
}

// Show a message after a successful update.
if (isset($_GET['updated'])) {
    $message = ['success' => true, 'text' => 'Annoncen er opdateret.'];
}

// Reuse submitted values after errors.
$formValue = static function (string $field) use ($isEditPost, $listing): string {
    $value = $isEditPost ? ($_POST[$field] ?? '') : ($listing[$field] ?? '');
    return htmlspecialchars((string) $value);
};
$isSelected = static function (string $field, string $value) use ($isEditPost, $listing): string {
    $current = $isEditPost ? ($_POST[$field] ?? '') : ($listing[$field] ?? '');
    return (string) $current === $value ? ' selected' : '';
};
$isChecked = static function (string $field) use ($isEditPost, $listing): string {
    $checked = $isEditPost ? isset($_POST[$field]) : !empty($listing[$field]);
    return $checked ? ' checked' : '';
};
$selectedEquipment = $isEditPost && is_array($_POST['equipment'] ?? null)
    ? array_map('strval', $_POST['equipment'])
    : $savedEquipment;

// Configure number fields as: field => [label, step].
$numberFields = [
    'horsepower' => ['Hestekræfter', '1'],
    'torque' => ['Moment (Nm)', '1'],
    'cylinders' => ['Cylindre', '1'],
    'fuel_consumption' => ['Forbrug (km/l)', '0.1'],
    'co2_emission' => ['CO₂ (g/km)', '1'],
    'periodic_tax' => ['Periodisk afgift', '0.01'],
    'acceleration' => ['0–100 km/t (sek.)', '0.1'],
    'top_speed' => ['Tophastighed (km/t)', '1'],
    'gear_count' => ['Antal gear', '1'],
    'weight' => ['Egenvægt (kg)', '1'],
    'payload' => ['Nyttelast (kg)', '1'],
    'towing_capacity' => ['Trækkapacitet (kg)', '1'],
    'max_towing_brake' => ['Trækvægt med bremser (kg)', '1'],
    'length' => ['Længde (mm)', '1'],
    'width' => ['Bredde (mm)', '1'],
    'height' => ['Højde (mm)', '1'],
    'trunk_size' => ['Bagagerum (liter)', '1'],
    'tank_capacity' => ['Tankkapacitet (liter)', '1'],
    'doors' => ['Antal døre', '1'],
    'airbags' => ['Antal airbags', '1'],
    'new_price' => ['Nypris', '0.01'],
];

require __DIR__ . '/templates/header.php';
?>

<main class="mx-auto w-full max-w-6xl flex-1 px-4 py-10 sm:px-6 sm:py-12">
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a class="text-sm font-semibold text-accent hover:underline" href="profile.php">&larr; Tilbage til min profil</a>
            <h1 class="mt-3 text-3xl font-bold text-content">Rediger bilannonce</h1>
        </div>
        <a class="inline-flex items-center justify-center rounded-lg border border-primary/15 px-4 py-2.5 font-semibold text-content hover:border-accent hover:text-accent"
           href="view-car.php?id=<?= (int) $listingId ?>">Se annonce</a>
    </div>

    <?php if ($message !== null): ?>
        <div class="mb-7 rounded-xl border px-5 py-4 <?= $message['success']
            ? 'border-success/20 bg-success/10 text-success'
            : 'border-danger/20 bg-danger/10 text-danger' ?>">
            <p class="font-semibold"><?= htmlspecialchars($message['text']) ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($listingImages)): ?>
        <section class="mb-8 rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
            <h2 class="text-xl font-bold text-content">Nuværende billeder</h2>
            <p class="mt-1 text-sm text-content/60">Billedredigering bliver tilføjet, når backendens uploadfunktion er klar.</p>
            <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <?php foreach ($listingImages as $image): ?>
                    <?php
                    $filename = str_replace('\\', '/', (string) $image['filename']);
                    $safeImage = !str_contains($filename, '..')
                        ? 'images/' . implode('/', array_map('rawurlencode', explode('/', ltrim($filename, '/'))))
                        : null;
                    ?>
                    <?php if ($safeImage !== null): ?>
                        <div class="relative overflow-hidden rounded-lg bg-primary/5">
                            <img class="aspect-[4/3] h-full w-full object-cover" src="<?= htmlspecialchars($safeImage) ?>" alt="Billede af bilen">
                            <?php if (!empty($image['is_main'])): ?>
                                <span class="absolute left-2 top-2 rounded-full bg-primary px-2.5 py-1 text-xs font-semibold text-white">Hovedbillede</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <form class="space-y-8" method="post">
        <input type="hidden" name="action" value="edit_listing">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
            <h2 class="text-xl font-bold text-content">Grundoplysninger</h2>
            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-2 block font-medium" for="brand_id">Mærke</label>
                    <select class="w-full rounded-lg border border-primary/20 px-4 py-3" id="brand_id" name="brand_id" required>
                        <option value="">Vælg mærke</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= (int) $brand['id'] ?>"<?= $isSelected('brand_id', (string) $brand['id']) ?>><?= htmlspecialchars($brand['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block font-medium" for="model_id">Model</label>
                    <select class="w-full rounded-lg border border-primary/20 px-4 py-3" id="model_id" name="model_id" required>
                        <option value="">Vælg model</option>
                        <?php foreach ($models as $model): ?>
                            <option value="<?= (int) $model['id'] ?>" data-brand-id="<?= (int) $model['brand_id'] ?>"<?= $isSelected('model_id', (string) $model['id']) ?>><?= htmlspecialchars($model['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-2 block font-medium" for="title">Overskrift</label>
                    <input class="w-full rounded-lg border border-primary/20 px-4 py-3" id="title" name="title" maxlength="255" value="<?= $formValue('title') ?>" required>
                </div>
                <div>
                    <label class="mb-2 block font-medium" for="price">Pris</label>
                    <input class="w-full rounded-lg border border-primary/20 px-4 py-3" type="number" id="price" name="price" min="1" step="0.01" value="<?= $formValue('price') ?>" required>
                </div>
                <div>
                    <label class="mb-2 block font-medium" for="price_type">Pristype</label>
                    <select class="w-full rounded-lg border border-primary/20 px-4 py-3" id="price_type" name="price_type">
                        <option value="fast"<?= $isSelected('price_type', 'fast') ?>>Fast pris</option>
                        <option value="forhandling"<?= $isSelected('price_type', 'forhandling') ?>>Til forhandling</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block font-medium" for="condition">Stand</label>
                    <select class="w-full rounded-lg border border-primary/20 px-4 py-3" id="condition" name="condition" required>
                        <?php foreach (['brugt' => 'Brugt', 'ny' => 'Ny', 'demobil' => 'Demobil'] as $value => $label): ?>
                            <option value="<?= $value ?>"<?= $isSelected('condition', $value) ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block font-medium" for="fuel_type">Drivmiddel</label>
                    <select class="w-full rounded-lg border border-primary/20 px-4 py-3" id="fuel_type" name="fuel_type" required>
                        <?php foreach (['Benzin', 'Diesel', 'El', 'Hybrid', 'Plug-in hybrid', 'Brint'] as $fuel): ?>
                            <option value="<?= $fuel ?>"<?= $isSelected('fuel_type', $fuel) ?>><?= $fuel ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block font-medium" for="model_year">Modelår</label>
                    <input class="w-full rounded-lg border border-primary/20 px-4 py-3" type="number" id="model_year" name="model_year" min="1900" max="<?= (int) date('Y') + 1 ?>" value="<?= $formValue('model_year') ?>" required>
                </div>
                <div>
                    <label class="mb-2 block font-medium" for="first_registration">Første registrering</label>
                    <input class="w-full rounded-lg border border-primary/20 px-4 py-3" id="first_registration" name="first_registration" value="<?= $formValue('first_registration') ?>">
                </div>
                <div>
                    <label class="mb-2 block font-medium" for="mileage">Kilometer</label>
                    <input class="w-full rounded-lg border border-primary/20 px-4 py-3" type="number" id="mileage" name="mileage" min="1" value="<?= $formValue('mileage') ?>" required>
                </div>
                <?php foreach (['engine_info' => 'Motorvariant', 'euro_norm' => 'Euronorm', 'color' => 'Farve', 'category' => 'Kategori', 'body_type' => 'Karrosseri'] as $field => $label): ?>
                    <div>
                        <label class="mb-2 block font-medium" for="<?= $field ?>"><?= $label ?></label>
                        <input class="w-full rounded-lg border border-primary/20 px-4 py-3" id="<?= $field ?>" name="<?= $field ?>" value="<?= $formValue($field) ?>">
                    </div>
                <?php endforeach; ?>
                <div>
                    <label class="mb-2 block font-medium" for="color_type">Farvetype</label>
                    <select class="w-full rounded-lg border border-primary/20 px-4 py-3" id="color_type" name="color_type">
                        <?php foreach (['Standard', 'Metallic', 'Mat'] as $type): ?>
                            <option value="<?= $type ?>"<?= $isSelected('color_type', $type) ?>><?= $type ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
            <h2 class="text-xl font-bold text-content">Tekniske oplysninger</h2>
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($numberFields as $field => [$label, $step]): ?>
                    <div>
                        <label class="mb-2 block font-medium" for="<?= $field ?>"><?= htmlspecialchars($label) ?></label>
                        <input class="w-full rounded-lg border border-primary/20 px-4 py-3" type="number" id="<?= $field ?>" name="<?= $field ?>" min="0" step="<?= $step ?>" value="<?= $formValue($field) ?>">
                    </div>
                <?php endforeach; ?>
                <div>
                    <label class="mb-2 block font-medium" for="gear_type">Gearkasse</label>
                    <select class="w-full rounded-lg border border-primary/20 px-4 py-3" id="gear_type" name="gear_type">
                        <option value="">Ikke angivet</option>
                        <?php foreach (['Manuel', 'Automatisk', 'CVT', 'Halvautomatisk'] as $gear): ?>
                            <option value="<?= $gear ?>"<?= $isSelected('gear_type', $gear) ?>><?= $gear ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block font-medium" for="drive_wheels">Træk</label>
                    <select class="w-full rounded-lg border border-primary/20 px-4 py-3" id="drive_wheels" name="drive_wheels">
                        <option value="">Ikke angivet</option>
                        <?php foreach (['Forhjulstræk', 'Baghjulstræk', '4x4'] as $drive): ?>
                            <option value="<?= $drive ?>"<?= $isSelected('drive_wheels', $drive) ?>><?= $drive ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end gap-6 pb-3">
                    <label class="flex items-center gap-2 font-medium"><input class="h-4 w-4" type="checkbox" name="abs_brakes" value="1"<?= $isChecked('abs_brakes') ?>> ABS</label>
                    <label class="flex items-center gap-2 font-medium"><input class="h-4 w-4" type="checkbox" name="esp" value="1"<?= $isChecked('esp') ?>> ESP</label>
                </div>
            </div>
        </section>

        <?php if (!empty($equipmentOptions)): ?>
            <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
                <h2 class="text-xl font-bold text-content">Udstyr</h2>
                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($equipmentOptions as $equipment): ?>
                        <label class="flex items-start gap-3 rounded-lg border border-primary/10 p-3 text-sm">
                            <input class="mt-0.5 h-4 w-4" type="checkbox" name="equipment[]" value="<?= (int) $equipment['id'] ?>"<?= in_array((string) $equipment['id'], $selectedEquipment, true) ? ' checked' : '' ?>>
                            <span><?= htmlspecialchars($equipment['name']) ?><?php if (!empty($equipment['category'])): ?><span class="block text-xs text-content/50"><?= htmlspecialchars($equipment['category']) ?></span><?php endif; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
            <h2 class="text-xl font-bold text-content">Beskrivelse</h2>
            <div class="mt-6 space-y-5">
                <div>
                    <label class="mb-2 block font-medium" for="description">Beskrivelse</label>
                    <textarea class="min-h-36 w-full rounded-lg border border-primary/20 px-4 py-3" id="description" name="description"><?= $formValue('description') ?></textarea>
                </div>
                <div>
                    <label class="mb-2 block font-medium" for="general_info">Generelle oplysninger</label>
                    <textarea class="min-h-28 w-full rounded-lg border border-primary/20 px-4 py-3" id="general_info" name="general_info"><?= $formValue('general_info') ?></textarea>
                </div>
            </div>
        </section>

        <button class="w-full rounded-lg bg-accent px-6 py-3.5 font-semibold text-white transition hover:bg-accent/80 sm:w-auto" type="submit">Gem ændringer</button>
    </form>

    <section class="mt-10 rounded-xl border border-danger/20 bg-danger/5 p-6 sm:p-8">
        <h2 class="text-xl font-bold text-danger">Slet annonce</h2>
        <p class="mt-2 text-sm leading-6 text-content/65">
            Annoncen fjernes fra de aktive bilannoncer. Handlingen kan ikke fortrydes fra denne side.
        </p>
        <form class="mt-5" method="post"
              onsubmit="return confirm('Er du sikker på, at du vil slette denne annonce?');">
            <input type="hidden" name="action" value="delete_listing">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <button class="rounded-lg bg-danger px-5 py-3 font-semibold text-white transition hover:bg-danger/85"
                    type="submit">
                Slet annonce
            </button>
        </form>
    </section>
</main>

<script>
    // Filter models by the selected brand.
    const brandSelect = document.getElementById('brand_id');
    const modelSelect = document.getElementById('model_id');
    const modelOptions = Array.from(modelSelect.options).slice(1);

    function filterModels(keepSelection = true) {
        const selectedModel = keepSelection ? modelSelect.value : '';
        modelOptions.forEach((option) => {
            option.hidden = brandSelect.value !== '' && option.dataset.brandId !== brandSelect.value;
        });
        modelSelect.value = keepSelection && !modelSelect.selectedOptions[0]?.hidden ? selectedModel : '';
    }

    brandSelect.addEventListener('change', () => filterModels(false));
    filterModels(true);
</script>

<?php require __DIR__ . '/templates/footer.php'; ?>
