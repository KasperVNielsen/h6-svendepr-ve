<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../user.php';

// Test getUser()
$user = getUser(4); // din test-bruger fra eksempeldata
echo "=== getUser(4) ===\n";
echo "<pre>" . print_r($user, true) . "</pre>";

// Test changePassword()
echo "\n=== changePassword() ===\n";
$result = changePassword(4, 'password', 'nytpassword123');
echo "<pre>" . print_r($result, true) . "</pre>";

// Skift det tilbage så testen kan køres igen
changePassword(4, 'nytpassword123', 'password');

// Test updateProfile()
echo "\n=== updateProfile() ===\n";
$ok = updateProfile(4, [ 'iban' => '48932644893264']);
var_dump($ok);