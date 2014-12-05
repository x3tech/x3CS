#!/bin/bash
exec 2>&1

. /env

export MYSQL_DB="${DB_ENV_MYSQL_DATABASE}"
export MYSQL_ADDR="${DB_PORT_3306_TCP_ADDR}"
export MYSQL_PORT="${DB_PORT_3306_TCP_PORT}"
export MYSQL_USER="${DB_ENV_MYSQL_USER}"
export MYSQL_PASSWORD="${DB_ENV_MYSQL_PASSWORD}"

exec chpst -u www-data:www-data /usr/bin/hhvm -m server -c /etc/hhvm/hhvm.hdf
