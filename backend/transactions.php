<?php
require_once 'db.php';
require_once __DIR__ . '/auth.php';

function calculateCommission($price) {
    if ($price < 200000) {
        return $price * 0.06; // 6%
    } elseif ($price >= 200000 && $price < 1000000) {
        return $price * 0.03; // 3%
    } else {
        return $price * 0.01; // 1%
    }
}

function buyCar() {
    global $pdo;

    isLoggedIn(); 

    $buyerId = $_SESSION['user_id'];
    $listingId = (int)($_POST['listing_id'] ?? 0);

    if (!$listingId) return "Annonce ID mangler";

    try {
        $pdo->beginTransaction();

        // Hent annonce
        $stmt = $pdo->prepare("SELECT * FROM car_listings WHERE id = ? AND status = 'aktiv'");
        $stmt->execute([$listingId]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$listing) {
            $pdo->rollBack();
            return "Bilen findes ikke eller er allerede solgt";
        }
        
        if ($listing['seller_user_id'] == $buyerId) {
            $pdo->rollBack();
            return "Du kan ikke købe din egen bil";
        }

        $price = (float)$listing['price'];
        $commission = calculateCommission($price);
        $sellerGets = $price - $commission;

        // Tjek købers saldo
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$buyerId]);
        $buyer = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($buyer['balance'] < $price) {
            $pdo->rollBack();
            return "Du har ikke nok penge på kontoen";
        }

        // TRÆK fra køber
        $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$price, $buyerId]);

        // Køber transaktion
        $stmt = $pdo->prepare("INSERT INTO transactions 
            (user_id, type, amount, method, status, description) 
            VALUES (?, 'køb', ?, 'intern', 'gennemført', ?)");
        $stmt->execute([$buyerId, $price, "Køb af bil #" . $listingId]);

        // OVERFØR til sælger (minus kommission)
        if ($listing['seller_company_id']) {
            $stmt = $pdo->prepare("UPDATE companies SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$sellerGets, $listing['seller_company_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$sellerGets, $listing['seller_user_id']]);
        }

        // Sælger transaktion
        $stmt = $pdo->prepare("INSERT INTO transactions 
            (user_id, company_id, type, amount, fee, method, status, description) 
            VALUES (?, ?, 'salg', ?, ?, 'intern', 'gennemført', ?)");
        $stmt->execute([
            $listing['seller_user_id'], 
            $listing['seller_company_id'], 
            $price, 
            $commission,
            "Salg af bil #" . $listingId . " (kommission " . number_format($commission, 2) . " kr.)"
        ]);

        // KOMMISSION til superadmin
        $superadminStmt = $pdo->prepare("SELECT id FROM users WHERE role_id = 5 LIMIT 1");
        $superadminStmt->execute();
        $superadmin = $superadminStmt->fetch(PDO::FETCH_ASSOC);
    
        if ($superadmin && $commission > 0) {
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$commission, $superadmin['id']]);
        
            $stmt = $pdo->prepare("INSERT INTO transactions 
                (user_id, type, amount, method, status, description) 
                VALUES (?, 'indbetaling', ?, 'intern', 'gennemført', ?)");
            $stmt->execute([
                $superadmin['id'], 
                $commission, 
                "Kommission fra salg #" . $listingId . " (" . $price . " kr.)"
            ]);
        }

        // Marker som solgt
        $stmt = $pdo->prepare("UPDATE car_listings SET status = 'solgt', sold_at = NOW() WHERE id = ?");
        $stmt->execute([$listingId]);

        // Gem salgshistorik
        $paymentMethod = htmlspecialchars(trim($_POST['payment_method'] ?? 'intern'));

        $stmt = $pdo->prepare("INSERT INTO sales_history 
            (listing_id, seller_user_id, seller_company_id, buyer_user_id, buyer_company_id, sale_price, sale_date, payment_method, notes) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
        $stmt->execute([
            $listingId,
            $listing['seller_user_id'],
            $listing['seller_company_id'],
            $buyerId,
            null, // buyer_company_id sæt hvis køber er firma
            $price,
            $paymentMethod,
            "Kommission: " . number_format($commission, 2) . " kr. | Sælger modtager: " . number_format($sellerGets, 2) . " kr."
        ]);

        $pdo->commit();
        return "Bil købt! Kommission på " . number_format($commission, 2) . " kr. er fratrukket.";

    } catch (PDOException $e) {
        $pdo->rollBack();
        return "Fejl: " . $e->getMessage();
    }
}

function addBalance(){
    global $pdo;

    isLoggedIn();

    $userId = $_SESSION['user_id'];

     if (empty($_POST['amount'])) {
        return "Beløb skal angives";
    }
    
    $amount = (float)$_POST['amount'];
    
    if ($amount <= 0) {
        return "Beløb skal være større end 0";
    }

    try{
        $stmt = $pdo->Prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$amount, $userId]);

       $stmt = $pdo->prepare("
            INSERT INTO transactions 
            (user_id, type, amount, method, status, description) 
            VALUES (?, 'indbetaling', ?, 'kort', 'gennemført', 'Indbetaling via kort')
        ");

         $stmt->execute([$userId, $amount]);
         return number_format($amount, 2, ',', '.') . " kr. indbetalt!";

    } catch(PDOException $e){
        return "fejl: " . $e->getMessage();
    }
}

function withDraw(){

    global $pdo;

    isLoggedIn();

    $userId = $_SESSION['user_id'];

    if (empty($_POST['amount'])) {
        return "Beløb skal angives";
    }
    
    $amount = (float)$_POST['amount'];
    
    if ($amount <= 0) {
        return "Beløb skal være større end 0";
    }

  try {
        // Tjek saldo først
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || $user['balance'] < $amount) {
            return "Ikke nok penge på kontoen";
        }

        // Træk fra saldo
        $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$amount, $userId]);

        // Gem transaktion
        $stmt = $pdo->prepare("
            INSERT INTO transactions 
            (user_id, type, amount, method, status, description) 
            VALUES (?, 'udbetaling', ?, 'iban', 'gennemført', 'Udbetaling til bankkonto')
        ");
        $stmt->execute([$userId, $amount]);
        
        return number_format($amount, 2, ',', '.') . " kr. udbetalt!";

    } catch (PDOException $e) {
        return "Fejl: " . $e->getMessage();
    }

}
?>