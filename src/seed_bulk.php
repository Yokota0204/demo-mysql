<?php
declare(strict_types=1);

/**
 * パフォーマンス検証用の大量データ投入スクリプト。
 *
 *   make seed                    # users 10万件 / orders 20万件
 *   make seed N=1000000          # 件数を指定
 *   docker compose exec php php seed_bulk.php 100000 3
 *                                # users件数 / 1ユーザーあたりの注文数
 */

require __DIR__ . '/db.php';

$userCount      = (int) ($argv[1] ?? 100_000);
$ordersPerUser  = (int) ($argv[2] ?? 2);
$chunk          = 1_000; // 1回のINSERTでまとめる行数

if ($userCount < 1) {
    fwrite(STDERR, "件数は1以上を指定してください\n");
    exit(1);
}

$pdo = db();

$prefectures = ['北海道', '東京都', '大阪府', '愛知県', '福岡県', '京都府', '宮城県', '広島県', '沖縄県', '新潟県'];
$statuses    = ['pending', 'paid', 'shipped', 'cancelled'];

printf("users %s 件 / orders %s 件を投入します\n\n",
    number_format($userCount),
    number_format($userCount * $ordersPerUser)
);

// --- users ---
$startId = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM users')->fetchColumn();
$suffix  = bin2hex(random_bytes(3)); // 再実行してもメールアドレスが衝突しないようにする

[, $usersMs] = measure(function () use ($pdo, $userCount, $chunk, $prefectures, $suffix) {
    $done = 0;
    while ($done < $userCount) {
        $size = min($chunk, $userCount - $done);

        $values = [];
        $params = [];
        for ($i = 0; $i < $size; $i++) {
            $n        = $done + $i;
            $values[] = '(?, ?, ?, ?, ?)';
            $params[] = 'ユーザー' . $n;
            $params[] = "bulk{$suffix}_{$n}@example.com";
            $params[] = random_int(18, 79);
            $params[] = $prefectures[array_rand($prefectures)];
            $params[] = date('Y-m-d H:i:s', time() - random_int(0, 365 * 24 * 3600));
        }

        $pdo->beginTransaction();
        $pdo->prepare(
            'INSERT INTO users (name, email, age, prefecture, created_at) VALUES ' . implode(',', $values)
        )->execute($params);
        $pdo->commit();

        $done += $size;
        printf("\r  users  : %s / %s", number_format($done), number_format($userCount));
    }
});
printf("  (%.1f 秒)\n", $usersMs / 1000);

// --- orders ---
$minUserId = $startId + 1;
$maxUserId = $startId + $userCount;
$orderTotal = $userCount * $ordersPerUser;

[, $ordersMs] = measure(function () use ($pdo, $orderTotal, $chunk, $statuses, $minUserId, $maxUserId) {
    $done = 0;
    while ($done < $orderTotal) {
        $size = min($chunk, $orderTotal - $done);

        $values = [];
        $params = [];
        for ($i = 0; $i < $size; $i++) {
            $values[] = '(?, ?, ?, ?)';
            $params[] = random_int($minUserId, $maxUserId);
            $params[] = random_int(100, 300_000) / 1.0;
            $params[] = $statuses[array_rand($statuses)];
            $params[] = date('Y-m-d H:i:s', time() - random_int(0, 365 * 24 * 3600));
        }

        $pdo->beginTransaction();
        $pdo->prepare(
            'INSERT INTO orders (user_id, amount, status, ordered_at) VALUES ' . implode(',', $values)
        )->execute($params);
        $pdo->commit();

        $done += $size;
        printf("\r  orders : %s / %s", number_format($done), number_format($orderTotal));
    }
});
printf("  (%.1f 秒)\n", $ordersMs / 1000);

printf("\n完了: 合計 %.1f 秒\n", ($usersMs + $ordersMs) / 1000);
echo "\nEXPLAIN を試す例:\n";
echo "  make mysql\n";
echo "  mysql> EXPLAIN ANALYZE SELECT * FROM users WHERE prefecture = '東京都' AND age > 40\\G\n";
echo "  mysql> ALTER TABLE users ADD INDEX idx_users_pref_age (prefecture, age);\n";
echo "  mysql> EXPLAIN ANALYZE SELECT * FROM users WHERE prefecture = '東京都' AND age > 40\\G\n";
