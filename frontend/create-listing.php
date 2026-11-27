<?php
$pageTitle = 'Opret bilannonce | Bil kassen';
$basePath = '../';

require_once __DIR__ . '/../backend/listing.php';

if (!isLoggedIn()) {
    header('Location: ./login');
    exit;
}

$equipmentOptions = $pdo->query("SELECT id, name, category FROM equipment WHERE is_active = 1 ORDER BY category, name")
    ->fetchAll(PDO::FETCH_ASSOC);

$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_listing') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        $message = ['success' => false, 'text' => 'Formularen er udløbet. Prøv igen.'];
    } elseif (
        ($_POST['vehicle_lookup_complete'] ?? '') !== '1'
        || empty($_POST['brand_name'])
        || empty($_POST['model_name'])
    ) {
        $message = ['success' => false, 'text' => 'Hent først bilen via nummerplade eller stelnummer.'];
    } else {
        $result = createListing();
        $success = str_starts_with($result, 'Annonce oprettet!');

        if ($success && preg_match('/ID:\s*(\d+)/', $result, $matches)) {
            header('Location: ./opret-annonce?created=' . (int) $matches[1]);
            exit;
        }

        $message = ['success' => $success, 'text' => $result];
    }
}

if (isset($_GET['created']) && ctype_digit((string) $_GET['created'])) {
    $listingId = (int) $_GET['created'];
    $message = [
        'success' => true,
        'text' => 'Din bilannonce er oprettet.',
    ];
}

$old = static fn(string $field, string $default = ''): string =>
    htmlspecialchars((string) ($_POST[$field] ?? $default));
$selected = static fn(string $field, string $value): string =>
    isset($_POST[$field]) && (string) $_POST[$field] === $value ? ' selected' : '';
$postedEquipment = is_array($_POST['equipment'] ?? null)
    ? array_map('strval', $_POST['equipment'])
    : [];

require __DIR__ . '/templates/header.php';
?>

