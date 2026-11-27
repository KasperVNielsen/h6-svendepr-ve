<?php
$pageTitle = 'Min saldo | Bil kassen';
$basePath = '../';

require_once __DIR__ . '/../backend/transactions.php';
require_once __DIR__ . '/templates/transaction-listing.php';

if (!isLoggedIn()) {
    header('Location: ./login');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;
    $action = (string) ($_POST['action'] ?? '');
    $result = '';
    $success = false;

    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $result = 'Formularen er udløbet. Prøv igen.';
    } elseif ($action === 'add_balance') {
        $result = addBalance();
        $success = str_contains($result, 'kr. indbetalt!');
    } elseif ($action === 'withdraw') {
        $result = withDraw();
        $success = str_contains($result, 'kr. udbetalt!');
    } else {
        $result = 'Ukendt handling.';
    }

    $_SESSION['transaction_flash'] = ['success' => $success, 'text' => $result];
    header('Location: ./saldo');
    exit;
}

$flash = $_SESSION['transaction_flash'] ?? null;
unset($_SESSION['transaction_flash']);

$statement = $pdo->prepare('SELECT balance FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
$statement->execute([$userId]);
$balance = (float) ($statement->fetchColumn() ?: 0);

// company_id IS NULL keeps private transactions separate from company activity.
$statement = $pdo->prepare("
    SELECT id, type, amount, fee, method, status, description, created_at
    FROM transactions
    WHERE user_id = ? AND company_id IS NULL
    ORDER BY created_at DESC, id DESC
    LIMIT 50
");
$statement->execute([$userId]);
$transactions = $statement->fetchAll(PDO::FETCH_ASSOC);
$transactionCarNames = loadTransactionCarNames($pdo, $transactions);

require __DIR__ . '/templates/header.php';
?>

<main class="mx-auto w-full max-w-5xl flex-1 px-4 py-10 sm:px-6 sm:py-12">
    <div class="mb-8">
        <p class="text-sm font-semibold uppercase tracking-wider text-accent">Privat konto</p>
        <h1 class="mt-1 text-3xl font-bold text-content">Min saldo</h1>
        <p class="mt-2 text-content/65">Her vises kun din private saldo og dine private transaktioner.</p>
    </div>

    <?php if ($flash !== null): ?>
        <div class="mb-7 rounded-xl border px-5 py-4 <?= $flash['success'] ? 'border-success/20 bg-success/10 text-success' : 'border-danger/20 bg-danger/10 text-danger' ?>">
            <p class="font-semibold"><?= htmlspecialchars($flash['text']) ?></p>
        </div>
    <?php endif; ?>

    <section class="rounded-xl bg-primary p-6 text-white shadow-sm sm:p-8">
        <p class="text-sm font-medium text-white/65">Tilgængelig privat saldo</p>
        <p class="mt-2 text-4xl font-bold"><?= number_format($balance, 2, ',', '.') ?> kr.</p>
    </section>

    <div class="mt-8 grid gap-8 md:grid-cols-2">
        <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
            <h2 class="text-xl font-bold">Indbetal</h2>
            <p class="mt-1 text-sm text-content/60">Beløbet indsættes på din private saldo.</p>
            <form class="mt-6 space-y-4" method="post">
                <input type="hidden" name="action" value="add_balance">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div>
                    <label class="mb-2 block font-medium" for="deposit_amount">Beløb</label>
                    <input class="w-full rounded-lg border border-primary/20 px-4 py-3" type="number" id="deposit_amount" name="amount" min="0.01" step="0.01" required>
                </div>
                <button class="rounded-lg bg-accent px-5 py-3 font-semibold text-white" type="submit">Indbetal beløb</button>
            </form>
        </section>

        <section class="rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
            <h2 class="text-xl font-bold">Udbetal</h2>
            <p class="mt-1 text-sm text-content/60">Beløbet trækkes fra din private saldo.</p>
            <form class="mt-6 space-y-4" method="post">
                <input type="hidden" name="action" value="withdraw">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div>
                    <label class="mb-2 block font-medium" for="withdraw_amount">Beløb</label>
                    <input class="w-full rounded-lg border border-primary/20 px-4 py-3" type="number" id="withdraw_amount" name="amount" min="0.01" max="<?= htmlspecialchars((string) $balance) ?>" step="0.01" required>
                </div>
                <button class="rounded-lg border border-accent px-5 py-3 font-semibold text-accent" type="submit">Udbetal beløb</button>
            </form>
        </section>
    </div>

    <section class="mt-8 rounded-xl border border-primary/10 bg-surface p-6 shadow-sm sm:p-8">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-bold">Seneste transaktioner</h2>
            <span class="text-sm text-content/55">Maks. 50</span>
        </div>

        <?php if (empty($transactions)): ?>
            <p class="mt-6 rounded-lg border border-dashed border-primary/20 p-8 text-center text-content/60">Du har ingen private transaktioner endnu.</p>
        <?php else: ?>
            <div class="mt-6 divide-y divide-primary/10">
                <?php foreach ($transactions as $transaction): ?>
                    <?php
                    $isOutgoing = in_array($transaction['type'], ['køb', 'udbetaling'], true);
                    $displayAmount = (float) $transaction['amount'];
                    $transactionListingId = transactionListingId((string) $transaction['description']);
                    $transactionCarName = $transactionListingId !== null
                        ? ($transactionCarNames[$transactionListingId] ?? null)
                        : null;
                    $transactionLinkText = $transactionCarName !== null
                        ? ucfirst((string) $transaction['type']) . ' af bil – ' . $transactionCarName
                        : preg_replace('/\s*\(kommission.*$/iu', '', (string) $transaction['description']);
                    if ($transaction['type'] === 'salg') {
                        $displayAmount -= (float) $transaction['fee'];
                    }
                    ?>
                    <article class="flex flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-semibold capitalize"><?= htmlspecialchars($transaction['type']) ?></p>
                            <?php if ($transactionListingId !== null): ?>
                                <a class="mt-1 inline-block text-sm font-semibold text-accent hover:underline" href="bil?id=<?= $transactionListingId ?>">
                                    <?= htmlspecialchars($transactionLinkText) ?>
                                </a>
                            <?php else: ?>
                                <p class="mt-1 text-sm text-content/60"><?= htmlspecialchars((string) $transaction['description']) ?></p>
                            <?php endif; ?>
                            <p class="mt-1 text-xs text-content/45"><?= date('d.m.Y H:i', strtotime($transaction['created_at'])) ?></p>
                        </div>
                        <div class="text-left sm:text-right">
                            <p class="font-bold <?= $isOutgoing ? 'text-danger' : 'text-success' ?>">
                                <?= $isOutgoing ? '−' : '+' ?><?= number_format($displayAmount, 2, ',', '.') ?> kr.
                            </p>
                            <?php if ((float) $transaction['fee'] > 0): ?>
                                <p class="mt-1 text-xs text-content/50">Gebyr: <?= number_format((float) $transaction['fee'], 2, ',', '.') ?> kr.</p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/templates/footer.php'; ?>
