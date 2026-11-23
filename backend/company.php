<?php 
require_once 'db.php';
require_once __DIR__ . '/auth.php';

$message = "";
$toastClass = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action']) && $_POST['action'] === 'add_employee') {
        $message = addEmployee();
    } else {
        $message = createCompany();
    }
}

function createCompany() {
    global $pdo;
    
   
    session_start();
    isLoggedIn();
   
    $userId = $_SESSION['user_id']; 

    // validate fields
    if (empty($_POST['cvr']) || empty($_POST['company_name']) || 
        empty($_POST['address']) || empty($_POST['city']) || empty($_POST['zip_code'])) {
        return "Alle felter skal udfyldes";
    }
     
    $cvr = htmlspecialchars(trim($_POST['cvr']));
    $companyName = htmlspecialchars(trim($_POST['company_name']));
    $address = htmlspecialchars(trim($_POST['address']));
    $city = htmlspecialchars(trim($_POST['city']));
    $zipCode = htmlspecialchars(trim($_POST['zip_code']));
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
    $website = htmlspecialchars(trim($_POST['website'] ?? ''));
    $description = htmlspecialchars(trim($_POST['description'] ?? ''));
    
    // check if the cvr already exist
    $checkCvr = $pdo->prepare("SELECT id FROM companies WHERE cvr = ?");
    $checkCvr->execute([$cvr]);
    
    if ($checkCvr->rowCount() > 0) {
        return "CVR nummer findes allerede";
    }
    
    // check if the user allready owns a company
    $checkOwner = $pdo->prepare("SELECT id FROM companies WHERE owner_user_id = ?");
    $checkOwner->execute([$userId]);
    
    if ($checkOwner->rowCount() > 0) {
        return "Du ejer allerede et firma";
    }

    try {
        // create comapny with the user id owner_user_id
        $stmt = $pdo->prepare("INSERT INTO companies (
            owner_user_id, 
            cvr, 
            company_name, 
            address, 
            zip_code, 
            city, 
            email, 
            phone,
            website,
            description
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $userId,   
            $cvr,
            $companyName,
            $address,
            $zipCode,
            $city,
            $email,
            $phone,
            $website,
            $description
        ]);
        
        $companyId = $pdo->lastInsertId();
        
        // Opret brugeren som medarbejder i firmaet
        $stmt = $pdo->prepare("INSERT INTO company_employees (
            company_id, 
            user_id, 
            position, 
            can_create_listings, 
            can_edit_listings, 
            can_manage_transactions
        ) VALUES (?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $companyId,
            $userId,
            'Ejer',
            1, 
            1, 
            1  
        ]);
        
        // update the users role to 'firma' (role_id = 2)
        $stmt = $pdo->prepare("UPDATE users SET role_id = 2 WHERE id = ?");
        $stmt->execute([$userId]);
        
        return "Firma oprettet!";
        
    } catch (PDOException $e) {
        return "Fejl: " . $e->getMessage();
    }
}