<main class="mx-auto w-full max-w-6xl flex-1 px-4 py-10 sm:px-6 sm:py-12">
    <div class="mb-8">
        <a class="text-sm font-semibold text-accent hover:underline" href="./">
            &larr; Tilbage til bilerne
        </a>
        <h1 class="mt-3 text-3xl font-bold text-content">Opret bilannonce</h1>
        <p class="mt-2 text-content/65">Slå bilen op via nummerplade eller stelnummer, kontrollér oplysningerne og udfyld resten.</p>
    </div>

    <?php if ($message !== null): ?>
        <div class="mb-7 rounded-xl border px-5 py-4 <?= $message['success']
            ? 'border-success/20 bg-success/10 text-success'
            : 'border-danger/20 bg-danger/10 text-danger' ?>">
            <p class="font-semibold"><?= htmlspecialchars($message['text']) ?></p>
            <?php if ($message['success'] && isset($listingId)): ?>
                <a class="mt-2 inline-block text-sm font-semibold underline" href="./">
                    Se bilen på forsiden
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <section class="mb-8 rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
        <h2 class="text-xl font-bold text-content">Hent biloplysninger</h2>
        <p class="mt-1 text-sm text-content/60">Skriv en nummerplade som AB12345 eller et stelnummer på 17 tegn.</p>

        <div class="mt-5 flex flex-col gap-3 sm:flex-row">
            <div class="flex-1">
                <label class="sr-only" for="vehicle_lookup">Nummerplade eller stelnummer</label>
                <input class="w-full rounded-lg border border-primary/20 px-4 py-3 uppercase outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                       type="text" id="vehicle_lookup" maxlength="17" autocomplete="off"
                       placeholder="AB12345 eller W0L0SDL68C4173363">
            </div>
            <button class="rounded-lg bg-primary px-5 py-3 font-semibold text-white transition hover:bg-primary/85 disabled:cursor-wait disabled:opacity-60"
                    type="button" id="lookup_vehicle">
                Hent biloplysninger
            </button>
        </div>
        <p class="mt-3 hidden text-sm" id="lookup_message" role="status"></p>
    </section>

    <form class="space-y-8" method="post" enctype="multipart/form-data" id="listing_form">
        <input type="hidden" name="action" value="create_listing">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="brand_id" id="brand_id" value="<?= $old('brand_id') ?>">
        <input type="hidden" name="model_id" id="model_id" value="<?= $old('model_id') ?>">
        <input type="hidden" name="vehicle_lookup_complete" id="vehicle_lookup_complete" value="<?= $old('vehicle_lookup_complete') ?>">
        <input type="hidden" name="vehicle_lookup_type" id="vehicle_lookup_type" value="<?= $old('vehicle_lookup_type') ?>">
        <input type="hidden" name="vehicle_lookup_value" id="vehicle_lookup_value" value="<?= $old('vehicle_lookup_value') ?>">

        <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
            <h2 class="text-xl font-bold text-content">Grundoplysninger</h2>
            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-2 block font-medium text-content" for="brand_name">Mærke</label>
                    <input class="w-full cursor-not-allowed rounded-lg border border-primary/15 bg-primary/5 px-4 py-3 text-content/70"
                           type="text" id="brand_name" name="brand_name" value="<?= $old('brand_name') ?>"
                           placeholder="Hentes fra biloplysninger" readonly required>
                </div>
                <div>
                    <label class="mb-2 block font-medium text-content" for="model_name">Model</label>
                    <input class="w-full cursor-not-allowed rounded-lg border border-primary/15 bg-primary/5 px-4 py-3 text-content/70"
                           type="text" id="model_name" name="model_name" value="<?= $old('model_name') ?>"
                           placeholder="Hentes fra biloplysninger" readonly required>
                </div>
                <p class="text-sm text-content/55 sm:col-span-2">Mærke og model hentes fra biloplysninger og kan ikke ændres manuelt.</p>
                <div class="sm:col-span-2">
                    <label class="mb-2 block font-medium text-content" for="title">Overskrift</label>
                    <input class="w-full rounded-lg border border-primary/20 px-4 py-3" type="text" id="title" name="title"
                           maxlength="255" value="<?= $old('title') ?>" required>
                </div>
                <div>
                    <label class="mb-2 block font-medium text-content" for="price">Pris</label>
                    <input class="w-full rounded-lg border border-primary/20 px-4 py-3" type="number" id="price" name="price"
                           min="1" step="0.01" value="<?= $old('price') ?>" required>
                </div>
                <div>
                    <label class="mb-2 block font-medium text-content" for="price_type">Pristype</label>
                    <select class="w-full rounded-lg border border-primary/20 px-4 py-3" id="price_type" name="price_type">
                        <option value="fast"<?= $selected('price_type', 'fast') ?>>Fast pris</option>
                        <option value="forhandling"<?= $selected('price_type', 'forhandling') ?>>Til forhandling</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block font-medium text-content" for="condition">Stand</label>
                    <select class="w-full rounded-lg border border-primary/20 px-4 py-3" id="condition" name="condition" required>
                        <option value="brugt"<?= $selected('condition', 'brugt') ?>>Brugt</option>
                        <option value="ny"<?= $selected('condition', 'ny') ?>>Ny</option>
                        <option value="demobil"<?= $selected('condition', 'demobil') ?>>Demobil</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block font-medium text-content" for="fuel_type">Drivmiddel</label>
                    <select class="w-full rounded-lg border border-primary/20 px-4 py-3" id="fuel_type" name="fuel_type" required>
                        <option value="">Vælg drivmiddel</option>
                        <?php foreach (['Benzin', 'Diesel', 'El', 'Hybrid', 'Plug-in hybrid', 'Brint'] as $fuel): ?>
                            <option value="<?= htmlspecialchars($fuel) ?>"<?= $selected('fuel_type', $fuel) ?>><?= htmlspecialchars($fuel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block font-medium text-content" for="model_year">Modelår</label>
                    <input class="w-full rounded-lg border border-primary/20 px-4 py-3" type="number" id="model_year" name="model_year"
                           min="1900" max="<?= (int) date('Y') + 1 ?>" value="<?= $old('model_year') ?>" required>
                </div>
                <div>
                    <label class="mb-2 block font-medium text-content" for="first_registration">Første registrering</label>
                    <input class="w-full rounded-lg border border-primary/20 px-4 py-3" type="text" id="first_registration"
                           name="first_registration" placeholder="4/2023" value="<?= $old('first_registration') ?>">
                </div>
                <div>
                    <label class="mb-2 block font-medium text-content" for="mileage">Kilometer</label>
                    <input class="w-full rounded-lg border border-primary/20 px-4 py-3" type="number" id="mileage" name="mileage"
                           min="1" value="<?= $old('mileage') ?>" required>
                </div>
                <div>
                    <label class="mb-2 block font-medium text-content" for="body_type">Karrosseri</label>
                    <input class="w-full rounded-lg border border-primary/20 px-4 py-3" type="text" id="body_type" name="body_type"
                           value="<?= $old('body_type') ?>">
                </div>
                <div>
                    <label class="mb-2 block font-medium text-content" for="category">Kategori</label>
                    <input class="w-full rounded-lg border border-primary/20 px-4 py-3" type="text" id="category" name="category"
                           value="<?= $old('category') ?>">
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
            <h2 class="text-xl font-bold text-content">Motor og køreegenskaber</h2>
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <?php
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
                ];
                ?>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="mb-2 block font-medium text-content" for="engine_info">Motorvariant</label>
                    <input class="w-full rounded-lg border border-primary/20 px-4 py-3" type="text" id="engine_info" name="engine_info"
                           value="<?= $old('engine_info') ?>">
                </div>
                <?php foreach ($numberFields as $field => [$label, $step]): ?>
                    <div>
                        <label class="mb-2 block font-medium text-content" for="<?= $field ?>"><?= htmlspecialchars($label) ?></label>
                        <input class="w-full rounded-lg border border-primary/20 px-4 py-3" type="number" id="<?= $field ?>"
                               name="<?= $field ?>" min="0" step="<?= $step ?>" value="<?= $old($field) ?>">
                    </div>
                <?php endforeach; ?>
                <div>
                    <label class="mb-2 block font-medium text-content" for="euro_norm">Euronorm</label>
                    <input class="w-full rounded-lg border border-primary/20 px-4 py-3" type="text" id="euro_norm" name="euro_norm"
                           value="<?= $old('euro_norm') ?>">
                </div>
                <div>
                    <label class="mb-2 block font-medium text-content" for="gear_type">Gearkasse</label>
                    <select class="w-full rounded-lg border border-primary/20 px-4 py-3" id="gear_type" name="gear_type">
                        <option value="">Ikke angivet</option>
                        <?php foreach (['Manuel', 'Automatisk', 'CVT', 'Halvautomatisk'] as $gear): ?>
                            <option value="<?= $gear ?>"<?= $selected('gear_type', $gear) ?>><?= $gear ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block font-medium text-content" for="drive_wheels">Træk</label>
                    <select class="w-full rounded-lg border border-primary/20 px-4 py-3" id="drive_wheels" name="drive_wheels">
                        <option value="">Ikke angivet</option>
                        <?php foreach (['Forhjulstræk', 'Baghjulstræk', '4x4'] as $drive): ?>
                            <option value="<?= $drive ?>"<?= $selected('drive_wheels', $drive) ?>><?= $drive ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
            <h2 class="text-xl font-bold text-content">Mål, vægt og udseende</h2>
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <?php
                $dimensionFields = [
                    'weight' => 'Egenvægt (kg)',
                    'payload' => 'Nyttelast (kg)',
                    'towing_capacity' => 'Trækkapacitet (kg)',
                    'max_towing_brake' => 'Trækvægt med bremser (kg)',
                    'length' => 'Længde (mm)',
                    'width' => 'Bredde (mm)',
                    'height' => 'Højde (mm)',
                    'trunk_size' => 'Bagagerum (liter)',
                    'tank_capacity' => 'Tankkapacitet (liter)',
                    'doors' => 'Antal døre',
                    'airbags' => 'Antal airbags',
                ];
                ?>
                <?php foreach ($dimensionFields as $field => $label): ?>
                    <div>
                        <label class="mb-2 block font-medium text-content" for="<?= $field ?>"><?= htmlspecialchars($label) ?></label>
                        <input class="w-full rounded-lg border border-primary/20 px-4 py-3" type="number" id="<?= $field ?>"
                               name="<?= $field ?>" min="0" value="<?= $old($field) ?>">
                    </div>
                <?php endforeach; ?>
                <div>
                    <label class="mb-2 block font-medium text-content" for="color">Farve</label>
                    <input class="w-full rounded-lg border border-primary/20 px-4 py-3" type="text" id="color" name="color"
                           value="<?= $old('color') ?>">
                </div>
                <div>
                    <label class="mb-2 block font-medium text-content" for="color_type">Farvetype</label>
                    <select class="w-full rounded-lg border border-primary/20 px-4 py-3" id="color_type" name="color_type">
                        <?php foreach (['Standard', 'Metallic', 'Mat'] as $colorType): ?>
                            <option value="<?= $colorType ?>"<?= $selected('color_type', $colorType) ?>><?= $colorType ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end gap-6 pb-3">
                    <label class="flex items-center gap-2 font-medium text-content">
                        <input class="h-4 w-4" type="checkbox" id="abs_brakes" name="abs_brakes" value="1"
                            <?= isset($_POST['abs_brakes']) ? ' checked' : '' ?>>
                        ABS
                    </label>
                    <label class="flex items-center gap-2 font-medium text-content">
                        <input class="h-4 w-4" type="checkbox" id="esp" name="esp" value="1"
                            <?= isset($_POST['esp']) ? ' checked' : '' ?>>
                        ESP
                    </label>
                </div>
            </div>
        </section>

        <?php if (!empty($equipmentOptions)): ?>
            <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
                <h2 class="text-xl font-bold text-content">Udstyr</h2>
                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($equipmentOptions as $equipment): ?>
                        <label class="flex items-start gap-3 rounded-lg border border-primary/10 p-3 text-sm text-content">
                            <input class="mt-0.5 h-4 w-4" type="checkbox" name="equipment[]" value="<?= (int) $equipment['id'] ?>"
                                <?= in_array((string) $equipment['id'], $postedEquipment, true) ? ' checked' : '' ?>>
                            <span>
                                <?= htmlspecialchars($equipment['name']) ?>
                                <?php if (!empty($equipment['category'])): ?>
                                    <span class="block text-xs text-content/50"><?= htmlspecialchars($equipment['category']) ?></span>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
            <h2 class="text-xl font-bold text-content">Beskrivelse og billeder</h2>
            <div class="mt-6 space-y-5">
                <div>
                    <label class="mb-2 block font-medium text-content" for="description">Beskrivelse</label>
                    <textarea class="min-h-36 w-full rounded-lg border border-primary/20 px-4 py-3" id="description"
                              name="description"><?= $old('description') ?></textarea>
                </div>
                <div>
                    <label class="mb-2 block font-medium text-content" for="images">Billeder</label>
                    <input class="block w-full rounded-lg border border-primary/20 bg-page text-sm text-content file:mr-4 file:border-0 file:bg-primary/10 file:px-4 file:py-3 file:font-semibold"
                           type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
                    <p class="mt-2 text-sm text-content/60">JPG, PNG eller WebP. Maksimalt 5 MB pr. billede.</p>
                    <div class="mt-5 hidden" id="image_preview_wrapper">
                        <p class="mb-3 text-sm font-semibold text-content">Vælg annoncens hovedbillede</p>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4" id="image_preview" aria-live="polite"></div>
                    </div>
                </div>
            </div>
        </section>

        <button class="w-full rounded-lg bg-accent px-6 py-3.5 font-semibold text-white transition hover:bg-accent/80 sm:w-auto"
                type="submit">
            Opret annonce
        </button>
    </form>
