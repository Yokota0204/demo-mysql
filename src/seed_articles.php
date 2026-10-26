<?php
declare(strict_types=1);

/**
 * 全文検索の検証用データを投入するスクリプト。
 *
 *   make seed-articles           # 10万件
 *   make seed-articles N=500000  # 件数指定
 *
 * 本文にはキーワードを「決まった出現率」で埋め込んである。
 * ヒット件数の多い/少ないで LIKE と FULLTEXT の差がどう変わるかを見るため。
 *
 *   量子暗号   : 約 0.1%  (10万件中およそ100件)  … ヒットが極端に少ない
 *   機械学習   : 約 5%    (          およそ5,000件) … そこそこヒットする
 *   最適化     : 約 30%   (         およそ30,000件) … 大量にヒットする
 */

require __DIR__ . '/db.php';

$total = (int) ($argv[1] ?? 100_000);
$chunk = 500;

if ($total < 1) {
    fwrite(STDERR, "件数は1以上を指定してください\n");
    exit(1);
}

// 埋め込むキーワードと出現率
$keywords = [
    '量子暗号' => 0.001,
    '機械学習' => 0.05,
    '最適化'   => 0.30,
];

// 本文を組み立てる語彙。上のキーワードと重複する語は入れない
$nouns = [
    '設計', '運用', '監視', '検証', '導入', '移行', '構築', '保守', '分析', '記録',
    '要件', '仕様', '手順', '基盤', '構成', '負荷', '応答', '帯域', '容量', '権限',
    '利用者', '管理者', '開発者', '担当部門', '現場', '拠点', '社内システム', '外部連携',
    '通信', '暗号', '認証', '署名', '鍵管理', '証明書', '監査', '統計', '指標', '報告書',
];
$adjectives = [
    '安定した', '大規模な', '既存の', '新しい', '複雑な', '簡潔な', '効率的な',
    '継続的な', '段階的な', '限定的な', '一般的な', '例外的な',
];
$verbs = [
    '検討する', '実施する', '見直す', '整理する', '評価する', '比較する',
    '共有する', '記録する', '改善する', '再検討する', '確認する', '調整する',
];
$templates = [
    '%s における %s の %s を %s。',
    '%2$s の観点から、%1$s の %3$s を %4$s 必要がある。',
    '%s の %s について、%s な結果を踏まえて %s。',
    '今回の %s では %s を対象とし、%s の %s を進めた。',
];

$pdo = db();

printf("articles を %s 件投入します\n\n", number_format($total));

[, $ms] = measure(function () use ($pdo, $total, $chunk, $keywords, $nouns, $adjectives, $verbs, $templates) {
    $done  = 0;
    $mtRnd = fn (array $a) => $a[array_rand($a)];

    while ($done < $total) {
        $size   = min($chunk, $total - $done);
        $values = [];
        $params = [];

        for ($i = 0; $i < $size; $i++) {
            $n = $done + $i;

            // 本文を8〜14文で組み立てる
            $sentences = [];
            $count     = random_int(8, 14);
            for ($s = 0; $s < $count; $s++) {
                $sentences[] = sprintf(
                    $mtRnd($templates),
                    $mtRnd($nouns),
                    $mtRnd($adjectives) . $mtRnd($nouns),
                    $mtRnd($nouns),
                    $mtRnd($verbs)
                );
            }

            // キーワードを出現率にしたがってランダムな位置に差し込む
            foreach ($keywords as $word => $rate) {
                if (mt_rand() / mt_getrandmax() < $rate) {
                    $pos = random_int(0, count($sentences) - 1);
                    $sentences[$pos] = $word . 'を用いた' . $sentences[$pos];
                }
            }

            $values[] = '(?, ?, ?)';
            $params[] = sprintf('記事%06d %sの%s', $n, $mtRnd($adjectives), $mtRnd($nouns));
            $params[] = implode('', $sentences);
            $params[] = date('Y-m-d H:i:s', time() - random_int(0, 365 * 24 * 3600));
        }

        $pdo->beginTransaction();
        $pdo->prepare(
            'INSERT INTO articles (title, body, created_at) VALUES ' . implode(',', $values)
        )->execute($params);
        $pdo->commit();

        $done += $size;
        printf("\r  %s / %s", number_format($done), number_format($total));
    }
});

printf("\n\n完了: %.1f 秒\n", $ms / 1000);

// 実際に何件ヒットするようになったか確認する
echo "\n=== 埋め込んだキーワードの実際の件数 ===\n";
foreach (array_keys($keywords) as $word) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM articles WHERE body LIKE ?');
    $stmt->execute(['%' . $word . '%']);
    printf("%-10s : %s 件\n", $word, number_format((int) $stmt->fetchColumn()));
}
