#!/bin/sh
# Выполняется один раз при инициализации пустого тома mysql_data.
# Для уже существующего тома тестовая БД создаётся командой `make test-db`.
set -eu

TEST_DATABASE="${MYSQL_DATABASE:-zaborprofil}_test"

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE DATABASE IF NOT EXISTS \`${TEST_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON \`${TEST_DATABASE}\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
SQL