</main>

<script>
    const lookupButton = document.getElementById('lookup_vehicle');
    const lookupInput = document.getElementById('vehicle_lookup');
    const lookupMessage = document.getElementById('lookup_message');
    const listingForm = document.getElementById('listing_form');
    const brandIdInput = document.getElementById('brand_id');
    const modelIdInput = document.getElementById('model_id');
    const brandNameInput = document.getElementById('brand_name');
    const modelNameInput = document.getElementById('model_name');
    const lookupCompleteInput = document.getElementById('vehicle_lookup_complete');
    const lookupTypeInput = document.getElementById('vehicle_lookup_type');
    const lookupValueInput = document.getElementById('vehicle_lookup_value');
    const imageInput = document.getElementById('images');
    const imagePreview = document.getElementById('image_preview');
    const imagePreviewWrapper = document.getElementById('image_preview_wrapper');
    let imagePreviewUrls = [];

    function showLookupMessage(text, success = false) {
        lookupMessage.textContent = text;
        lookupMessage.className = `mt-3 text-sm ${success ? 'text-success' : 'text-danger'}`;
    }

    function clearVehicleIdentity() {
        brandIdInput.value = '';
        modelIdInput.value = '';
        brandNameInput.value = '';
        modelNameInput.value = '';
        lookupCompleteInput.value = '';
        lookupTypeInput.value = '';
        lookupValueInput.value = '';
    }

    function clearImagePreviews() {
        imagePreviewUrls.forEach((url) => URL.revokeObjectURL(url));
        imagePreviewUrls = [];
        imagePreview.replaceChildren();
    }

    imageInput.addEventListener('change', () => {
        clearImagePreviews();

        const files = Array.from(imageInput.files || []);
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        const maxSize = 5 * 1024 * 1024;
        const firstValidIndex = files.findIndex((file) => allowedTypes.includes(file.type) && file.size <= maxSize);

        imagePreviewWrapper.classList.toggle('hidden', files.length === 0);

        files.forEach((file, index) => {
            const isValid = allowedTypes.includes(file.type) && file.size <= maxSize;
            const imageUrl = URL.createObjectURL(file);
            imagePreviewUrls.push(imageUrl);

            const card = document.createElement('label');
            card.className = 'cursor-pointer overflow-hidden rounded-lg border border-primary/15 bg-page';

            const image = document.createElement('img');
            image.className = 'aspect-[4/3] w-full object-cover';
            image.src = imageUrl;
            image.alt = `Forhåndsvisning af ${file.name}`;

            const controls = document.createElement('span');
            controls.className = 'flex items-start gap-2 p-3 text-sm';

            const radio = document.createElement('input');
            radio.className = 'mt-0.5 h-4 w-4 shrink-0';
            radio.type = 'radio';
            radio.name = 'main_image_index';
            radio.value = String(index);
            radio.disabled = !isValid;
            radio.checked = index === firstValidIndex;

            const text = document.createElement('span');
            text.className = isValid ? 'min-w-0 text-content' : 'min-w-0 text-danger';

            const choice = document.createElement('span');
            choice.className = 'block font-semibold';
            choice.textContent = isValid ? 'Brug som hovedbillede' : 'Filen kan ikke uploades';

            const filename = document.createElement('span');
            filename.className = 'mt-1 block truncate text-xs opacity-65';
            filename.textContent = file.name;
            filename.title = file.name;

            text.append(choice, filename);
            controls.append(radio, text);
            card.append(image, controls);
            imagePreview.append(card);
        });
    });

    window.addEventListener('beforeunload', clearImagePreviews);

    lookupInput.addEventListener('input', clearVehicleIdentity);

    lookupButton.addEventListener('click', async () => {
        const identifier = lookupInput.value.toUpperCase().replace(/\s+/g, '');
        const isPlate = /^[A-Z]{2}\d{5}$/.test(identifier);
        const isVin = /^[A-HJ-NPR-Z0-9]{17}$/.test(identifier);

        if (!isPlate && !isVin) {
            showLookupMessage('Skriv en gyldig nummerplade eller et stelnummer på 17 tegn.');
            return;
        }

        clearVehicleIdentity();
        lookupButton.disabled = true;
        lookupButton.textContent = 'Henter…';
        lookupMessage.className = 'mt-3 text-sm text-content/60';
        lookupMessage.textContent = 'Henter oplysninger fra Synsbasen…';

        try {
            const parameter = isPlate ? 'plate' : 'vin';
            const url = `api/bilopslag?${parameter}=${encodeURIComponent(identifier)}`;
            const response = await fetch(url, {headers: {'Accept': 'application/json'}});

            if (!(response.headers.get('content-type') || '').includes('application/json')) {
                throw new Error('Bilopslaget er ikke tilgængeligt endnu.');
            }

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.error || 'Biloplysningerne kunne ikke hentes.');
            }

            const data = result.data || {};
            const brandName = data.brand_name || result.brand_name || '';
            const modelName = data.model_name || result.model_name || '';

            if (!data.brand_id || !brandName || !modelName) {
                throw new Error('Backend mangler at returnere bilens mærke og model.');
            }

            Object.entries(data).forEach(([field, value]) => {
                if (value === null || value === '' || ['brand_id', 'model_id', 'brand_name', 'model_name'].includes(field)) {
                    return;
                }

                const input = listingForm.elements.namedItem(field);
                if (!input) return;

                if (input.type === 'checkbox') {
                    input.checked = Boolean(Number(value));
                } else {
                    input.value = value;
                }
            });

            brandIdInput.value = data.brand_id;
            modelIdInput.value = data.model_id || '0';
            brandNameInput.value = brandName;
            modelNameInput.value = modelName;
            lookupCompleteInput.value = '1';
            lookupTypeInput.value = parameter;
            lookupValueInput.value = identifier;

            const message = result.model_matched
                ? 'Biloplysningerne er hentet. Kontrollér de øvrige felter.'
                : 'Biloplysningerne er hentet. Modellen oprettes, når annoncen gemmes.';
            showLookupMessage(message, true);
        } catch (error) {
            clearVehicleIdentity();
            showLookupMessage(error.message || 'Biloplysningerne kunne ikke hentes.');
        } finally {
            lookupButton.disabled = false;
            lookupButton.textContent = 'Hent biloplysninger';
        }
    });

    listingForm.addEventListener('submit', (event) => {
        if (lookupCompleteInput.value !== '1' || !brandNameInput.value || !modelNameInput.value) {
            event.preventDefault();
            showLookupMessage('Hent først bilens mærke og model via nummerplade eller stelnummer.');
            lookupInput.focus();
        }
    });
</script>

<?php require __DIR__ . '/templates/footer.php'; ?>
