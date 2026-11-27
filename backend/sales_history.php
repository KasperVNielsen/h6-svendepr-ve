<?php 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function loadSalesHistory(){

    global $pdo, $seller;

    if (!isLoggedIn()) {
        return "Du skal være logget ind.";
    }

    $sellerUserId = $seller['user_id'];
    $sellerCompanyId = $seller['company_id'];

    try {

        $stmt = $pdo->prepare("
            SELECT
                sh.*,
                cl.brand_id,
                cl.model_id,
                b.name AS brand_name,
                m.name AS model_name
            FROM sales_history sh
            LEFT JOIN car_listings cl
                ON sh.listing_id = cl.id
            LEFT JOIN brands b
                ON cl.brand_id = b.id
            LEFT JOIN models m
                ON cl.model_id = m.id
            WHERE
                sh.seller_user_id = ?
                OR sh.seller_company_id = ?

            ORDER BY sh.sale_date DESC
        ");

        $stmt->execute([
            $sellerUserId,
            $sellerCompanyId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch(PDOException $e) {

        error_log('loadSalesHistory: ' . $e->getMessage());

        return "Der opstod en fejl. Prøv igen senere.";
    }
}

function loadBuyHistory() {
    global $pdo;

    if (!isLoggedIn()) {
        return "You must be logged in.";
    }

    $userId = $_SESSION['user_id'];
    $buyer = findSeller($userId);

    if (!$buyer) {
        return [];
    }

    $buyerUserId = $buyer['user_id'];
    $buyerCompanyId = $buyer['company_id'];

    try {
        $stmt = $pdo->prepare("
            SELECT
                sh.*,
                cl.brand_id,
                cl.model_id,
                b.name AS brand_name,
                m.name AS model_name
            FROM sales_history sh
            LEFT JOIN car_listings cl ON sh.listing_id = cl.id
            LEFT JOIN brands b ON cl.brand_id = b.id
            LEFT JOIN models m ON cl.model_id = m.id
            WHERE
                sh.buyer_user_id = ?
                OR (
                    ? IS NOT NULL
                    AND sh.buyer_company_id = ?
                )
            ORDER BY sh.sale_date DESC
        ");

        $stmt->execute([
            $buyerUserId,
            $buyerCompanyId,
            $buyerCompanyId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Buy history error: " . $e->getMessage());
        return [];
    }
}
?>