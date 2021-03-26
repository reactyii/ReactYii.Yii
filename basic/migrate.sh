#!/bin/bash

# git update-index --chmod=+x migrate.sh

#https://www.yiiframework.com/doc/guide/2.0/ru/db-migrations#reverting-migrations

yii migrate --interactive=0

#повторить последние 1
#yii migrate/redo 1

#отменить последние 1
#yii migrate/down 1

#документация по миграциям
#https://www.yiiframework.com/doc/guide/2.0/ru/db-migrations
#https://www.yiiframework.com/doc/api/2.0/yii-db-schemabuildertrait
