#!/bin/sh

# The official MySQL entrypoint sources this file in its own shell.
# Do not enable `set -u` here because it would affect the parent entrypoint.

# Test-only visibility to prove both HTTP requests actually wait
# on InnoDB locks.
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=socket -uroot <<'SQL'
GRANT SELECT ON performance_schema.data_locks TO 'reservation_test'@'%';
GRANT SELECT ON performance_schema.data_lock_waits TO 'reservation_test'@'%';
SQL