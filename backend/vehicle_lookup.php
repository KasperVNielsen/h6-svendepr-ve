
<?php
/**
 * php backend/tests/test-vehicle-lookup.php AS87640
 *
 * php backend/tests/test-vehicle-lookup.php VF7X1REVF72378327
 *
 * php backend/tests/test-vehicle-lookup.php AS87640 VF7X1REVF72378327
 * 
 * php backend/tests/test-vehicle-lookup.php AS87640 --save
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This test must be run from the command line.\n");
}

require_once __DIR__ . '/db.php';

$expansions = [
    'engine',
    'weight',
    'axle',
    'body',
    'equipment',
    'emission',
    'periodic_taxes',
    'inspections',
    'dmr_data'
];

$testsPassed = 0;
$testsFailed = 0;

function printJson($data): void
{
    echo json_encode(
        $data,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    ) . "\n";
}

function printSection(string $title): void
{
    echo "\n";
    echo str_repeat('=', 80) . "\n";
    echo " $title\n";
    echo str_repeat('=', 80) . "\n";
}

function isAvailable($value): bool
{
    return $value !== null &&
        $value !== '' &&
        $value !== [];
}

printSection('SYNSBASEN COMPLETE VEHICLE LOOKUP TEST');

echo "Date: " . date('Y-m-d H:i:s') . "\n";

$arguments = array_slice($argv, 1);

$saveResponse = in_array('--save', $arguments, true);

$arguments = array_values(array_filter(
    $arguments,
    fn($arg) => $arg !== '--save'
));

$plate = strtoupper(trim($arguments[0] ?? ''));
$vin = strtoupper(trim($arguments[1] ?? ''));

// Allow VIN as first argument.

if (
    $plate !== '' &&
    preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $plate)
) {
    $vin = $plate;
    $plate = '';
}

if ($plate === '' && $vin === '') {
    exit(
        "\nUsage:\n\n" .
        "Registration:\n" .
        "php test-vehicle-lookup.php AS87640\n\n" .
        "VIN:\n" .
        "php test-vehicle-lookup.php VF7X1REVF72378327\n\n" .
        "Both:\n" .
        "php test-vehicle-lookup.php AS87640 VF7X1REVF72378327\n\n" .
        "Save JSON:\n" .
        "php test-vehicle-lookup.php AS87640 --save\n\n"
    );
}

echo "\n[TEST] Loading Synsbasen API token... ";

$stmt = $pdo->prepare("
    SELECT value
    FROM settings
    WHERE key_name = 'synsbasen_api_token'
    LIMIT 1
");

$stmt->execute();

$token = $stmt->fetchColumn();

if (!$token) {
    echo "FAILED\n";
    exit("No Synsbasen API key found in settings.\n");
}

echo "OK\n";

function runVehicleTest(
    string $type,
    string $identifier,
    string $token,
    array $expansions,
    bool $saveResponse
): void {

    global $testsPassed, $testsFailed;

    printSection("TESTING $type: $identifier");

    // Build URL.

    if ($type === 'VIN') {
        $url = 'https://api.synsbasen.dk/v1/vehicles/vin/'
            . rawurlencode($identifier);
    } else {
        $url = 'https://api.synsbasen.dk/v1/vehicles/registration/'
            . rawurlencode($identifier);
    }

    // Add all requested expansions.

    $query = [];

    foreach ($expansions as $expansion) {
        $query[] = 'expand[]=' . rawurlencode($expansion);
    }

    $query[] = 'dmr_fallback=true';

    $url .= '?' . implode('&', $query);

    echo "\n[INFO] Request URL:\n";
    echo $url . "\n";

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Accept: application/json"
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($ch);

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $error = curl_error($ch);

    $contentType = curl_getinfo(
        $ch,
        CURLINFO_CONTENT_TYPE
    );

    curl_close($ch);

    echo "\n[INFO] HTTP Status: $status\n";
    echo "[INFO] Content Type: " . ($contentType ?: 'Unknown') . "\n";

    if ($response === false) {

        echo "\n[FAIL] cURL error: $error\n";

        $testsFailed++;

        return;
    }

    if ($status !== 200) {

        echo "\n[FAIL] API returned HTTP $status\n";

        echo "\nAPI response:\n";
        echo $response . "\n";

        if ($status === 401 || $status === 403) {
            echo "\n[INFO] Check API key and subscription.\n";
        }

        if ($status === 404) {
            echo "\n[INFO] Vehicle not found.\n";
        }

        if ($status === 429) {
            echo "\n[INFO] API rate limit reached.\n";
        }

        $testsFailed++;

        return;
    }

    $json = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {

        echo "\n[FAIL] Invalid JSON response.\n";

        echo json_last_error_msg() . "\n";

        echo "\nRaw response:\n";
        echo $response . "\n";

        $testsFailed++;

        return;
    }

    echo "\n[PASS] Valid JSON response\n";

    $vehicle = $json['data'] ?? null;

    if (!is_array($vehicle)) {

        echo "\n[FAIL] Missing data object.\n";

        echo "\nComplete response:\n";
        printJson($json);

        $testsFailed++;

        return;
    }

    echo "[PASS] Vehicle data found\n";

    printSection('VEHICLE SUMMARY');

    $summaryFields = [
        'id',
        'registration',
        'vin',
        'brand',
        'brand_id',
        'model',
        'model_id',
        'brand_and_model',
        'variant',
        'variant_id',
        'version',
        'version_id',
        'model_year',
        'first_registration_date',
        'fuel_type',
        'mileage',
        'mileage_annual_average',
        'kind',
        'usage',
        'category',
        'body_type',
        'eu_version',
        'eu_variant',
        'ec_type_approval',
        'ec_type_approval_corrected',
        'status',
        'status_updated_at',
        'registration_status',
        'registration_status_updated_at',
        'last_inspection_date',
        'last_inspection_result',
        'last_inspection_kind',
        'next_inspection_date_estimate',
        'ncap_five',
        'leasing_period_start',
        'leasing_period_end',
        'extra_equipment'
    ];

    foreach ($summaryFields as $field) {

        $value = $vehicle[$field] ?? null;

        if (is_array($value)) {
            $value = json_encode(
                $value,
                JSON_UNESCAPED_UNICODE
            );
        }

        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        echo str_pad($field, 40) . ': '
            . ($value ?? 'NULL')
            . "\n";
    }

    printSection('EXPANDED OBJECT AVAILABILITY');

    foreach ($expansions as $expansion) {

        $exists = array_key_exists($expansion, $vehicle);

        $value = $vehicle[$expansion] ?? null;

        if (!$exists) {

            echo "[MISSING] $expansion\n";

        } elseif (!isAvailable($value)) {

            echo "[EMPTY]   $expansion\n";

        } else {

            echo "[FOUND]   $expansion\n";

        }
    }

    printSection('ALL TOP-LEVEL VEHICLE FIELDS');

    foreach ($vehicle as $key => $value) {

        if (is_array($value)) {
            echo "\n$key:\n";
            printJson($value);
        } else {

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            echo "$key: " . ($value ?? 'NULL') . "\n";
        }
    }

    printSection('COMPLETE SYNSBASEN JSON RESPONSE');

    printJson($json);

    if ($saveResponse) {

        $directory = __DIR__ . '/responses';

        if (
            !is_dir($directory) &&
            !mkdir($directory, 0750, true) &&
            !is_dir($directory)
        ) {
            echo "\n[FAIL] Could not create response directory.\n";
        } else {

            $filename = $type . '_'
                . preg_replace('/[^A-Z0-9]/', '', $identifier)
                . '_'
                . date('Ymd_His')
                . '.json';

            $filepath = $directory . '/' . $filename;

            $saved = file_put_contents(
                $filepath,
                json_encode(
                    $json,
                    JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
                ),
                LOCK_EX
            );

            if ($saved !== false) {
                echo "\n[PASS] Response saved to:\n";
                echo $filepath . "\n";
            } else {
                echo "\n[FAIL] Could not save response.\n";
            }
        }
    }

    printSection('VALIDATION');

    $requiredFields = [
        'id',
        'brand',
        'model',
        'registration',
        'vin'
    ];

    $missing = [];

    foreach ($requiredFields as $field) {

        if (
            !isset($vehicle[$field]) ||
            $vehicle[$field] === ''
        ) {
            $missing[] = $field;
        }
    }

    if (empty($missing)) {

        echo "[PASS] Required vehicle fields exist\n";

        $testsPassed++;

    } else {

        echo "[WARNING] Missing fields: "
            . implode(', ', $missing)
            . "\n";

        echo "[INFO] Vehicle may still be usable.\n";

        $testsFailed++;
    }

    echo "\n[PASS] $type lookup completed.\n";
}


if ($plate !== '') {

    if (!preg_match('/^[A-Z]{2}\d{5}$/', $plate)) {

        echo "\n[FAIL] Invalid registration: $plate\n";

        $testsFailed++;

    } else {

        runVehicleTest(
            'REGISTRATION',
            $plate,
            $token,
            $expansions,
            $saveResponse
        );
    }
}

if ($vin !== '') {

    if (!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) {

        echo "\n[FAIL] Invalid VIN: $vin\n";

        $testsFailed++;

    } else {

        runVehicleTest(
            'VIN',
            $vin,
            $token,
            $expansions,
            $saveResponse
        );
    }
}

printSection('TEST RESULTS');

echo "Passed: $testsPassed\n";
echo "Failed: $testsFailed\n";

if ($testsFailed === 0) {

    echo "\nALL TESTS COMPLETED SUCCESSFULLY!\n";

    exit(0);
}

echo "\nSOME TESTS NEED ATTENTION!\n";

exit(1);
