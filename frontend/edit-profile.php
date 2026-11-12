<?php
$pageTitle = 'Rediger profil | Bil kassen';
$basePath = '../';

$profileErrors = [];
$profileMessage = null;
$imageMessage = null;
$passwordErrors = [];
$passwordMessage = null;

require_once __DIR__ . '/../backend/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once __DIR__ . '/../backend/user.php';
$_SERVER['REQUEST_METHOD'] = $requestMethod;
require_once __DIR__ . '/templates/profile-image.php';

$userId = (int) $_SESSION['user_id'];
$user = getUser($userId);
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;

if ($user === null) {
    header('Location: login.php');
    exit;
}

if ($requestMethod === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        $invalidRequest = ['success' => false, 'message' => 'Formularen er udløbet. Prøv igen.'];

        if ($action === 'update_profile_image') {
            $imageMessage = $invalidRequest;
        } elseif ($action === 'change_password') {
            $passwordMessage = $invalidRequest;
        } else {
            $profileMessage = $invalidRequest;
        }

        $action = '';
    }

    if ($action === 'update_profile_image') {
        $imageMessage = saveProfileImage($userId, $_FILES['profile_image'] ?? null);

        if ($imageMessage['success']) {
            $user = getUser($userId);
        }
    }

    if ($action === 'update_profile') {
        $profileData = [
            'first_name' => $_POST['first_name'] ?? '',
            'last_name' => $_POST['last_name'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'address' => $_POST['address'] ?? '',
            'zip_code' => $_POST['zip_code'] ?? '',
            'city' => $_POST['city'] ?? '',
            'iban' => $_POST['iban'] ?? '',
        ];

        $result = updateProfile($userId, $profileData);

        if ($result['success']) {
            $profileMessage = $result;
            $user = getUser($userId);
        } else {
            $message = $result['message'];
            $lowerMessage = strtolower($message);
            $field = null;

            if (str_contains($lowerMessage, 'first_name')) {
                $field = 'first_name';
            } elseif (str_contains($lowerMessage, 'last_name')) {
                $field = 'last_name';
            } elseif (str_contains($lowerMessage, 'navn')) {
                $field = !preg_match('/\A[\p{L}]+\z/u', trim($profileData['first_name']))
                    ? 'first_name'
                    : 'last_name';
            } elseif (str_contains($lowerMessage, 'telefon')) {
                $field = 'phone';
            } elseif (str_contains($lowerMessage, 'adresse')) {
                $field = 'address';
            } elseif (str_contains($lowerMessage, 'postnummer')) {
                $field = 'zip_code';
            } elseif (str_contains($lowerMessage, 'by') || str_contains($lowerMessage, 'city')) {
                $field = 'city';
            } elseif (str_contains($lowerMessage, 'iban')) {
                $field = 'iban';
            }

            if ($field !== null) {
                $profileErrors[$field] = $message;
            } else {
                $profileMessage = $result;
            }

            $user = array_merge($user, $profileData);
        }
    }

    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '') {
            $passwordErrors['current_password'] = 'Nuværende password skal udfyldes.';
        } elseif ($newPassword === '') {
            $passwordErrors['new_password'] = 'Ny adgangskode skal udfyldes.';
        } elseif ($newPassword !== $confirmPassword) {
            $passwordErrors['confirm_password'] = 'De nye adgangskoder er ikke ens.';
        } else {
            $result = changePassword($userId, $currentPassword, $newPassword);

            if ($result['success']) {
                $passwordMessage = $result;
            } elseif (str_contains(strtolower($result['message']), 'nuværende')) {
                $passwordErrors['current_password'] = $result['message'];
            } elseif (str_contains(strtolower($result['message']), 'nyt password')) {
                $passwordErrors['new_password'] = $result['message'];
            } else {
                $passwordMessage = $result;
            }
        }
    }
}

$profileImageName = !empty($user['profile_image']) ? basename($user['profile_image']) : '';
$profileImageFilePath = __DIR__ . '/images/profile-images/' . $profileImageName;
$hasProfileImage = $profileImageName !== '' && is_file($profileImageFilePath);

require __DIR__ . '/templates/header.php';
?>

