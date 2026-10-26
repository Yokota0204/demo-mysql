<?php
declare(strict_types=1);

/**
 * MySQLへのPDO接続を返す共通処理。
 * 接続情報は compose.yaml の environment 経由で渡ってくる。
 *
 * @param bool $asRoot true にすると root で繋ぐ(performance_schema の参照などに使う)
 */
function db(bool $asRoot = false): PDO
{
    static $conns = [];
    $key = $asRoot ? 'root' : 'app';

    if (isset($conns[$key])) {
        return $conns[$key];
    }

    $host = getenv('DB_HOST') ?: 'db';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_DATABASE') ?: 'demo';

    $user = $asRoot ? 'root' : (getenv('DB_USERNAME') ?: 'demo');
    $pass = $asRoot ? (getenv('DB_ROOT_PASSWORD') ?: '') : (getenv('DB_PASSWORD') ?: '');

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

    return $conns[$key] = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // 本物のプリペアドステートメントを使う(エミュレーションを切る)
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

/**
 * 経過時間を測ってミリ秒で返す。パフォーマンス検証用。
 */
function measure(callable $fn): array
{
    $start  = hrtime(true);
    $result = $fn();
    $ms     = (hrtime(true) - $start) / 1_000_000;

    return [$result, $ms];
}
