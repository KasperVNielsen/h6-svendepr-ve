<?php
// backend/api/vehicle-lookup.php
// Returns only fields that can be used to autofill a vehicle listing.

session_start();

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function normalizeVehicleName(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = strtr($value, [
        'æ' => 'ae',
        'ø' => 'o',
        'å' => 'aa',
    ]);

    return preg_replace('/[^a-z0-9]/', '', $value);
}

function nullableInt($value): ?int
{
    return $value !== null && $value !== '' && is_numeric($value)
        ? (int) $value
        : null;
}

function nullableFloat($value): ?float
{
    return $value !== null && $value !== '' && is_numeric($value)
        ? (float) $value
        : null;
}

function nullableString($value): ?string
{
    if ($value === null || is_array($value) || is_object($value)) {
        return null;
    }

    $value = trim((string) $value);
    return $value !== '' ? $value : null;
}

function formatRegistrationDate(?string $date): ?string
{
    if (!$date) {
        return null;
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('n/Y', $timestamp) : null;
}

if (empty($_SESSION['user_id'])) {
    respond(401, [
        'success' => false,
        'error' => 'Du skal være logget ind.',
    ]);
}

$plate = strtoupper(preg_replace(
    '/\s+/',
    '',
    trim($_GET['plate'] ?? $_GET['registration'] ?? '')
));

$vin = strtoupper(trim($_GET['vin'] ?? ''));

// Exactly one lookup method must be supplied.
if (($plate !== '') === ($vin !== '')) {
    respond(400, [
        'success' => false,
        'error' => 'Angiv enten nummerplade eller stelnummer.',
    ]);
}

if ($plate !== '' && !preg_match('/^[A-Z]{2}\d{5}$/', $plate)) {
    respond(400, [
        'success' => false,
        'error' => 'Ugyldigt nummerpladeformat. Fx AB12345.',
    ]);
}

if ($vin !== '' && !preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) {
    respond(400, [
        'success' => false,
        'error' => 'Ugyldigt stelnummer. Et VIN skal være 17 tegn.',
    ]);
}

// A new lookup replaces any previously verified vehicle in this session.
unset($_SESSION['vehicle_listing_lookup']);

$stmt = $pdo->prepare("
    SELECT value
    FROM settings
    WHERE key_name = 'synsbasen_api_token'
    LIMIT 1
");
$stmt->execute();
$token = $stmt->fetchColumn();

if (!$token) {
    respond(500, [
        'success' => false,
        'error' => 'Synsbasen API-nøgle mangler. Kontakt admin.',
    ]);
}

// Only request expandable resources that can contribute to listing fields.
// Confirm supported expansion names against your Synsbasen API version.
$expansions = ['engine', 'weight', 'axle', 'body', 'equipment', 'emission'];

$identifier = $vin !== ''
    ? 'vin/' . rawurlencode($vin)
    : 'registration/' . rawurlencode($plate);

$query = [];
foreach ($expansions as $expansion) {
    $query[] = 'expand[]=' . rawurlencode($expansion);
}
$query[] = 'dmr_fallback=true';

$url = 'https://api.synsbasen.dk/v1/vehicles/'
    . $identifier
    . '?'
    . implode('&', $query);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $token",
        'Accept: application/json',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    error_log('Synsbasen cURL error: ' . $curlError);
    respond(502, [
        'success' => false,
        'error' => 'Kunne ikke kontakte Synsbasen.',
    ]);
}

if ($status === 404) {
    respond(404, [
        'success' => false,
        'error' => 'Køretøj ikke fundet.',
    ]);
}

if ($status === 401 || $status === 403) {
    error_log('Synsbasen authentication error: ' . $status);
    respond(502, [
        'success' => false,
        'error' => 'Synsbasen afviste API-adgangen.',
    ]);
}

if ($status === 429) {
    respond(503, [
        'success' => false,
        'error' => 'Synsbasen API-kvote er opbrugt. Prøv igen senere.',
    ]);
}

if ($status !== 200) {
    error_log('Synsbasen HTTP status: ' . $status . ' Response: ' . substr($response, 0, 1000));
    respond(502, [
        'success' => false,
        'error' => 'Kunne ikke hente køretøjsdata. Prøv igen senere.',
    ]);
}

$json = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('Synsbasen JSON error: ' . json_last_error_msg());
    respond(502, [
        'success' => false,
        'error' => 'Ugyldigt svar fra Synsbasen.',
    ]);
}

