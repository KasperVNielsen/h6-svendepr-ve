<?php
$pageTitle = 'Min profil | Bil kassen';
$basePath = '../';

require_once __DIR__ . '/../backend/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../backend/user.php';

$user = getUser((int) $_SESSION['user_id']);

if ($user === null) {
    header('Location: login.php');
    exit;
}

$profileImageName = !empty($user['profile_image']) ? basename($user['profile_image']) : '';
$profileImageFilePath = __DIR__ . '/images/profile-images/' . $profileImageName;
$hasProfileImage = $profileImageName !== '' && is_file($profileImageFilePath);
$memberSince = !empty($user['created_at']) ? date('d.m.Y', strtotime($user['created_at'])) : 'Ikke angivet';
$phone = $user['phone'] ?? '';
$formattedPhone = preg_match('/^[0-9]{8}$/', $phone)
    ? implode(' ', str_split($phone, 2))
    : ($phone !== '' ? $phone : 'Ikke angivet');

require __DIR__ . '/templates/header.php';
?>

<main class="mx-auto w-full max-w-6xl flex-1 px-6 py-12">
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-accent">Min konto</p>
            <h1 class="mt-1 text-3xl font-bold text-content">Min profil</h1>
        </div>

        <a class="inline-flex items-center justify-center rounded-lg bg-accent px-4 py-2.5 font-semibold text-white transition hover:bg-accent/80"
           href="<?= $basePath ?>frontend/edit-profile.php">
            Rediger profil
        </a>
    </div>

    <section class="overflow-hidden rounded-xl border border-primary/10 bg-surface shadow-sm">
        <div class="bg-primary px-6 py-8 sm:px-8">
            <div class="flex flex-col items-center gap-5 text-center sm:flex-row sm:text-left">
                <?php if ($hasProfileImage): ?>
                    <img class="h-24 w-24 shrink-0 rounded-full border-4 border-white/20 object-cover"
                         src="<?= $basePath ?>frontend/images/profile-images/<?= rawurlencode($profileImageName) ?>"
                         alt="Profilbillede for <?= htmlspecialchars($user['first_name']) ?>">
                <?php else: ?>
                    <div class="flex h-24 w-24 shrink-0 items-center justify-center rounded-full border-4 border-white/20 bg-white/10 text-white">
                        <svg class="h-12 w-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0A17.9 17.9 0 0 1 12 21.75c-2.7 0-5.25-.59-7.5-1.65Z" />
                        </svg>
                    </div>
                <?php endif; ?>

                <div>
                    <h2 class="text-2xl font-bold text-white">
                        <?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?>
                    </h2>
                    <p class="mt-1 text-white/70"><?= htmlspecialchars($user['email']) ?></p>
                </div>
            </div>
        </div>

        <div class="grid gap-px bg-primary/10 sm:grid-cols-2 lg:grid-cols-3">
            <div class="bg-surface p-6">
                <p class="text-sm font-medium text-content/60">By</p>
                <p class="mt-1 font-semibold text-content"><?= htmlspecialchars($user['city'] ?: 'Ikke angivet') ?></p>
            </div>
            <div class="bg-surface p-6">
                <p class="text-sm font-medium text-content/60">Telefon</p>
                <p class="mt-1 font-semibold text-content"><?= htmlspecialchars($formattedPhone) ?></p>
            </div>
            <div class="bg-surface p-6">
                <p class="text-sm font-medium text-content/60">Medlem siden</p>
                <p class="mt-1 font-semibold text-content"><?= htmlspecialchars($memberSince) ?></p>
            </div>
        </div>
    </section>

    <section class="mt-8 rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
        <h2 class="text-xl font-bold text-content">Mine bilannoncer</h2>
        <div class="mt-6 rounded-lg border border-dashed border-primary/20 px-6 py-10 text-center">
            <p class="font-medium text-content">Dine bilannoncer bliver vist her</p>
            <p class="mt-1 text-sm text-content/60">Kommer senere.</p>
        </div>
    </section>
</main>

<?php require __DIR__ . '/templates/footer.php'; ?>
