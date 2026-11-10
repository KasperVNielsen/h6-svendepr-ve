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





?>