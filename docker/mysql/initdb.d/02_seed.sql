-- 動作確認用の少量サンプル。大量データは `make seed` で投入する。

INSERT INTO users (name, email, age, prefecture) VALUES
  ('山田 太郎',   'taro@example.com',   34, '東京都'),
  ('佐藤 花子',   'hanako@example.com', 28, '大阪府'),
  ('鈴木 一郎',   'ichiro@example.com', 45, '北海道'),
  ('高橋 美咲',   'misaki@example.com', 22, '福岡県'),
  ('田中 健',     'ken@example.com',    51, '東京都');

INSERT INTO orders (user_id, amount, status, ordered_at) VALUES
  (1,  12800.00, 'paid',      '2026-01-15 10:23:00'),
  (1,   3200.50, 'shipped',   '2026-02-02 18:45:00'),
  (2,  45000.00, 'paid',      '2026-02-11 09:05:00'),
  (3,    980.00, 'cancelled', '2026-03-01 12:00:00'),
  (4,   7600.00, 'pending',   '2026-03-20 20:30:00'),
  (5, 128000.00, 'paid',      '2026-04-02 08:15:00');