$v = $json['data'] ?? null;
if (!is_array($v) || empty($v['brand'])) {
    respond(404, [
        'success' => false,
        'error' => 'Ingen køretøjsdata fundet.',
    ]);
}

$engine = is_array($v['engine'] ?? null) ? $v['engine'] : [];
$weight = is_array($v['weight'] ?? null) ? $v['weight'] : [];
$body = is_array($v['body'] ?? null) ? $v['body'] : [];
$emission = is_array($v['emission'] ?? null) ? $v['emission'] : [];
$equipment = is_array($v['equipment'] ?? null) ? $v['equipment'] : [];

$brandName = nullableString($v['brand'] ?? null);
$modelName = nullableString($v['model'] ?? null);

// Match Synsbasen brand/model names to the IDs used by the listing form.
$brandId = null;
$modelId = null;

$brands = $pdo->query('SELECT id, name FROM brands')->fetchAll(PDO::FETCH_ASSOC);
foreach ($brands as $brand) {
    if (normalizeVehicleName($brand['name']) === normalizeVehicleName($brandName ?? '')) {
        $brandId = (int) $brand['id'];
        break;
    }
}

if ($brandId !== null && $modelName !== null) {
    $stmt = $pdo->prepare('SELECT id, name FROM models WHERE brand_id = ?');
    $stmt->execute([$brandId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $model) {
        if (normalizeVehicleName($model['name']) === normalizeVehicleName($modelName)) {
            $modelId = (int) $model['id'];
            break;
        }
    }
}

// Normalize common fuel labels to the values used in the listing form.
$fuelRaw = $engine['fuel_type_extended']
    ?? $engine['fuel_type']
    ?? $v['fuel_type']
    ?? null;

$fuelMap = [
    'benzin' => 'Benzin',
    'petrol' => 'Benzin',
    'gasoline' => 'Benzin',
    'diesel' => 'Diesel',
    'el' => 'El',
    'electric' => 'El',
    'hybrid' => 'Hybrid',
    'plugin hybrid' => 'Plug-in hybrid',
    'plug-in hybrid' => 'Plug-in hybrid',
    'brint' => 'Brint',
    'hydrogen' => 'Brint',
];

$fuel = null;
if ($fuelRaw !== null && !is_array($fuelRaw)) {
    $fuelKey = mb_strtolower(trim((string) $fuelRaw), 'UTF-8');
    $fuel = $fuelMap[$fuelKey] ?? nullableString($fuelRaw);
}

$automatic = $engine['automatic_transmission'] ?? null;
$gearType = $automatic === null
    ? null
    : ((bool) $automatic ? 'Automatisk' : 'Manuel');

$driveWheels = null;
$equipmentNames = [];

foreach ($equipment as $item) {
    if (!is_array($item)) {
        continue;
    }

    $name = nullableString($item['name'] ?? null);
    if ($name === null) {
        continue;
    }

    $equipmentNames[] = $name;
    $normalized = mb_strtolower($name, 'UTF-8');

    if (
        str_contains($normalized, 'firehjulstræk')
        || str_contains($normalized, 'firehjulstraek')
        || str_contains($normalized, '4x4')
    ) {
        $driveWheels = '4x4';
    }
}

$absBrakes = null;
$esp = null;
$airbags = null;

foreach ($equipmentNames as $name) {
    $normalized = mb_strtolower($name, 'UTF-8');

    if (str_contains($normalized, 'abs')) {
        $absBrakes = 1;
    }
    if (str_contains($normalized, 'esp')) {
        $esp = 1;
    }
    if (str_contains($normalized, 'airbag')) {
        // Quantity is not assumed here; the equipment name only confirms presence.
        $airbags = 1;
    }
}

$color = nullableString($body['color'] ?? null);

$colorType = null;
foreach ($equipmentNames as $name) {
    if (mb_strtolower($name, 'UTF-8') === 'metallak') {
        $colorType = 'Metallic';
        break;
    }
}

$vehicleWeight = nullableInt(
    $weight['vehicle_weight']
    ?? $weight['driveable_weight_minimum']
    ?? null
);

$payload = null;
if (
    isset($weight['total_weight'], $weight['vehicle_weight'])
    && is_numeric($weight['total_weight'])
    && is_numeric($weight['vehicle_weight'])
    && (int) $weight['total_weight'] > (int) $weight['vehicle_weight']
) {
    $payload = (int) $weight['total_weight'] - (int) $weight['vehicle_weight'];
}

$firstRegistrationDate = nullableString($v['first_registration_date'] ?? null);
$modelYear = nullableInt($v['model_year'] ?? null);

if ($modelYear === null && $firstRegistrationDate !== null) {
    $timestamp = strtotime($firstRegistrationDate);
    if ($timestamp) {
        $modelYear = (int) date('Y', $timestamp);
    }
}

$title = trim(implode(' ', array_filter([
    $brandName,
    $modelName,
    nullableString($v['variant'] ?? null),
])));

$euroNorm = nullableString(
    $emission['euronorm']
    ?? $emission['euro_norm']
    ?? null
);

// Taxes can be dependent on vehicle category and registration details.
// Only populate this if the API response actually contains a single amount.
$periodicTax = null;
$taxes = $v['periodic_taxes'] ?? null;
if (is_array($taxes) && isset($taxes['total_amount'])) {
    $periodicTax = nullableFloat($taxes['total_amount']);
}

// This response deliberately contains only fields from the listing form
// that can reasonably be populated from vehicle data. Seller-entered fields
// such as price, condition, description and general_info are not returned.
$data = [
    'brand_id' => $brandId,
    'brand_name' => $brandName,
    'model_id' => $modelId,
    'model_name' => $modelName,
    'title' => $title !== '' ? $title : null,

    'engine_info' => nullableString(implode(' ', array_filter([
        nullableString($v['variant'] ?? null),
        nullableString($v['version'] ?? null),
    ]))) ,

    'horsepower' => nullableInt($engine['horsepower'] ?? null),
    'torque' => nullableInt($engine['torque'] ?? null),
    'cylinders' => nullableInt($engine['cylinders'] ?? null),
    'fuel_type' => $fuel,
    'fuel_consumption' => nullableFloat($engine['fuel_efficiency'] ?? null),
    'co2_emission' => nullableFloat($emission['co2'] ?? null),
    'euro_norm' => $euroNorm,
    'periodic_tax' => $periodicTax,

    'acceleration' => nullableFloat($engine['acceleration'] ?? null),
    'top_speed' => nullableInt($engine['top_speed'] ?? null),
    'gear_type' => $gearType,
    'gear_count' => nullableInt($engine['gears'] ?? null),
    'drive_wheels' => $driveWheels,

    'weight' => $vehicleWeight,
    'payload' => $payload,
    'towing_capacity' => nullableInt($weight['trailer_with_brakes_weight_maximum'] ?? null),
    'max_towing_brake' => nullableInt($weight['trailer_with_brakes_weight_maximum'] ?? null),

    'length' => nullableInt($body['length'] ?? null),
    'width' => nullableInt($body['width'] ?? null),
    'height' => nullableInt($body['height'] ?? null),
    'trunk_size' => nullableInt($body['trunk_size'] ?? null),
    'tank_capacity' => nullableFloat($engine['tank_capacity'] ?? null),
    'doors' => nullableInt($body['doors'] ?? null),

    'abs_brakes' => $absBrakes,
    'esp' => $esp,
    'airbags' => $airbags,

    'model_year' => $modelYear,
    'first_registration' => formatRegistrationDate($firstRegistrationDate),
    'mileage' => nullableInt($v['mileage'] ?? null),
    'color' => $color,
    'color_type' => $colorType,

    'category' => nullableString($v['category'] ?? null),
    'body_type' => nullableString($v['body_type'] ?? $body['body_type'] ?? null),
];

// Keep the verified vehicle identity on the server. createListing() uses this
// instead of trusting editable values sent by the browser.
$_SESSION['vehicle_listing_lookup'] = [
    'brand_id' => $brandId,
    'brand_name' => $brandName,
    'model_id' => $modelId ?? 0,
    'model_name' => $modelName,
    'category' => $data['category'],
    'lookup_type' => $vin !== '' ? 'vin' : 'plate',
    'lookup_value' => $vin !== '' ? $vin : $plate,
    'looked_up_at' => time(),
];

respond(200, [
    'success' => true,
    'message' => 'Køretøjsdata hentet.',
    'brand_matched' => $brandId !== null,
    'model_matched' => $modelId !== null,
    'data' => $data,
]);
