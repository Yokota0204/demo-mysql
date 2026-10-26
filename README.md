# demo-mysql — MySQL 8.4 検証環境

MySQL 8.4 の挙動確認と、PHP(CLI)からのデータベース操作を試すための Docker 環境です。
SQL・機能の検証と、インデックス／設定変更によるパフォーマンス比較を想定しています。

## 構成

| サービス | イメージ | ホスト側ポート | 役割 |
| --- | --- | --- | --- |
| `db` | `mysql:8.4` | `127.0.0.1:13306` | 検証対象のMySQL |
| `php` | `php:8.4-cli`(自前ビルド) | なし | CLIスクリプト実行用。`pdo_mysql` / `mysqli` 入り |
| `phpmyadmin` | `phpmyadmin:5` | `127.0.0.1:8081` | ブラウザからテーブル閲覧・SQL実行 |

ポートは既存の `lesson-booking` プロジェクト（3306 / 8080 を使用中）と衝突しない番号にしてあります。
変更したいときは `.env` を編集してください。ポートは `127.0.0.1` にのみ公開しており、外部からは接続できません。

## はじめかた

```bash
make up      # 起動（初回はイメージのビルドも走る）
make check   # PHPからMySQLに繋がることを確認
```

- phpMyAdmin: http://localhost:8081
- 接続情報: `.env` に記載（既定は DB `demo` / ユーザー `demo` / パスワード `demopass`）

## よく使うコマンド

`make` または `make help` で一覧が出ます。

| コマンド | 内容 |
| --- | --- |
| `make up` / `make down` | 起動 / 停止（データは残る） |
| `make restart` | `my.cnf` を書き換えたあとの再起動 |
| `make reset` | データを全消しして初期状態から作り直す |
| `make ps` / `make logs` | 状態確認 / ログ追尾 |
| `make check` | PHPからの疎通確認とMySQLの主要設定表示 |
| `make mysql` | `mysql` クライアントに入る（root） |
| `make mysql-app` | `mysql` クライアントに入る（アプリ用ユーザー） |
| `make shell` | PHPコンテナのシェルに入る |
| `make seed` | 大量データ投入（既定 users 10万件 / orders 20万件） |
| `make run F=xxx.php` | `src/xxx.php` を実行 |
| `make slow` | スロークエリログを追う |
| `make slow-clear` | スロークエリログを空にする |
| `make noindex-on` / `noindex-off` | インデックス未使用クエリのログ記録を切り替え |
| `make general-on` / `general-off` | 全SQLを記録する一般クエリログを切り替え |
| `make vars` | MySQLの主要な設定値を一覧表示 |
| `make pma` | phpMyAdmin をブラウザで開く |

## ディレクトリ構成

```
.
├── compose.yaml
├── Makefile
├── .env                       # 接続情報とポート（.env.example をコピーしたもの）
├── docker/
│   ├── mysql/conf.d/my.cnf    # MySQLの設定。書き換えて make restart で反映
│   ├── mysql/initdb.d/        # 初回起動時だけ実行されるSQL
│   └── php/Dockerfile
├── src/                       # PHPスクリプト置き場（コンテナ内の /app）
│   ├── db.php                 # PDO接続の共通処理
│   ├── check.php              # 疎通確認
│   └── seed_bulk.php          # 大量データ投入
└── logs/mysql/                # スロークエリログ等（ホストから直接読める）
```

## PHPから使う

`src/` に置いたファイルはそのままコンテナの `/app` に見えます。新しく試すときは
`src/` にファイルを作って `make run F=ファイル名.php` で実行してください。

```php
<?php
require __DIR__ . '/db.php';

$pdo = db();   // アプリ用ユーザーで接続（db(true) なら root）

$stmt = $pdo->prepare('SELECT * FROM users WHERE prefecture = ? LIMIT 5');
$stmt->execute(['東京都']);
foreach ($stmt as $row) {
    echo $row['name'], "\n";
}

// 実行時間を測る
[$rows, $ms] = measure(fn () => $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
printf("%s 件 / %.1f ms\n", number_format((int) $rows), $ms);
```

`PDO::ATTR_EMULATE_PREPARES` を `false` にしてあるので、プリペアドステートメントは
エミュレーションではなくサーバ側で処理されます。

## GUIツールから繋ぐ

TablePlus / Sequel Ace / DBeaver などからは以下で接続できます。

```
Host: 127.0.0.1   Port: 13306
User: demo        Password: demopass    Database: demo
```

MySQL 8.4 の既定である `caching_sha2_password` を使っています。古いクライアントで
接続できない場合はクライアント側を更新してください（`mysql_native_password` は 8.4 で既定無効です）。

## パフォーマンス検証の進め方

```bash
make seed N=1000000        # 100万件入れる
make mysql
```

```sql
-- インデックスなしの状態
EXPLAIN ANALYZE SELECT * FROM users WHERE prefecture = '東京都' AND age > 40\G

-- インデックスを張って比較
ALTER TABLE users ADD INDEX idx_users_pref_age (prefecture, age);
EXPLAIN ANALYZE SELECT * FROM users WHERE prefecture = '東京都' AND age > 40\G

-- 元に戻す
ALTER TABLE users DROP INDEX idx_users_pref_age;
```

`users` テーブルは検証しやすいよう、`prefecture` と `age` にあえてインデックスを張っていません。

設定値の影響を見たいときは `docker/mysql/conf.d/my.cnf` を編集して `make restart` します。
`innodb_buffer_pool_size` や `innodb_flush_log_at_trx_commit` あたりが分かりやすい題材です。

スロークエリログは `logs/mysql/slow.log` に出ます（既定では 0.1 秒以上）。
全クエリを記録したい場合は `my.cnf` の `long_query_time` を `0` にしてください。

## 検証記録

| ドキュメント | 内容 |
| --- | --- |
| [docs/fulltext-vs-like.md](docs/fulltext-vs-like.md) | LIKE検索 と FULLTEXTインデックス(ngram) の性能比較 |

## データのリセット

```bash
make reset
```

volume ごと削除して作り直すため、`docker/mysql/initdb.d/` のSQLが再実行され、
テーブル定義とサンプルデータが初期状態に戻ります。
`initdb.d` のSQLは **volumeが空のときにしか実行されない** 点に注意してください。
スキーマを変えたら `make reset` が必要です。

## 困ったとき

- **ポートが衝突する** — `.env` の `MYSQL_PORT` / `PMA_PORT` を変更して `make up`。
- **`make up` 後すぐ `make check` が失敗する** — MySQLの初回起動には数十秒かかります。`make ps` で `healthy` になってから実行してください。
- **`initdb.d` のSQLを直したのに反映されない** — 初回起動時のみ実行されます。`make reset` してください。
- **PHPに拡張を追加したい** — `docker/php/Dockerfile` を編集して `make build` → `make up`。
