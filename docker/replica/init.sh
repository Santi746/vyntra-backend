#!/bin/bash
set -e

until pg_isready -h $PRIMARY_HOST -p $PRIMARY_PORT; do
  echo "Esperando al primary..."
  sleep 2
done

rm -rf /var/lib/postgresql/data/*

pg_basebackup -h $PRIMARY_HOST \
              -U $REPLICATION_USER \
              -D /var/lib/postgresql/data \
              -Fp \
              -Xs \
              -P -v \
              -R

echo "Replica lista!"
