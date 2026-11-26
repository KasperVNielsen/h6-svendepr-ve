<?php
$pageTitle = 'Min profil | Bil kassen';
$basePath = '../';

require_once __DIR__ . '/../backend/auth.php';

if (!isLoggedIn()) {
    header('Location: ./login');
    exit;
}

require_once __DIR__ . '/../backend/user.php';

$user = getUser((int) $_SESSION['user_id']);

if ($user === null) {
    header('Location: ./login');
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

$statement = $pdo->prepare("
    SELECT cl.id, cl.title, cl.price, cl.status, cl.is_active, cl.main_image,
           b.name AS brand_name, m.name AS model_name
    FROM car_listings cl
    JOIN brands b ON cl.brand_id = b.id
    JOIN models m ON cl.model_id = m.id
    WHERE cl.seller_user_id = ?
      AND cl.is_active = 1
    ORDER BY cl.created_at DESC
");
$statement->execute([(int) $_SESSION['user_id']]);
$userListings = $statement->fetchAll(PDO::FETCH_ASSOC);

$statement = $pdo->prepare("
    SELECT c.id, c.company_name, c.cvr, c.is_active, c.owner_user_id, ce.position
    FROM companies c
    LEFT JOIN company_employees ce
      ON ce.company_id = c.id
     AND ce.user_id = ?
     AND ce.is_active = 1
    WHERE c.owner_user_id = ? OR ce.user_id = ?
    ORDER BY (c.owner_user_id = ?) DESC
    LIMIT 1
");
$statement->execute([
    (int) $_SESSION['user_id'],
    (int) $_SESSION['user_id'],
    (int) $_SESSION['user_id'],
    (int) $_SESSION['user_id'],
]);
$userCompany = $statement->fetch(PDO::FETCH_ASSOC) ?: null;

require __DIR__ . '/templates/header.php';
?>

<main class="mx-auto w-full max-w-6xl flex-1 px-6 py-12">
    <?php if (isset($_GET['deleted'])): ?>
        <div class="mb-7 rounded-xl border border-success/20 bg-success/10 px-5 py-4 text-success">
            <p class="font-semibold">Bilannoncen er slettet.</p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['company_created'])): ?>
        <div class="mb-7 rounded-xl border border-success/20 bg-success/10 px-5 py-4 text-success">
            <p class="font-semibold">Firmaet er oprettet og tilknyttet din profil.</p>
        </div>
    <?php endif; ?>

    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-accent">Min konto</p>
            <h1 class="mt-1 text-3xl font-bold text-content">Min profil</h1>
        </div>

        <a class="inline-flex items-center justify-center rounded-lg bg-accent px-4 py-2.5 font-semibold text-white transition hover:bg-accent/80"
           href="rediger-profil">
            Rediger profil
        </a>
    </div>

    <section class="overflow-hidden rounded-xl border border-primary/10 bg-surface shadow-sm">
        <div class="bg-primary px-6 py-8 sm:px-8">
            <div class="flex flex-col items-center gap-5 text-center sm:flex-row sm:text-left">
                <?php if ($hasProfileImage): ?>
                    <img class="h-24 w-24 shrink-0 rounded-full border-4 border-white/20 object-cover"
                         src="images/profile-images/<?= rawurlencode($profileImageName) ?>"
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
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-content">Firma</h2>
                <?php if ($userCompany !== null): ?>
                    <p class="mt-2 text-content/65">
                        <?= htmlspecialchars($userCompany['company_name']) ?>
                        <span class="text-content/45">&middot; CVR <?= htmlspecialchars($userCompany['cvr']) ?></span>
                        <?php if (empty($userCompany['is_active'])): ?>
                            <span class="ml-2 font-semibold text-danger">Deaktiveret</span>
                        <?php elseif ((int) $userCompany['owner_user_id'] !== (int) $_SESSION['user_id']): ?>
                            <span class="ml-2 text-content/45">&middot; <?= htmlspecialchars((string) ($userCompany['position'] ?: 'Medarbejder')) ?></span>
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p class="mt-2 text-content/65">Opret en firmaprofil, hvis du vil sælge biler som virksomhed.</p>
                <?php endif; ?>
            </div>

            <?php if ($userCompany === null): ?>
                <a class="inline-flex items-center justify-center rounded-lg bg-accent px-4 py-2.5 font-semibold text-white transition hover:bg-accent/80"
                   href="opret-firma">Opret firma</a>
            <?php else: ?>
                <a class="inline-flex items-center justify-center rounded-lg border border-primary/15 px-4 py-2.5 font-semibold transition hover:border-accent hover:text-accent"
                   href="min-virksomhed">Gå til virksomheden</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="mt-8 rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-bold text-content">Mine bilannoncer</h2>
            <a class="text-sm font-semibold text-accent hover:underline" href="opret-annonce">Opret ny annonce</a>
        </div>

        <?php if (empty($userListings)): ?>
            <div class="mt-6 rounded-lg border border-dashed border-primary/20 px-6 py-10 text-center">
                <p class="font-medium text-content">Du har ingen bilannoncer endnu</p>
                <a class="mt-2 inline-block text-sm font-semibold text-accent hover:underline" href="opret-annonce">Opret din første annonce</a>
            </div>
        <?php else: ?>
            <div class="mt-6 divide-y divide-primary/10 overflow-hidden rounded-xl border border-primary/10">
                <?php foreach ($userListings as $listing): ?>
                    <article class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-bold text-content"><?= htmlspecialchars(trim($listing['brand_name'] . ' ' . $listing['model_name'])) ?></p>
                            <p class="mt-1 text-sm text-content/60"><?= htmlspecialchars($listing['title']) ?></p>
                            <p class="mt-2 text-sm font-semibold text-content"><?= number_format((float) $listing['price'], 0, ',', '.') ?> kr.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-primary/5 px-3 py-1 text-xs font-semibold capitalize text-content/65"><?= htmlspecialchars($listing['status']) ?></span>
                            <a class="rounded-lg border border-primary/15 px-4 py-2 text-sm font-semibold text-content transition hover:border-accent hover:text-accent"
                               href="rediger-annonce?id=<?= (int) $listing['id'] ?>">Rediger</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/templates/footer.php'; ?>
