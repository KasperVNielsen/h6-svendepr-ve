<?php 
require_once 'db.php';

$message = "";
$toastClass = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $message = createUser();
}

function createUser() {
    global $pdo;

    if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email']) || empty($_POST['password']) || empty($_POST['city'])) {
        return "Alle felter skal udfyldes";
    }

    $firstName = htmlspecialchars(trim($_POST['first_name']));
    $lastName = htmlspecialchars(trim($_POST['last_name']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $city = htmlspecialchars(trim($_POST['city']));
    
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);

    if ($check->rowCount() > 0) {
        return "Email findes allerede";
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, city, email, password_hash) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$firstName, $lastName, $city, $email, $password]);
        return "Bruger oprettet!";
    } catch (PDOException $e) {
        return "Fejl: " . $e->getMessage();
    }
}

function login() {
    global $pdo; 

    if (empty($_POST['email']) || empty($_POST['password'])) {
        return "Email og password er påkrævet";
    }

    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

   
    if ($user && password_verify($password, $user['password_hash'])) {
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $email;
        header("Location: ../index.php");
        exit();
    } else {
        return "Forkert email eller password";
    }
}

function isLoggedIn(){
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

     // Check if user is logged in
      if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // This prevents using sessions from deleted/disabled accounts
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id, is_active FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            return true;
        }
    } catch (PDOException $e) {
        return false;
    }
    
    return false;

}

function logOut() {
   
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    //clears the server-side session data
    $_SESSION = [];

 
    if (ini_get("session.use_cookies")) {
        //session_start() makes a cookie that stores session id
        //get cookie session
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            //set expiration time (11.5 hours) in the past
            time() - 42000,
            //settings as the original cookie, so the browser recognizes it as the same cookie and removes it
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    //destroy the session on the server session_destroy(); does not remove the cookie
    session_destroy();

    header("Location: ../index.php");
    exit();
}



?>