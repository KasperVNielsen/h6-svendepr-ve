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



function getCompanyInfo(int $companyId)
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT
            id,
            company_name,
            cvr,
            website,
            address,
            zip_code,
            city,
            phone,
            email,
            logo,
            description,
            is_verified,
            created_at
        FROM companies
        WHERE id = ?
          AND is_active = 1
          AND deleted_at IS NULL
        LIMIT 1
    ");

    $stmt->execute([$companyId]);

    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    return $company ?: null;
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

    isLoggedIn();

    $userId = $_SESSION['user_id'];

    if (empty($_POST['company_id'])) {
        return "Firma ID mangler";
    }

    $companyId = (int)$_POST['company_id'];

    try {
        $pdo->beginTransaction();

        // Check ownership and lock company
        $check = $pdo->prepare("
            SELECT id
            FROM companies
            WHERE id = ?
            AND owner_user_id = ?
            AND is_active = 1
            AND deleted_at IS NULL
            FOR UPDATE
        ");

        $check->execute([$companyId, $userId]);

        if (!$check->fetchColumn()) {
            $pdo->rollBack();
            return "Du ejer ikke dette firma";
        }

        // Soft delete company
        $stmt = $pdo->prepare("
            UPDATE companies
            SET is_active = 0,
                deleted_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$companyId]);

        // Get company's deletion timestamp
        $stmt = $pdo->prepare("
            SELECT deleted_at
            FROM companies
            WHERE id = ?
        ");
        $stmt->execute([$companyId]);

        $deletedAt = $stmt->fetchColumn();

        // Soft delete all active company cars
        $stmt = $pdo->prepare("
            UPDATE cars
            SET is_active = 0,
                deleted_at = ?
            WHERE company_id = ?
            UPDATE car_listings
            SET is_active = 0,
                deleted_at = NOW()
            WHERE seller_company_id = ?
            AND deleted_at IS NULL
        ");
        $stmt->execute([$deletedAt, $companyId]);

        // Soft delete all active company employees
        $stmt = $pdo->prepare("
            UPDATE company_employees
            SET is_active = 0,
                deleted_at = ?
            WHERE company_id = ?
            AND is_active = 1
            AND deleted_at IS NULL
        ");
        $stmt->execute([$deletedAt, $companyId]);

        // Reset owner's role to normal user
        $stmt = $pdo->prepare("
            UPDATE users
            SET role_id = 1
            WHERE id = ?
        ");
        $stmt->execute([$userId]);

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

        // Check company ownership and deletion period
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

        // Save original deletion timestamp
        $deletedAt = $company['deleted_at'];

        // Reactivate company
        $stmt = $pdo->prepare("
            UPDATE companies
            SET is_active = 1,
                deleted_at = NULL
            WHERE id = ?
            AND owner_user_id = ?
        ");
        $stmt->execute([$companyId, $userId]);

        // Restore cars deleted together with company
        $stmt = $pdo->prepare("
            UPDATE car_listings
            SET is_active = 1,
                deleted_at = NULL
            WHERE company_id = ?
            AND deleted_at = ?
            AND is_active = 0
            WHERE seller_company_id = ?
              AND deleted_at = ?
              AND is_active = 0
        ");
        $stmt->execute([$companyId, $deletedAt]);

        // Restore employees deleted together with company
        $stmt = $pdo->prepare("
            UPDATE company_employees
            SET is_active = 1,
                deleted_at = NULL
            WHERE company_id = ?
            AND deleted_at = ?
            AND is_active = 0
        ");
        $stmt->execute([$companyId, $deletedAt]);

        // Restore owner's role
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

function editCompany() {
    global $pdo;

    isLoggedIn();

    $userId = $_SESSION['user_id'];

    if (empty($_POST['company_id'])) {
        return "Firma ID mangler";
    }

    $companyId = (int)$_POST['company_id'];

    // Check company ownership
    $check = $pdo->prepare("
        SELECT id, logo
        FROM companies
        WHERE id = ?
        AND owner_user_id = ?
    ");

    $check->execute([$companyId, $userId]);

    $company = $check->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        return "Du ejer ikke dette firma";
    }

    // Company information
    $companyName = trim($_POST['company_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $zipCode = trim($_POST['zip_code'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone = trim($_POST['phone'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Validate required fields
    if (
        empty($companyName) ||
        empty($address) ||
        empty($city) ||
        empty($zipCode)
    ) {
        return "Firmanavn, adresse, by og postnummer skal udfyldes";
    }

    // Validate email
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Ugyldig emailadresse";
    }

    // Keep existing logo
    $logo = $company['logo'];
    $newLogoPath = null;

    // Handle logo upload
    if (
        isset($_FILES['logo']) &&
        $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            return "Fejl ved upload af logo";
        }

        // Maximum 5 MB
        if ($_FILES['logo']['size'] > 5 * 1024 * 1024) {
            return "Logoet må maksimalt være 5 MB";
        }

        // Validate image
        $imageInfo = getimagesize($_FILES['logo']['tmp_name']);

        if ($imageInfo === false) {
            return "Filen er ikke et gyldigt billede";
        }

        $allowedTypes = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_WEBP => 'webp'
        ];

        $imageType = $imageInfo[2];

        if (!isset($allowedTypes[$imageType])) {
            return "Kun JPG, PNG og WEBP er tilladt";
        }

        // Adjust this path if your project structure differs
        $uploadDir = dirname(__DIR__) . '/frontend/company_logo/';

        // Create folder if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return "Kunne ikke oprette company_logo mappen";
            }
        }

        if (!is_writable($uploadDir)) {
            return "Upload-mappen er ikke skrivbar";
        }

        // Filename: companyID_uniqueID.extension
        $filename = $companyId . '_' . bin2hex(random_bytes(8))
                  . '.' . $allowedTypes[$imageType];

        $destination = $uploadDir . $filename;

        if (!move_uploaded_file($_FILES['logo']['tmp_name'], $destination)) {
            return "Kunne ikke gemme logoet";
        }

        // Save relative frontend path in database
        $logo = 'company_logo/' . $filename;
        $newLogoPath = $destination;
    }

    try {

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE companies
            SET company_name = ?,
                address = ?,
                zip_code = ?,
                city = ?,
                email = ?,
                phone = ?,
                website = ?,
                description = ?,
                logo = ?
            WHERE id = ?
            AND owner_user_id = ?
        ");

        $stmt->execute([
            $companyName,
            $address,
            $zipCode,
            $city,
            $email,
            $phone,
            $website,
            $description,
            $logo,
            $companyId,
            $userId
        ]);

        $pdo->commit();

        // Delete old logo after successful update
        if ($newLogoPath !== null && !empty($company['logo'])) {

            $oldLogoPath = dirname(__DIR__) . '/frontend/' . $company['logo'];

            $logoDirectory = realpath($uploadDir);
            $oldDirectory = realpath(dirname($oldLogoPath));

            if (
                is_file($oldLogoPath) &&
                $oldDirectory !== false &&
                $oldDirectory === $logoDirectory
            ) {
                unlink($oldLogoPath);
            }
        }

        return "Firmaoplysninger opdateret!";

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Remove new logo if database update failed
        if ($newLogoPath !== null && is_file($newLogoPath)) {
            unlink($newLogoPath);
        }

        error_log('editCompany: ' . $e->getMessage());

        return "Fejl ved opdatering af firmaoplysninger";
    }
}


function deleteEmployee() {
    global $pdo;

    isLoggedIn();

    $userId = $_SESSION['user_id'];

    if (empty($_POST['company_id']) || empty($_POST['employee_id'])) {
        return "Firma ID eller medarbejder ID mangler";
    }

    $companyId = (int)$_POST['company_id'];
    $employeeId = (int)$_POST['employee_id'];

    try {

        // Check that the logged-in user owns the company
        $check = $pdo->prepare("
            SELECT id
            FROM companies
            WHERE id = ?
            AND owner_user_id = ?
            AND is_active = 1
            AND deleted_at IS NULL
        ");

        $check->execute([$companyId, $userId]);

        if (!$check->fetchColumn()) {
            return "Du ejer ikke dette firma";
        }

        // Soft delete employee
        $stmt = $pdo->prepare("
            UPDATE company_employees ce
            SET ce.is_active = 0
            WHERE ce.id = ?
            AND ce.company_id = ?
            AND ce.user_id != ?
            AND ce.is_active = 1
        ");

        $stmt->execute([
            $employeeId,
            $companyId,
            $userId
        ]);

        if ($stmt->rowCount() === 0) {
            return "Medarbejderen findes ikke eller kan ikke slettes";
        }

        return "Medarbejderen er fjernet fra firmaet!";

    } catch (Throwable $e) {

        error_log('deleteEmployee: ' . $e->getMessage());

        return "Der opstod en fejl. Prøv igen senere.";
    }
}

?>