<main class="mx-auto w-full max-w-4xl flex-1 px-6 py-12">
    <div class="mb-8">
        <a class="text-sm font-semibold text-accent hover:underline" href="<?= $basePath ?>frontend/profile.php">
            &larr; Tilbage til min profil
        </a>
        <h1 class="mt-3 text-3xl font-bold text-content">Rediger profil</h1>
    </div>

    <form class="mb-8 rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8"
          method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_profile_image">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <h2 class="text-xl font-bold text-content">Profilbillede</h2>
        <div class="mt-6 flex flex-col gap-6 sm:flex-row sm:items-center">
            <?php if ($hasProfileImage): ?>
                <img class="h-24 w-24 shrink-0 rounded-full object-cover"
                     src="<?= $basePath ?>frontend/images/profile-images/<?= rawurlencode($profileImageName) ?>"
                     alt="Dit profilbillede">
            <?php else: ?>
                <div class="flex h-24 w-24 shrink-0 items-center justify-center rounded-full bg-primary text-white">
                    <svg class="h-12 w-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0A17.9 17.9 0 0 1 12 21.75c-2.7 0-5.25-.59-7.5-1.65Z" />
                    </svg>
                </div>
            <?php endif; ?>

            <div class="flex-1">
                <label class="mb-2 block font-medium text-content" for="profile_image">Vælg profilbillede</label>
                <input class="block w-full rounded-lg border border-primary/20 bg-page text-sm text-content file:mr-4 file:border-0 file:bg-primary/10 file:px-4 file:py-3 file:font-semibold file:text-content"
                       type="file" id="profile_image" name="profile_image"
                       accept="image/png,image/jpeg,image/webp" required>
                <?php if ($imageMessage !== null): ?>
                    <p class="mt-2 text-sm <?= $imageMessage['success'] ? 'text-success' : 'text-danger' ?>">
                        <?= htmlspecialchars($imageMessage['message']) ?>
                    </p>
                <?php else: ?>
                    <p class="mt-2 text-sm text-content/60">JPG, PNG eller WebP. Maksimalt 5 MB.</p>
                <?php endif; ?>

                <button class="mt-4 rounded-lg bg-accent px-4 py-2.5 font-semibold text-white transition hover:bg-accent/80"
                        type="submit">
                    Gem profilbillede
                </button>
            </div>
        </div>
    </form>

    <form class="mb-8 rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8" method="post">
        <input type="hidden" name="action" value="update_profile">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <h2 class="text-xl font-bold text-content">Personlige oplysninger</h2>

        <?php if ($profileMessage !== null): ?>
            <p class="mt-5 rounded-lg border px-4 py-3 text-sm <?= $profileMessage['success']
                ? 'border-success/20 bg-success/10 text-success'
                : 'border-danger/20 bg-danger/10 text-danger' ?>">
                <?= htmlspecialchars($profileMessage['message']) ?>
            </p>
        <?php endif; ?>

        <div class="mt-6 grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-2 block font-medium text-content" for="first_name">Fornavn</label>
                <input class="w-full rounded-lg border px-4 py-3 outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20 <?= isset($profileErrors['first_name']) ? 'border-danger' : 'border-primary/20' ?>"
                       type="text" id="first_name" name="first_name"
                       value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                <?php if (isset($profileErrors['first_name'])): ?>
                    <p class="mt-1.5 text-sm text-danger"><?= htmlspecialchars($profileErrors['first_name']) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label class="mb-2 block font-medium text-content" for="last_name">Efternavn</label>
                <input class="w-full rounded-lg border px-4 py-3 outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20 <?= isset($profileErrors['last_name']) ? 'border-danger' : 'border-primary/20' ?>"
                       type="text" id="last_name" name="last_name"
                       value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                <?php if (isset($profileErrors['last_name'])): ?>
                    <p class="mt-1.5 text-sm text-danger"><?= htmlspecialchars($profileErrors['last_name']) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label class="mb-2 block font-medium text-content" for="phone">Telefon</label>
                <input class="w-full rounded-lg border px-4 py-3 outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20 <?= isset($profileErrors['phone']) ? 'border-danger' : 'border-primary/20' ?>"
                       type="tel" id="phone" name="phone" inputmode="numeric" maxlength="8"
                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                <?php if (isset($profileErrors['phone'])): ?>
                    <p class="mt-1.5 text-sm text-danger"><?= htmlspecialchars($profileErrors['phone']) ?></p>
                <?php endif; ?>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-2 block font-medium text-content" for="address">Adresse</label>
                <input class="w-full rounded-lg border px-4 py-3 outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20 <?= isset($profileErrors['address']) ? 'border-danger' : 'border-primary/20' ?>"
                       type="text" id="address" name="address"
                       value="<?= htmlspecialchars($user['address'] ?? '') ?>">
                <?php if (isset($profileErrors['address'])): ?>
                    <p class="mt-1.5 text-sm text-danger"><?= htmlspecialchars($profileErrors['address']) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label class="mb-2 block font-medium text-content" for="zip_code">Postnummer</label>
                <input class="w-full rounded-lg border px-4 py-3 outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20 <?= isset($profileErrors['zip_code']) ? 'border-danger' : 'border-primary/20' ?>"
                       type="text" id="zip_code" name="zip_code" inputmode="numeric" maxlength="4"
                       value="<?= htmlspecialchars($user['zip_code'] ?? '') ?>">
                <?php if (isset($profileErrors['zip_code'])): ?>
                    <p class="mt-1.5 text-sm text-danger"><?= htmlspecialchars($profileErrors['zip_code']) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label class="mb-2 block font-medium text-content" for="city">By</label>
                <input class="w-full rounded-lg border px-4 py-3 outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20 <?= isset($profileErrors['city']) ? 'border-danger' : 'border-primary/20' ?>"
                       type="text" id="city" name="city"
                       value="<?= htmlspecialchars($user['city'] ?? '') ?>" required>
                <?php if (isset($profileErrors['city'])): ?>
                    <p class="mt-1.5 text-sm text-danger"><?= htmlspecialchars($profileErrors['city']) ?></p>
                <?php endif; ?>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-2 block font-medium text-content" for="iban">IBAN</label>
                <input class="w-full rounded-lg border px-4 py-3 uppercase outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20 <?= isset($profileErrors['iban']) ? 'border-danger' : 'border-primary/20' ?>"
                       type="text" id="iban" name="iban" autocomplete="off"
                       value="<?= htmlspecialchars($user['iban'] ?? '') ?>" placeholder="DK efterfulgt af 16 cifre">
                <?php if (isset($profileErrors['iban'])): ?>
                    <p class="mt-1.5 text-sm text-danger"><?= htmlspecialchars($profileErrors['iban']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <button class="mt-6 w-full rounded-lg bg-accent px-4 py-3 font-semibold text-white transition hover:bg-accent/80 sm:w-auto"
                type="submit">
            Gem oplysninger
        </button>
    </form>

    <form class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8" method="post">
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <h2 class="text-xl font-bold text-content">Skift adgangskode</h2>

        <?php if ($passwordMessage !== null): ?>
            <p class="mt-5 rounded-lg border px-4 py-3 text-sm <?= $passwordMessage['success']
                ? 'border-success/20 bg-success/10 text-success'
                : 'border-danger/20 bg-danger/10 text-danger' ?>">
                <?= htmlspecialchars($passwordMessage['message']) ?>
            </p>
        <?php endif; ?>

        <div class="mt-6 space-y-5">
            <div>
                <label class="mb-2 block font-medium text-content" for="current_password">Nuværende adgangskode</label>
                <input class="w-full rounded-lg border px-4 py-3 outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20 <?= isset($passwordErrors['current_password']) ? 'border-danger' : 'border-primary/20' ?>"
                       type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                <?php if (isset($passwordErrors['current_password'])): ?>
                    <p class="mt-1.5 text-sm text-danger"><?= htmlspecialchars($passwordErrors['current_password']) ?></p>
                <?php endif; ?>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-2 block font-medium text-content" for="new_password">Ny adgangskode</label>
                    <input class="w-full rounded-lg border px-4 py-3 outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20 <?= isset($passwordErrors['new_password']) ? 'border-danger' : 'border-primary/20' ?>"
                           type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" required>
                    <?php if (isset($passwordErrors['new_password'])): ?>
                        <p class="mt-1.5 text-sm text-danger"><?= htmlspecialchars($passwordErrors['new_password']) ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="mb-2 block font-medium text-content" for="confirm_password">Gentag ny adgangskode</label>
                    <input class="w-full rounded-lg border px-4 py-3 outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20 <?= isset($passwordErrors['confirm_password']) ? 'border-danger' : 'border-primary/20' ?>"
                           type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="8" required>
                    <?php if (isset($passwordErrors['confirm_password'])): ?>
                        <p class="mt-1.5 text-sm text-danger"><?= htmlspecialchars($passwordErrors['confirm_password']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <button class="mt-6 w-full rounded-lg bg-accent px-4 py-3 font-semibold text-white transition hover:bg-accent/80 sm:w-auto"
                type="submit">
            Skift adgangskode
        </button>
    </form>
</main>

<?php require __DIR__ . '/templates/footer.php'; ?>
