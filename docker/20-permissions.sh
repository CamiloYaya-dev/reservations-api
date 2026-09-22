#!/bin/sh
set -eu
# The official image initially grants privileges on MYSQL_DATABASE to MYSQL_USER.
# Replace them with only the permissions needed by the API and product CLI.
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=socket -uroot <<'SQL'
REVOKE ALL PRIVILEGES ON inventory.* FROM 'reservation_app'@'%';
GRANT SELECT, INSERT, UPDATE ON inventory.products TO 'reservation_app'@'%';
GRANT SELECT, INSERT ON inventory.reservations TO 'reservation_app'@'%';
SQL
