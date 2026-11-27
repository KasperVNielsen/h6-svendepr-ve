<?php 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function loadSalesHistory(){
    
    global $pdo;

    if (!isLoggedIn()) {
        return "Du skal være logget ind.";
    }

    $sellerUserId = $seller['user_id'];
    $sellerCompanyId = $seller['company_id'];

    try{
        $stmt = $pdo->prepare("SELECT cl.*, b.name AS brand_name, m.name AS model_name
            FROM car_listings cl
            JOIN brands b ON cl.brand_id = b.id
            JOIN models m ON cl.model_id = m.id
            WHERE cl.status = 'solgt' 
            AND(cl.seller_user_id = ? OR cl.seller_company_id = ?)
            ORDER BY cl.sold_at DESC    
       ");
       
       $stmt->execute([$sellerUserId, $sellerCompanyId]);
       return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch(PDOException $e){
        error_log('loadSalesHistory: ' . $e->getMessage());
        return "Der opstod en fejl. Prøv igen senere.";
    }
}
?>