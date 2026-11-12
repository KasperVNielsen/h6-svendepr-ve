<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function getListings($limit = 30){
    global $pdo;
    $stmt = prepare("
        cl.*, b.name AS brand_name, m.name AS model_name FROM car_listings cl
        JOIN brands b ON cl.brand_id = b.id
        JOIN models m ON cl.model_id = m.id
        WHERE cl.status = 'aktiv' AND cl.is_active = 1
        ORDER BY cl.created_at DESC
        LIMIT:limit
    ");

}


?>

