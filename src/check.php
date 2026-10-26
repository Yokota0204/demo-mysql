<?php
declare(strict_types=1);

require __DIR__ . '/db.php';

echo "=== 接続確認 ===\n";
echo 'PHP     : ' . PHP_VERSION . "\n";
echo 'drivers : ' . implode(', ', PDO::getAvailableDrivers()) . "\n";
echo 'mysqli  : ' . (extension_loaded('mysqli') ? '有効' : '無効') . "\n\n";

$pdo = db();

echo "=== MySQL ===\n";
foreach ([
    'version',
    'version_comment',
    'character_set_server',
    'collation_server',
    'time_zone',
    'system_time_zone',
    'innodb_buffer_pool_size',
    'long_query_time',
    'slow_query_log',
    'slow_query_log_file',
] as $var) {
    $row = $pdo->query("SHOW VARIABLES LIKE " . $pdo->quote($var))->fetch();
    printf("%-24s : %s\n", $var, $row['Value'] ?? '(なし)');
}

echo "\n=== 認証プラグイン ===\n";
// mysql.user は一般ユーザーからは見えないので root 接続で参照する
try {
    $rows = db(true)
        ->query("SELECT user, host, plugin FROM mysql.user WHERE user NOT LIKE 'mysql.%' ORDER BY user")
        ->fetchAll();
    foreach ($rows as $row) {
        printf("%-10s@%-12s : %s\n", $row['user'], $row['host'], $row['plugin']);
    }
} catch (Throwable $e) {
    echo '(参照できませんでした: ' . $e->getMessage() . ")\n";
}

echo "\n=== テーブル ===\n";
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
if (!$tables) {
    echo "(テーブルなし)\n";
}
foreach ($tables as $table) {
    $count = $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    printf("%-20s : %s 行\n", $table, number_format((int) $count));
}

echo "\n接続OK\n";
