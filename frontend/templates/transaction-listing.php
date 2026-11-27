<?php

function transactionListingId(string $description): ?int
{
    if (!preg_match('/bil\s+#(\d+)/iu', $description, $matches)) {
        return null;
    }

    $listingId = (int) $matches[1];
    return $listingId > 0 ? $listingId : null;
}

function loadTransactionCarNames(PDO $pdo, array $transactions): array
{
    $listingIds = [];

    foreach ($transactions as $transaction) {
        $listingId = transactionListingId((string) ($transaction['description'] ?? ''));
        if ($listingId !== null) {
            $listingIds[$listingId] = $listingId;
        }
    }

    if ($listingIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($listingIds), '?'));
    $statement = $pdo->prepare("
        SELECT cl.id, b.name AS brand_name, m.name AS model_name
        FROM car_listings cl
        JOIN brands b ON b.id = cl.brand_id
        JOIN models m ON m.id = cl.model_id
        WHERE cl.id IN ($placeholders)
    ");
    $statement->execute(array_values($listingIds));

    $carNames = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $car) {
        $carNames[(int) $car['id']] = trim($car['brand_name'] . ' ' . $car['model_name']);
    }

    return $carNames;
}
