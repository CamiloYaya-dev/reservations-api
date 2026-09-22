#!/bin/sh

# The official MySQL entrypoint sources this file in its own shell.
# Do not enable `set -u` here because it would affect the parent entrypoint.

# Replace the privileges initially granted on MYSQL_DATABASE with only
# the permissions required by the API and product CLI.
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=socket -uroot <<'SQL'
REVOKE ALL PRIVILEGES ON inventory.* FROM 'reservation_app'@'%';
GRANT SELECT, INSERT, UPDATE ON inventory.products TO 'reservation_app'@'%';
GRANT SELECT, INSERT ON inventory.reservations TO 'reservation_app'@'%';
SQL