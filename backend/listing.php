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

// identifies which company a user is allowed to create listings for, whether they own the company or work there with the appropriate permission.
function findSeller($userId) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT id FROM companies WHERE owner_user_id = ? AND is_active = 1");
    $stmt->execute([$userId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($company) {
        return ['user_id' => $userId, 'company_id' => $company['id']];
    }

    $stmt = $pdo->prepare("
        SELECT company_id FROM company_employees 
        WHERE user_id = ? AND is_active = 1 AND can_create_listings = 1
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'user_id'    => $userId,
        'company_id' => $emp ? $emp['company_id'] : null
    ];
}

function sanitizeListingInput() {
    //  validate fields
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

    $seller = findSeller($userId);
    $sellerUserId = $seller['user_id'];
    $sellerCompanyId = $seller['company_id'];

    $data = sanitizeListingInput();

    if (is_string($data)) {
        return $data;
    }

    try {
        $pdo->beginTransaction();

        $brandId = (int)($data['brand_id'] ?? 0);
        $modelId = (int)($data['model_id'] ?? 0);
        $modelName = trim($data['model_name'] ?? $_POST['model_name'] ?? '');
        $category = $data['category'] ?? null;

        if ($brandId <= 0) {
            throw new Exception('Ugyldigt bilmærke.');
        }

        // Check whether selected model belongs to selected brand
        if ($modelId > 0) {
            $stmt = $pdo->prepare("
                SELECT id
                FROM models
                WHERE id = ? AND brand_id = ? AND is_active = 1
                LIMIT 1
            ");

            $stmt->execute([$modelId, $brandId]);
            $modelId = (int)($stmt->fetchColumn() ?: 0);
        }

        // If no valid model ID, search by model name
        if ($modelId === 0 && $modelName !== '') {

            $stmt = $pdo->prepare("
                SELECT id
                FROM models
                WHERE brand_id = ?
                AND LOWER(name) = LOWER(?)
                LIMIT 1
            ");

            $stmt->execute([$brandId, $modelName]);
            $modelId = (int)($stmt->fetchColumn() ?: 0);

            // Create model if it doesn't exist
            if ($modelId === 0) {

                $slug = strtolower(trim($modelName));
                $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
                $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
                $slug = trim($slug, '-');

                if ($slug === '') {
                    throw new Exception('Ugyldigt modelnavn.');
                }

                // Ensure unique slug
                $baseSlug = $slug;
                $counter = 1;

                while (true) {
                    $stmt = $pdo->prepare("
                        SELECT id FROM models
                        WHERE slug = ?
                        LIMIT 1
                    ");

                    $stmt->execute([$slug]);

                    if (!$stmt->fetchColumn()) {
                        break;
                    }

                    $slug = $baseSlug . '-' . $counter++;
                }

                $stmt = $pdo->prepare("
                    INSERT INTO models (
                        brand_id,
                        name,
                        slug,
                        category,
                        is_active,
                        created_at
                    ) VALUES (?, ?, ?, ?, 1, NOW())
                ");

                $stmt->execute([
                    $brandId,
                    $modelName,
                    $slug,
                    $category
                ]);

                $modelId = (int)$pdo->lastInsertId();
            }
        }

        if ($modelId <= 0) {
            throw new Exception('Kunne ikke finde eller oprette bilmodellen.');
        }

        // Use the correct model ID in the listing
        $data['model_id'] = $modelId;


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
                ?, ?, ?, ?, ?, ?, 'aktiv', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, '', 0, 0, 1
            )
        ");

        $stmt->execute([
            $sellerUserId,
            $sellerCompanyId,
            $data['brand_id'],
            $data['model_id'],
            $data['price'],
            $data['price_type'],
            $data['condition'],
            $data['engine_info'],
            $data['horsepower'],
            $data['torque'],
            $data['cylinders'],
            $data['fuel_type'],
            $data['fuel_consumption'],
            $data['co2_emission'],
            $data['euro_norm'],
            $data['periodic_tax'],
            $data['acceleration'],
            $data['top_speed'],
            $data['gear_type'],
            $data['gear_count'],
            $data['drive_wheels'],
            $data['weight'],
            $data['payload'],
            $data['towing_capacity'],
            $data['max_towing_brake'],
            $data['length'],
            $data['width'],
            $data['height'],
            $data['trunk_size'],
            $data['tank_capacity'],
            $data['doors'],
            $data['abs_brakes'],
            $data['esp'],
            $data['airbags'],
            $data['model_year'],
            $data['first_registration'],
            $data['mileage'],
            $data['color'],
            $data['color_type'],
            $data['new_price'],
            $data['category'],
            $data['body_type'],
            $data['title'],
            $data['description'],
            $data['general_info']
        ]);

        $listingId = (int)$pdo->lastInsertId();

        if (!empty($_FILES['images']['name'][0])) {

            $uploadDir = __DIR__ . '/../frontend/images/listings/' . $listingId . '/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $mainImageSet = false;
            $mainImagePath = null;

            foreach ($_FILES['images']['tmp_name'] as $index => $tmpName) {

                if ($_FILES['images']['error'][$index] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $originalName = basename($_FILES['images']['name'][$index]);

                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                $mime = mime_content_type($tmpName);

                if (!in_array($mime, $allowedMimes, true)) continue;
                if ($_FILES['images']['size'][$index] > 5 * 1024 * 1024) continue;

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
                        INSERT INTO car_images (
                            listing_id, filename, original_name,
                            file_size, mime_type, sort_order, is_main
                        )
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
                $upd = $pdo->prepare("
                    UPDATE car_listings
                    SET main_image = ?
                    WHERE id = ?
                ");

                $upd->execute([$mainImagePath, $listingId]);
            }
        }

        if (!empty($_POST['equipment']) && is_array($_POST['equipment'])) {

            $eqStmt = $pdo->prepare("
                INSERT INTO listing_equipment (listing_id, equipment_id)
                VALUES (?, ?)
            ");

            foreach ($_POST['equipment'] as $eqId) {
                $eqStmt->execute([
                    $listingId,
                    (int)$eqId
                ]);
            }
        }


        $pdo->commit();

        return "Annonce oprettet! ID: " . $listingId;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('createListing error: ' . $e->getMessage());

        return "Der opstod en fejl. Prøv igen senere.";
    }
}



function editListing($listingId) {
    global $pdo;

    isLoggedIn();

    $userId = $_SESSION['user_id'];
    $listingId = (int)$listingId;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return "Ugyldig forespørgsel.";
    }

    //  find seller
    $seller = findSeller($_SESSION['user_id']);
    $sellerUserId    = $seller['user_id'];
    $sellerCompanyId = $seller['company_id'];

    // check if the listing belongs to the user
    $stmt = $pdo->prepare("SELECT * FROM car_listings WHERE id = ?");
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$listing) {
        return "Annoncen blev ikke fundet.";
    }

    $ownsAsUser    = ($listing['seller_user_id'] == $sellerUserId);
    $ownsAsCompany = ($sellerCompanyId !== null && $listing['seller_company_id'] == $sellerCompanyId);

    if (!$ownsAsUser && !$ownsAsCompany) {
        return "Du har ikke tilladelse til at redigere denne annonce.";
    }

    $data = sanitizeListingInput();
    if (is_string($data)) {
        return $data; 
    }

    try {
        $pdo->beginTransaction();

        //  OPDATER ANNONCEN 
        $stmt = $pdo->prepare("
            UPDATE car_listings SET
                brand_id = ?, model_id = ?,
                price = ?, price_type = ?, `condition` = ?,
                engine_info = ?, horsepower = ?, torque = ?, cylinders = ?,
                fuel_type = ?, fuel_consumption = ?, co2_emission = ?, euro_norm = ?, periodic_tax = ?,
                acceleration = ?, top_speed = ?, gear_type = ?, gear_count = ?, drive_wheels = ?,
                weight = ?, payload = ?, towing_capacity = ?, max_towing_brake = ?,
                `length` = ?, width = ?, height = ?,
                trunk_size = ?, tank_capacity = ?, doors = ?,
                abs_brakes = ?, esp = ?, airbags = ?,
                model_year = ?, first_registration = ?, mileage = ?,
                color = ?, color_type = ?, new_price = ?,
                category = ?, body_type = ?,
                title = ?, description = ?, general_info = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $data['brand_id'], $data['model_id'],
            $data['price'], $data['price_type'], $data['condition'],
            $data['engine_info'], $data['horsepower'], $data['torque'], $data['cylinders'],
            $data['fuel_type'], $data['fuel_consumption'], $data['co2_emission'], $data['euro_norm'], $data['periodic_tax'],
            $data['acceleration'], $data['top_speed'], $data['gear_type'], $data['gear_count'], $data['drive_wheels'],
            $data['weight'], $data['payload'], $data['towing_capacity'], $data['max_towing_brake'],
            $data['length'], $data['width'], $data['height'],
            $data['trunk_size'], $data['tank_capacity'], $data['doors'],
            $data['abs_brakes'], $data['esp'], $data['airbags'],
            $data['model_year'], $data['first_registration'], $data['mileage'],
            $data['color'], $data['color_type'], $data['new_price'],
            $data['category'], $data['body_type'],
            $data['title'], $data['description'], $data['general_info'],
            $listingId
        ]);

        //  deleted removed picture
        if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
            $delStmt = $pdo->prepare("SELECT id, filename, is_main FROM car_images WHERE id = ? AND listing_id = ?");
            $rmStmt  = $pdo->prepare("DELETE FROM car_images WHERE id = ? AND listing_id = ?");

            foreach ($_POST['delete_images'] as $imgId) {
                $delStmt->execute([(int)$imgId, $listingId]);
                $img = $delStmt->fetch(PDO::FETCH_ASSOC);
                if (!$img) continue;

                $rmStmt->execute([(int)$imgId, $listingId]);

                
                $fullPath = __DIR__ . '/../../images/' . $img['filename'];
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }

                //if the main picture got deleted insert a new one
                if ($img['is_main']) {
                    $next = $pdo->prepare("
                        SELECT id, filename FROM car_images 
                        WHERE listing_id = ? 
                        ORDER BY sort_order ASC LIMIT 1
                    ");
                    $next->execute([$listingId]);
                    if ($newMain = $next->fetch(PDO::FETCH_ASSOC)) {
                        $pdo->prepare("UPDATE car_images SET is_main = 1 WHERE id = ?")->execute([$newMain['id']]);
                        $pdo->prepare("UPDATE car_listings SET main_image = ? WHERE id = ?")
                            ->execute([$newMain['filename'], $listingId]);
                    } else {
                        $pdo->prepare("UPDATE car_listings SET main_image = '' WHERE id = ?")->execute([$listingId]);
                    }
                }
            }
        }

        // add pictures
        if (!empty($_FILES['images']['name'][0])) {
            $mainImageSet = ($listing['main_image'] !== ''); 
            $sortStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) AS max_sort FROM car_images WHERE listing_id = ?");
            $sortStmt->execute([$listingId]);
            $nextSort = (int)$sortStmt->fetch(PDO::FETCH_ASSOC)['max_sort'] + 1;

            foreach ($_FILES['images']['tmp_name'] as $index => $tmpName) {
                if ($_FILES['images']['error'][$index] !== UPLOAD_ERR_OK) continue;

                $mime = mime_content_type($tmpName);
                if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) continue;
                if ($_FILES['images']['size'][$index] > 5 * 1024 * 1024) continue;

                $file = [
                    'name'     => $_FILES['images']['name'][$index],
                    'tmp_name' => $tmpName,
                    'error'    => $_FILES['images']['error'][$index],
                    'size'     => $_FILES['images']['size'][$index],
                ];

                $dbPath = uploadCarImage($listingId, $file);

                if ($dbPath) {
                    $isMain = 0;
                    if (!$mainImageSet) {
                        $mainImageSet = true;
                        $isMain = 1;
                        $pdo->prepare("UPDATE car_listings SET main_image = ? WHERE id = ?")
                            ->execute([$dbPath, $listingId]);
                    }

                    $imgStmt = $pdo->prepare("
                        INSERT INTO car_images (listing_id, filename, original_name, file_size, mime_type, sort_order, is_main)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $imgStmt->execute([
                        $listingId, $dbPath, basename($file['name']),
                        $file['size'], $mime, $nextSort, $isMain
                    ]);
                    $nextSort++;
                }
            }
        }

        
        $pdo->prepare("DELETE FROM listing_equipment WHERE listing_id = ?")->execute([$listingId]);

        if (!empty($_POST['equipment']) && is_array($_POST['equipment'])) {
            $eqStmt = $pdo->prepare("INSERT INTO listing_equipment (listing_id, equipment_id) VALUES (?, ?)");
            foreach ($_POST['equipment'] as $eqId) {
                $eqStmt->execute([$listingId, (int)$eqId]);
            }
        }

        $pdo->commit();
        return "Annonce opdateret!";

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('editListing: ' . $e->getMessage());
        return "Der opstod en fejl. Prøv igen senere.";
    }
}

function deleteListing($listingId) {
    global $pdo;

    isLoggedIn();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return "Ugyldig forespørgsel.";
    }

    $listingId = (int)$listingId;

    //  find seller
    $seller = findSeller($_SESSION['user_id']);
    $sellerUserId    = $seller['user_id'];
    $sellerCompanyId = $seller['company_id'];

    //  TJEK AT ANNONCEN FINDES OG TILHØRER BRUGEREN 
    $stmt = $pdo->prepare("
    SELECT id, seller_user_id, seller_company_id
    FROM car_listings
    WHERE id = ? AND is_active = 1
    ");
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$listing) {
        return "Annoncen blev ikke fundet.";
    }

    $ownsAsUser    = ($listing['seller_user_id'] == $sellerUserId);
    $ownsAsCompany = ($sellerCompanyId !== null && $listing['seller_company_id'] == $sellerCompanyId);

    if (!$ownsAsUser && !$ownsAsCompany) {
        return "Du har ikke tilladelse til at slette denne annonce.";
    }

    // SOFT DELETE
    try {
        $stmt = $pdo->prepare("
            UPDATE car_listings 
            SET is_active = 0, status = 'slettet' 
            WHERE id = ?
        ");
        $stmt->execute([$listingId]);

        return "Annoncen er slettet.";
    } catch (PDOException $e) {
        error_log('deleteListing: ' . $e->getMessage());
        return "Der opstod en fejl. Prøv igen senere.";
    }
}


function buyListing($listingId) {
    global $pdo;

    isLoggedIn();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return "Invalid request.";
    }

    $buyerId = (int)$_SESSION['user_id'];
    $listingId = (int)$listingId;

    if ($listingId <= 0) {
        return "Invalid listing ID.";
    }

    try {
        $pdo->beginTransaction();

        // FIND AND LOCK THE LISTING
        $stmt = $pdo->prepare("
            SELECT *
            FROM car_listings
            WHERE id = ?
            FOR UPDATE
        ");
        $stmt->execute([$listingId]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$listing) {
            throw new Exception("Listing not found.");
        }

        // CHECK IF THE CAR IS AVAILABLE
        if ($listing['status'] !== 'aktiv' || !$listing['is_active']) {
            throw new Exception("This car is no longer available.");
        }

        // PREVENT USERS FROM BUYING THEIR OWN CARS
        if ((int)$listing['seller_user_id'] === $buyerId) {
            throw new Exception("You cannot buy your own car.");
        }

        $sellerId = (int)$listing['seller_user_id'];

        // LOCK BUYER AND SELLER IN THE SAME ORDER
        $userIds = [$buyerId, $sellerId];
        sort($userIds);

        $users = [];

        foreach ($userIds as $userId) {
            $stmt = $pdo->prepare("
                SELECT id, balance, is_active
                FROM users
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$userId]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new Exception("User not found.");
            }

            $users[$userId] = $user;
        }

        // CHECK BUYER ACCOUNT
        if (!(int)$users[$buyerId]['is_active']) {
            throw new Exception("Your account is inactive.");
        }

        // GET LISTING PRICE
        $price = $listing['price'];

        // CHECK BUYER BALANCE
        if (bccomp((string)$users[$buyerId]['balance'], (string)$price, 2) < 0) {
            throw new Exception("You don't have enough balance.");
        }

        // DEDUCT MONEY FROM BUYER
        $stmt = $pdo->prepare("
            UPDATE users
            SET balance = balance - ?
            WHERE id = ?
            AND balance >= ?
        ");
        $stmt->execute([$price, $buyerId, $price]);

        if ($stmt->rowCount() !== 1) {
            throw new Exception("Payment could not be completed.");
        }

        // TRANSFER MONEY TO SELLER
        $stmt = $pdo->prepare("
            UPDATE users
            SET balance = balance + ?
            WHERE id = ?
        ");
        $stmt->execute([$price, $sellerId]);

        // MARK LISTING AS SOLD
        $stmt = $pdo->prepare("
            UPDATE car_listings
            SET
                status = 'solgt',
                is_active = 0,
                sold_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$listingId]);

        $pdo->commit();

        return "You have successfully purchased: " . $listing['title'];

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return $e->getMessage();

    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('buyListing: ' . $e->getMessage());

        return "Something went wrong. Please try again later.";
    }
}
?>

