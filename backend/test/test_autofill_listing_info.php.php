<?php
/**
 * backend/tests/test-vehicle-lookup.php
 *
 * CLI test for the listing autofill lookup.
 *
 * Usage:
 *   php backend/tests/test-vehicle-lookup.php AS87640
 *   php backend/tests/test-vehicle-lookup.php --vin 1HGBH41JXMN109186
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This test can only be run from the command line.\n");
}

require_once __DIR__ . '/../db.php';

function out(string $message = ''): void
{
    echo $message . PHP_EOL;
}

function valueForDisplay($value): string
{
    if ($value === null || $value === '') {
        return '[not available]';
    }

    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }

    if (is_array($value) || is_object($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    return (string) $value;
}

function fail(string $message, int $exitCode = 1): void
{
    out("ERROR: " . $message);
    exit($exitCode);
}

// Accept either a positional registration or --vin <VIN>.
$argument = $argv[1] ?? '';
$vin = '';

if ($argument === '--vin') {
    $vin = strtoupper(trim($argv[2] ?? ''));
    if ($vin === '') {
        fail('Missing VIN. Usage: php test-vehicle-lookup.php --vin <VIN>');
    }
} elseif (str_starts_with($argument, '--vin=')) {
    $vin = strtoupper(trim(substr($argument, 6)));
} else {
    $registration = strtoupper(preg_replace('/\s+/', '', trim($argument)));
}

if ($vin !== '') {
    if (!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) {
        fail('Invalid VIN format. A VIN must contain 17 characters.');
    }
    $identifier = 'vin/' . rawurlencode($vin);
    $lookupLabel = 'VIN: ' . $vin;
} else {
    $registration = $registration ?? '';
    if (!preg_match('/^[A-Z]{2}\d{5}$/', $registration)) {
        fail('Invalid registration. Usage: php test-vehicle-lookup.php AB12345');
    }
    $identifier = 'registration/' . rawurlencode($registration);
    $lookupLabel = 'Registration: ' . $registration;
}

out('==============================================');
out(' VEHICLE LISTING AUTOFILL TEST');
out('==============================================');
out($lookupLabel);
out();

// Read the same API token used by the lookup endpoint.
$stmt = $pdo->prepare("
    SELECT value
    FROM settings
    WHERE key_name = 'synsbasen_api_token'
    LIMIT 1
");
$stmt->execute();
$token = $stmt->fetchColumn();

if (!$token) {
    fail("Synsbasen API token not found in settings.");
}

// Keep the test request in sync with backend/api/vehicle-lookup.php.
$expansions = ['engine', 'weight', 'axle', 'body', 'equipment', 'emission'];
$query = [];

foreach ($expansions as $expansion) {
    $query[] = 'expand[]=' . rawurlencode($expansion);
}

$query[] = 'dmr_fallback=true';

$url = 'https://api.synsbasen.dk/v1/vehicles/'
    . $identifier
    . '?'
    . implode('&', $query);

out('Requesting vehicle data from Synsbasen...');

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
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    fail('cURL request failed: ' . $curlError);
}

if ($httpStatus !== 200) {
    out('HTTP status: ' . $httpStatus);
    out('Response: ' . substr($response, 0, 2000));
    fail('Synsbasen request did not return HTTP 200.');
}

$json = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    fail('Invalid JSON response: ' . json_last_error_msg());
}

$vehicle = $json['data'] ?? null;

if (!is_array($vehicle)) {
    out('API response:');
    out(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fail('Response does not contain a data object.');
}

// Extract only the listing-autofill data fields, matching the API endpoint.
$engine = is_array($vehicle['engine'] ?? null) ? $vehicle['engine'] : [];
$weight = is_array($vehicle['weight'] ?? null) ? $vehicle['weight'] : [];
$body = is_array($vehicle['body'] ?? null) ? $vehicle['body'] : [];
$emission = is_array($vehicle['emission'] ?? null) ? $vehicle['emission'] : [];
$equipment = is_array($vehicle['equipment'] ?? null) ? $vehicle['equipment'] : [];

$fuelRaw = $engine['fuel_type_extended']
    ?? $engine['fuel_type']
    ?? $vehicle['fuel_type']
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
    $fuel = $fuelMap[$fuelKey] ?? trim((string) $fuelRaw);
}

$automatic = $engine['automatic_transmission'] ?? null;
$gearType = $automatic === null
    ? null
    : ((bool) $automatic ? 'Automatisk' : 'Manuel');

$equipmentNames = [];
$driveWheels = null;
$absBrakes = null;
$esp = null;
$airbags = null;

foreach ($equipment as $item) {
    if (!is_array($item) || empty($item['name'])) {
        continue;
    }

    $name = trim((string) $item['name']);
    $equipmentNames[] = $name;
    $normalized = mb_strtolower($name, 'UTF-8');

    if (
        str_contains($normalized, 'firehjulstræk')
        || str_contains($normalized, 'firehjulstraek')
        || str_contains($normalized, '4x4')
    ) {
        $driveWheels = '4x4';
    }

    if (str_contains($normalized, 'abs')) {
        $absBrakes = 1;
    }
    if (str_contains($normalized, 'esp')) {
        $esp = 1;
    }
    if (str_contains($normalized, 'airbag')) {
        $airbags = 1;
    }
}

$vehicleWeight = $weight['vehicle_weight']
    ?? $weight['driveable_weight_minimum']
    ?? null;

$payload = null;
if (
    isset($weight['total_weight'], $weight['vehicle_weight'])
    && is_numeric($weight['total_weight'])
    && is_numeric($weight['vehicle_weight'])
    && (int) $weight['total_weight'] > (int) $weight['vehicle_weight']
) {
    $payload = (int) $weight['total_weight'] - (int) $weight['vehicle_weight'];
}

$firstRegistrationDate = $vehicle['first_registration_date'] ?? null;
$firstRegistration = null;

if ($firstRegistrationDate) {
    $timestamp = strtotime((string) $firstRegistrationDate);
    $firstRegistration = $timestamp ? date('n/Y', $timestamp) : null;
}

$modelYear = isset($vehicle['model_year']) ? (int) $vehicle['model_year'] : null;
if ($modelYear === null && $firstRegistrationDate) {
    $timestamp = strtotime((string) $firstRegistrationDate);
    $modelYear = $timestamp ? (int) date('Y', $timestamp) : null;
}

$title = trim(implode(' ', array_filter([
    $vehicle['brand'] ?? null,
    $vehicle['model'] ?? null,
    $vehicle['variant'] ?? null,
])));

$colorType = null;
foreach ($equipmentNames as $equipmentName) {
    if (mb_strtolower($equipmentName, 'UTF-8') === 'metallak') {
        $colorType = 'Metallic';
        break;
    }
}

$tax = null;
if (
    isset($vehicle['periodic_taxes']['total_amount'])
    && is_numeric($vehicle['periodic_taxes']['total_amount'])
) {
    $tax = (float) $vehicle['periodic_taxes']['total_amount'];
}

$autofill = [
    'title' => $title !== '' ? $title : null,
    'engine_info' => trim(implode(' ', array_filter([
        $vehicle['variant'] ?? null,
        $vehicle['version'] ?? null,
    ]))) ?: null,

    'horsepower' => isset($engine['horsepower']) ? (int) $engine['horsepower'] : null,
    'torque' => isset($engine['torque']) ? (int) $engine['torque'] : null,
    'cylinders' => isset($engine['cylinders']) ? (int) $engine['cylinders'] : null,
    'fuel_type' => $fuel,
    'fuel_consumption' => isset($engine['fuel_efficiency']) ? (float) $engine['fuel_efficiency'] : null,
    'co2_emission' => isset($emission['co2']) ? (float) $emission['co2'] : null,
    'euro_norm' => $emission['euronorm'] ?? $emission['euro_norm'] ?? null,
    'periodic_tax' => $tax,

    'acceleration' => isset($engine['acceleration']) ? (float) $engine['acceleration'] : null,
    'top_speed' => isset($engine['top_speed']) ? (int) $engine['top_speed'] : null,
    'gear_type' => $gearType,
    'gear_count' => isset($engine['gears']) ? (int) $engine['gears'] : null,
    'drive_wheels' => $driveWheels,

    'weight' => is_numeric($vehicleWeight) ? (int) $vehicleWeight : null,
    'payload' => $payload,
    'towing_capacity' => isset($weight['trailer_with_brakes_weight_maximum'])
        ? (int) $weight['trailer_with_brakes_weight_maximum'] : null,
    'max_towing_brake' => isset($weight['trailer_with_brakes_weight_maximum'])
        ? (int) $weight['trailer_with_brakes_weight_maximum'] : null,

    'length' => isset($body['length']) ? (int) $body['length'] : null,
    'width' => isset($body['width']) ? (int) $body['width'] : null,
    'height' => isset($body['height']) ? (int) $body['height'] : null,
    'trunk_size' => isset($body['trunk_size']) ? (int) $body['trunk_size'] : null,
    'tank_capacity' => isset($engine['tank_capacity']) ? (float) $engine['tank_capacity'] : null,
    'doors' => isset($body['doors']) ? (int) $body['doors'] : null,

    'abs_brakes' => $absBrakes,
    'esp' => $esp,
    'airbags' => $airbags,

    'model_year' => $modelYear,
    'first_registration' => $firstRegistration,
    'mileage' => isset($vehicle['mileage']) ? (int) $vehicle['mileage'] : null,
    'color' => $body['color'] ?? null,
    'color_type' => $colorType,

    'category' => $vehicle['category'] ?? null,
    'body_type' => $vehicle['body_type'] ?? $body['body_type'] ?? null,
];

out();
out('----------------------------------------------');
out(' LISTING AUTOFILL VALUES');
out('----------------------------------------------');

$missing = 0;
foreach ($autofill as $field => $value) {
    $display = valueForDisplay($value);
    out(str_pad($field, 25) . ': ' . $display);

    if ($value === null || $value === '') {
        $missing++;
    }
}

out('----------------------------------------------');
out('Fields checked: ' . count($autofill));
out('Fields with values: ' . (count($autofill) - $missing));
out('Fields unavailable: ' . $missing);
out();

out('Brand from API: ' . valueForDisplay($vehicle['brand'] ?? null));
out('Model from API: ' . valueForDisplay($vehicle['model'] ?? null));
out();


exit(0);