function addEmployee() {
    global $pdo;
    
    isLoggedIn();;
    
    $ownerId = $_SESSION['user_id'];
    
    
    if (empty($_POST['employee_email']) || empty($_POST['company_id'])) {
        return "Email og firma skal angives";
    }
    
    $employeeEmail = filter_var(trim($_POST['employee_email']), FILTER_SANITIZE_EMAIL);
    $companyId = (int)$_POST['company_id'];
    $position = htmlspecialchars(trim($_POST['position'] ?? 'Medarbejder'));
    $canCreate = isset($_POST['can_create']) ? 1 : 0;
    $canEdit = isset($_POST['can_edit']) ? 1 : 0;
    $canManage = isset($_POST['can_manage']) ? 1 : 0;
    
   
    $checkOwner = $pdo->prepare("
        SELECT id FROM companies 
        WHERE id = ? AND owner_user_id = ?
    ");
    $checkOwner->execute([$companyId, $ownerId]);
    
    if ($checkOwner->rowCount() === 0) {
        return "Du har ikke tilladelse til at tilføje medarbejdere til dette firma";
    }
    
    // find the user via email
    $findUser = $pdo->prepare("SELECT id FROM users WHERE email = ? AND is_active = 1");
    $findUser->execute([$employeeEmail]);
    
    if ($findUser->rowCount() === 0) {
        return "Bruger med denne email findes ikke";
    }
    
    $employee = $findUser->fetch(PDO::FETCH_ASSOC);
    $employeeId = $employee['id'];
    
    // check if the user is allready an employee on the company
    $checkEmp = $pdo->prepare("
        SELECT id FROM company_employees 
        WHERE company_id = ? AND user_id = ?
    ");
    $checkEmp->execute([$companyId, $employeeId]);
    
    if ($checkEmp->rowCount() > 0) {
        return "Brugeren er allerede ansat i firmaet";
    }
    
    // check if the user inst an owner of a company
    $checkOwnCompany = $pdo->prepare("
        SELECT id FROM companies WHERE owner_user_id = ?
    ");
    $checkOwnCompany->execute([$employeeId]);
    
    if ($checkOwnCompany->rowCount() > 0) {
        return "Brugeren ejer allerede et firma og kan ikke ansættes";
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO company_employees (
                company_id, user_id, position,
                can_create_listings, can_edit_listings, can_manage_transactions
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $companyId,
            $employeeId,
            $position,
            $canCreate,
            $canEdit,
            $canManage
        ]);
        
        return "Medarbejder tilføjet!";
        
    } catch (PDOException $e) {
        return "Fejl: " . $e->getMessage();
    }
}


function deleteCompany() {
    global $pdo;

    requireLogin();

    $userId = $_SESSION['user_id'];

    if (empty($_POST['company_id'])) {
        return "Firma ID mangler";
    }

    $companyId = (int)$_POST['company_id'];

    // Check that the user owns the company
    $check = $pdo->prepare("
        SELECT id
        FROM companies
        WHERE id = ?
        AND owner_user_id = ?
        AND is_active = 1
    ");
    $check->execute([$companyId, $userId]);

    if (!$check->fetchColumn()) {
        return "Du ejer ikke dette firma";
    }

    try {
        $pdo->beginTransaction();

        //Remove company employees
        $stmt = $pdo->prepare("
            DELETE FROM company_employees
            WHERE company_id = ?
        ");
        $stmt->execute([$companyId]);

        //Reset owner's role to normal user
        $stmt = $pdo->prepare("
            UPDATE users
            SET role_id = 1
            WHERE id = ?
        ");
        $stmt->execute([$userId]);

        //Soft delete company and start 30 day retention
        $stmt = $pdo->prepare("
            UPDATE companies
            SET is_active = 0,
                deleted_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$companyId]);

        $pdo->commit();

        return "Firmaet er deaktiveret. Oplysningerne gemmes i 30 dage.";

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('deleteCompany: ' . $e->getMessage());

        return "Der opstod en fejl. Prøv igen senere.";
    }
}


function reactivateCompany() {
    global $pdo;

    isLoggedIn();

    $userId = $_SESSION['user_id'];

    if (empty($_POST['company_id'])) {
        return "Firma ID mangler";
    }

    $companyId = (int)$_POST['company_id'];

    try {
        $pdo->beginTransaction();

        // Check that the company belongs to the user,
        // is deactivated, and is within the 30-day period
        $check = $pdo->prepare("
            SELECT *
            FROM companies
            WHERE id = ?
              AND owner_user_id = ?
              AND is_active = 0
              AND deleted_at IS NOT NULL
              AND deleted_at >= NOW() - INTERVAL 30 DAY
            FOR UPDATE
        ");

        $check->execute([$companyId, $userId]);
        $company = $check->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $pdo->rollBack();

            return "Firmaet findes ikke, er allerede aktivt, "
                 . "eller fristen for genaktivering er udløbet.";
        }

        //Reactivate company and reset deletion timestamp
        $stmt = $pdo->prepare("
            UPDATE companies
            SET is_active = 1,
                deleted_at = NULL
            WHERE id = ?
              AND owner_user_id = ?
        ");
        $stmt->execute([$companyId, $userId]);

        //Restore owner as company employee
        $stmt = $pdo->prepare("
            INSERT INTO company_employees (
                company_id, user_id, position,
                can_create_listings,
                can_edit_listings,
                can_manage_transactions,
                is_active
            ) VALUES (?, ?, 'Ejer', 1, 1, 1, 1)
        ");
        $stmt->execute([$companyId, $userId]);

        //Restore user's owner role
        $stmt = $pdo->prepare("
            UPDATE users
            SET role_id = 2
            WHERE id = ?
        ");
        $stmt->execute([$userId]);

        $pdo->commit();

        return "Firma genaktiveret! Velkommen tilbage, "
             . htmlspecialchars($company['company_name']);

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('reactivateCompany: ' . $e->getMessage());

        return "Fejl ved genaktivering. Prøv igen senere.";
    }
}



?>