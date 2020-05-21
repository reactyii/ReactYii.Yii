@rem https://www.yiiframework.com/doc/guide/2.0/ru/db-migrations#reverting-migrations

@yii migrate --interactive=0

@rem повторить последние 1
@rem yii migrate/redo 1

@rem отменить последние 1
@rem yii migrate/down --interactive=0 1

@rem документация по миграциям
@rem https://www.yiiframework.com/doc/guide/2.0/ru/db-migrations
@rem https://www.yiiframework.com/doc/api/2.0/yii-db-schemabuildertrait
