# =============================================================
# よく使う操作をまとめたもの。`make` または `make help` で一覧表示。
# =============================================================

-include .env
export

DC := docker compose

.DEFAULT_GOAL := help

.PHONY: help up down restart build reset ps logs logs-db check seed seed-articles run mysql mysql-app shell shell-db slow slow-clear general-on general-off noindex-on noindex-off pma vars

help: ## このヘルプを表示
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'
	@echo ""
	@echo "  MySQL     : 127.0.0.1:$(MYSQL_PORT)  (user: $(MYSQL_USER) / db: $(MYSQL_DATABASE))"
	@echo "  phpMyAdmin: http://localhost:$(PMA_PORT)"

up: ## 起動する(初回はビルドも実行)
	$(DC) up -d --build
	@echo ""
	@echo "起動しました。phpMyAdmin: http://localhost:$(PMA_PORT)"

down: ## 停止する(データは残る)
	$(DC) down

restart: ## 再起動する(my.cnf を書き換えたあとに使う)
	$(DC) restart db
	@echo "db を再起動しました"

build: ## PHPイメージを再ビルドする
	$(DC) build --no-cache php

reset: ## データを全消しして作り直す(initdb.d が再実行される)
	$(DC) down -v
	rm -f logs/mysql/*.log
	$(DC) up -d --build
	@echo "初期状態で作り直しました"

ps: ## コンテナの状態を見る
	$(DC) ps

logs: ## 全コンテナのログを追う
	$(DC) logs -f

logs-db: ## MySQLのログを追う
	$(DC) logs -f db

check: ## PHPからMySQLに繋がるか確認する
	$(DC) exec php php check.php

seed: ## 大量データを投入する (例: make seed N=1000000 M=3)
	$(DC) exec php php seed_bulk.php $(or $(N),100000) $(or $(M),2)

seed-articles: ## 全文検索の検証用データを投入する (例: make seed-articles N=500000)
	$(DC) exec php php seed_articles.php $(or $(N),100000)

run: ## src配下のスクリプトを実行する (例: make run F=my_test.php)
	@test -n "$(F)" || (echo "使い方: make run F=ファイル名.php" && exit 1)
	$(DC) exec php php $(F)

mysql: ## mysqlクライアントに入る(root)
	$(DC) exec -e MYSQL_PWD=$(MYSQL_ROOT_PASSWORD) -e LANG=C.UTF-8 -e LC_ALL=C.UTF-8 db mysql -uroot $(MYSQL_DATABASE)

mysql-app: ## mysqlクライアントに入る(アプリ用ユーザー)
	$(DC) exec -e MYSQL_PWD=$(MYSQL_PASSWORD) -e LANG=C.UTF-8 -e LC_ALL=C.UTF-8 db mysql -u$(MYSQL_USER) $(MYSQL_DATABASE)

shell: ## PHPコンテナのシェルに入る
	$(DC) exec -e LANG=C.UTF-8 -e LC_ALL=C.UTF-8 php bash

shell-db: ## MySQLコンテナのシェルに入る
	$(DC) exec -e LANG=C.UTF-8 -e LC_ALL=C.UTF-8 db bash

slow: ## スロークエリログを追う
	@touch logs/mysql/slow.log
	tail -f logs/mysql/slow.log

slow-clear: ## スロークエリログを空にする
	$(DC) exec -e MYSQL_PWD=$(MYSQL_ROOT_PASSWORD) db mysql -uroot -e "SET GLOBAL slow_query_log = OFF"
	: > logs/mysql/slow.log
	$(DC) exec -e MYSQL_PWD=$(MYSQL_ROOT_PASSWORD) db mysql -uroot -e "SET GLOBAL slow_query_log = ON"
	@echo "slow.log を空にしました"

general-on: ## 一般クエリログ(全SQL記録)を一時的に有効にする
	$(DC) exec -e MYSQL_PWD=$(MYSQL_ROOT_PASSWORD) db mysql -uroot -e "SET GLOBAL general_log = ON"
	@echo "有効化しました: tail -f logs/mysql/general.log"

noindex-on: ## インデックス未使用クエリのログ記録を有効にする
	$(DC) exec -e MYSQL_PWD=$(MYSQL_ROOT_PASSWORD) db mysql -uroot -e "SET GLOBAL log_queries_not_using_indexes = ON"
	@echo "有効化しました: make slow で確認"

noindex-off: ## インデックス未使用クエリのログ記録を無効にする
	$(DC) exec -e MYSQL_PWD=$(MYSQL_ROOT_PASSWORD) db mysql -uroot -e "SET GLOBAL log_queries_not_using_indexes = OFF"
	@echo "無効化しました"

general-off: ## 一般クエリログを無効にする
	$(DC) exec -e MYSQL_PWD=$(MYSQL_ROOT_PASSWORD) db mysql -uroot -e "SET GLOBAL general_log = OFF"
	@echo "無効化しました"

vars: ## 主要な設定値を一覧表示する
	$(DC) exec -e MYSQL_PWD=$(MYSQL_ROOT_PASSWORD) db mysql -uroot -e "SHOW VARIABLES WHERE Variable_name IN ('version','character_set_server','collation_server','innodb_buffer_pool_size','long_query_time','slow_query_log','general_log','max_connections','sql_mode','time_zone')"

pma: ## phpMyAdmin をブラウザで開く
	open http://localhost:$(PMA_PORT)
