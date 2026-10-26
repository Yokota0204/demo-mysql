-- 初回起動時(mysql-data volume が空のとき)だけ実行される
-- 作り直したいときは `make reset`

CREATE TABLE IF NOT EXISTS users (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(100)    NOT NULL,
  email      VARCHAR(255)    NOT NULL,
  age        TINYINT UNSIGNED     NULL,
  prefecture VARCHAR(20)          NULL,
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_users_email (email)
  -- 検証用にあえて prefecture / age にはインデックスを張っていない。
  -- ALTER TABLE users ADD INDEX idx_users_prefecture (prefecture);
  -- のように後から張って EXPLAIN の変化を比較する。
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NOT NULL,
  amount     DECIMAL(10,2)   NOT NULL,
  status     ENUM('pending','paid','shipped','cancelled') NOT NULL DEFAULT 'pending',
  ordered_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_orders_user_id (user_id),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
