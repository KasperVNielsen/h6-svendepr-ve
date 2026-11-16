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
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}




function sanitizeListingInput() {
    //  VALIDER PÅKRÆVEDE FELTER 
    $required = ['brand_id','model_id','title','price','condition','model_year','mileage','fuel_type'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            return "Feltet '$field' skal udfyldes";
        }
    }

    //  SANITIZE 
    $data = [];
    $data['brand_id']         = (int)$_POST['brand_id'];
    $data['model_id']         = (int)$_POST['model_id'];
    $data['title']            = htmlspecialchars(trim($_POST['title']), ENT_QUOTES, 'UTF-8');
    $data['price']            = (float)$_POST['price'];
    $data['price_type']       = in_array($_POST['price_type'] ?? '', ['fast','forhandling']) ? $_POST['price_type'] : 'fast';
    $data['condition']        = in_array($_POST['condition'] ?? '', ['brugt','ny','demobil']) ? $_POST['condition'] : 'brugt';
    $data['engine_info']      = htmlspecialchars(trim($_POST['engine_info'] ?? ''), ENT_QUOTES, 'UTF-8');
    $data['horsepower']       = !empty($_POST['horsepower']) ? (int)$_POST['horsepower'] : null;
    $data['torque']           = !empty($_POST['torque']) ? (int)$_POST['torque'] : null;
    $data['cylinders']        = !empty($_POST['cylinders']) ? (int)$_POST['cylinders'] : null;
    $data['fuel_type']        = $_POST['fuel_type'];
    $data['fuel_consumption'] = !empty($_POST['fuel_consumption']) ? (float)$_POST['fuel_consumption'] : null;
    $data['co2_emission']     = !empty($_POST['co2_emission']) ? (int)$_POST['co2_emission'] : null;
    $data['euro_norm']        = htmlspecialchars(trim($_POST['euro_norm'] ?? ''), ENT_QUOTES, 'UTF-8');
    $data['periodic_tax']     = !empty($_POST['periodic_tax']) ? (float)$_POST['periodic_tax'] : null;
    $data['acceleration']     = !empty($_POST['acceleration']) ? (float)$_POST['acceleration'] : null;
    $data['top_speed']        = !empty($_POST['top_speed']) ? (int)$_POST['top_speed'] : null;
    $data['gear_type']        = !empty($_POST['gear_type']) ? $_POST['gear_type'] : null;
    $data['gear_count']       = !empty($_POST['gear_count']) ? (int)$_POST['gear_count'] : null;
    $data['drive_wheels']     = !empty($_POST['drive_wheels']) ? $_POST['drive_wheels'] : null;
    $data['weight']           = !empty($_POST['weight']) ? (int)$_POST['weight'] : null;
    $data['payload']          = !empty($_POST['payload']) ? (int)$_POST['payload'] : null;
    $data['towing_capacity']  = !empty($_POST['towing_capacity']) ? (int)$_POST['towing_capacity'] : null;
    $data['max_towing_brake'] = !empty($_POST['max_towing_brake']) ? (int)$_POST['max_towing_brake'] : null;
    $data['length']           = !empty($_POST['length']) ? (int)$_POST['length'] : null;
    $data['width']            = !empty($_POST['width']) ? (int)$_POST['width'] : null;
    $data['height']           = !empty($_POST['height']) ? (int)$_POST['height'] : null;
    $data['trunk_size']       = !empty($_POST['trunk_size']) ? (int)$_POST['trunk_size'] : null;
    $data['tank_capacity']    = !empty($_POST['tank_capacity']) ? (int)$_POST['tank_capacity'] : null;
    $data['doors']            = !empty($_POST['doors']) ? (int)$_POST['doors'] : null;
    $data['abs_brakes']       = isset($_POST['abs_brakes']) ? 1 : null;
    $data['esp']              = isset($_POST['esp']) ? 1 : null;
    $data['airbags']          = !empty($_POST['airbags']) ? (int)$_POST['airbags'] : null;
    $data['model_year']       = !empty($_POST['model_year']) ? (int)$_POST['model_year'] : null;
    $data['first_registration'] = htmlspecialchars(trim($_POST['first_registration'] ?? ''), ENT_QUOTES, 'UTF-8');
    $data['mileage']          = (int)$_POST['mileage'];
    $data['color']            = htmlspecialchars(trim($_POST['color'] ?? ''), ENT_QUOTES, 'UTF-8');
    $data['color_type']       = in_array($_POST['color_type'] ?? '', ['Metallic','Mat','Standard']) ? $_POST['color_type'] : 'Standard';
    $data['new_price']        = !empty($_POST['new_price']) ? (float)$_POST['new_price'] : null;
    $data['category']         = htmlspecialchars(trim($_POST['category'] ?? ''), ENT_QUOTES, 'UTF-8');
    $data['body_type']        = htmlspecialchars(trim($_POST['body_type'] ?? ''), ENT_QUOTES, 'UTF-8');
    $data['description']      = htmlspecialchars(trim($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8');
    $data['general_info']     = htmlspecialchars(trim($_POST['general_info'] ?? ''), ENT_QUOTES, 'UTF-8');

    return $data;
}

function createListing() {
    global $pdo;

    isLoggedIn();

    $userId = $_SESSION['user_id'];

    $seller = findSeller($_SESSION['user_id']);
    $sellerUserId = $seller['user_id'];
    $sellerCompanyId = $seller['company_id'];

    $data = sanitizeListingInput();
    if (is_string($data)) {
        return $data; 
    }
    
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO car_listings (
            seller_user_id, seller_company_id, brand_id, model_id,
            price, price_type, status, `condition`, engine_info, horsepower, torque,
            cylinders, fuel_type, fuel_consumption, co2_emission, euro_norm, periodic_tax,
            acceleration, top_speed, gear_type, gear_count, drive_wheels,
            weight, payload, towing_capacity, max_towing_brake, `length`, width, height,
            trunk_size, tank_capacity, doors, abs_brakes, esp, airbags,
            model_year, first_registration, mileage, color, color_type, new_price,
            category, body_type, title, description, general_info, main_image,
            view_count, is_featured, is_active
        ) VALUES (
            ?, ?, ?, ?, ?, ?, 'aktiv', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', 0, 0, 1
        )
    ");

    $stmt->execute([
        $sellerUserId, $sellerCompanyId, $data['brand_id'], $data['model_id'],
        $data['price'], $data['price_type'], $data['condition'], $data['engine_info'],
        $data['horsepower'], $data['torque'], $data['cylinders'], $data['fuel_type'],
        $data['fuel_consumption'], $data['co2_emission'], $data['euro_norm'], $data['periodic_tax'],
        $data['acceleration'], $data['top_speed'], $data['gear_type'], $data['gear_count'],
        $data['drive_wheels'], $data['weight'], $data['payload'], $data['towing_capacity'],
        $data['max_towing_brake'], $data['length'], $data['width'], $data['height'],
        $data['trunk_size'], $data['tank_capacity'], $data['doors'], $data['abs_brakes'],
        $data['esp'], $data['airbags'], $data['model_year'], $data['first_registration'],
        $data['mileage'], $data['color'], $data['color_type'], $data['new_price'],
        $data['category'], $data['body_type'], $data['title'], $data['description'],
        $data['general_info']
    ]); 
        $listingId = (int)$pdo->lastInsertId();

        //  handle pisc 
        if (!empty($_FILES['images']['name'][0])) {
            $uploadDir = __DIR__ . '/../frontend/images/listings/' . $listingId . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $mainImageSet = false;
            $mainImagePath = null;

            foreach ($_FILES['images']['tmp_name'] as $index => $tmpName) {
                if ($_FILES['images']['error'][$index] !== UPLOAD_ERR_OK) continue;

                $originalName = basename($_FILES['images']['name'][$index]);
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                $mime = mime_content_type($tmpName);
                if (!in_array($mime, $allowedMimes)) continue;
                if ($_FILES['images']['size'][$index] > 5 * 1024 * 1024) continue; // 5 MB max

                $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '', $originalName);
                $finalName = $listingId . '_' . $index . '_' . $safeName;

                $targetPath = $uploadDir . $finalName;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $dbPath = 'listings/' . $listingId . '/' . $finalName;
                    $isMain = 0;

                    if (!$mainImageSet) {
                        $mainImageSet = true;
                        $isMain = 1;
                        $mainImagePath = $dbPath;
                    }

                    $imgStmt = $pdo->prepare("
                        INSERT INTO car_images (listing_id, filename, original_name, file_size, mime_type, sort_order, is_main)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $imgStmt->execute([
                        $listingId,
                        $dbPath,
                        $originalName,
                        $_FILES['images']['size'][$index],
                        $mime,
                        $index,
                        $isMain
                    ]);
                }
            }

            if ($mainImagePath) {
                $upd = $pdo->prepare("UPDATE car_listings SET main_image = ? WHERE id = ?");
                $upd->execute([$mainImagePath, $listingId]);
            }
        }

        //  handle equipment 
        if (!empty($_POST['equipment']) && is_array($_POST['equipment'])) {
            $eqStmt = $pdo->prepare("INSERT INTO listing_equipment (listing_id, equipment_id) VALUES (?, ?)");
            foreach ($_POST['equipment'] as $eqId) {
                $eqStmt->execute([$listingId, (int)$eqId]);
            }
        }

        $pdo->commit();
        return "Annonce oprettet! ID: " . $listingId;

    } catch (PDOException $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
        return "Der opstod en fejl. Prøv igen senere.";
        //return error . $e->getMessage();              
    }
}
















?>

