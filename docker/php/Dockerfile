FROM php:8.4-cli

# MySQL接続に必要な拡張。PDO派/mysqli派どちらでも試せるよう両方入れる
RUN docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli

# 検証時にDBコンテナへ入らずクエリを流せるよう mysql クライアントを入れる
RUN apt-get update \
 && apt-get install -y --no-install-recommends default-mysql-client \
 && rm -rf /var/lib/apt/lists/*

# 大量データ投入や計測スクリプトが途中で止まらない設定
RUN { \
      echo "memory_limit = 512M"; \
      echo "max_execution_time = 0"; \
      echo "error_reporting = E_ALL"; \
      echo "display_errors = On"; \
      echo "date.timezone = Asia/Tokyo"; \
    } > /usr/local/etc/php/conf.d/zz-research.ini

WORKDIR /app
