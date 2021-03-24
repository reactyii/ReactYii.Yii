<?php
namespace app\models;

use Yii;
use yii\caching\TagDependency;

abstract class BaseModel extends \yii\db\ActiveRecord
{

    protected static $getAllOrderBy = [
        'priority' => SORT_ASC,
        'id' => SORT_ASC
    ];


    protected static function getAllWhere(&$site)
    {
        return $site != null ? [
            'site_id' => $site['id'],
            'is_blocked' => 0
        ] : ['is_blocked' => 0];
    }

    protected static function getCacheBaseKey()
    {
        return trim(static::tableName(), '%{}'); // вот так короче будет для наших целей
    }

    public static function getAll(&$site)
    {
        $key = implode('-', [
            $site != null ? $site['id'] : '',
            static::getCacheBaseKey(),
            __FUNCTION__
        ]);
        Yii::info("getAll. key=" . $key, __METHOD__);

        return Yii::$app->cache->getOrSet($key, function () use ($key, $site) {
            Yii::info("getAll. get from DB key=" . $key, __METHOD__);

            return self::find()->where(static::getAllWhere($site))
                ->orderBy(static::$getAllOrderBy)
                ->asArray()
                ->all();
        }, null, new TagDependency([
            'tags' => [
                'site-' . $site['id'],
                static::getCacheBaseKey() . '-' . $site['id']
            ]
        ]));
    }

    public static function getItemByPage(&$site, $page, $tags=[])
    {
        return static::getItemByField($site, ['page=:page'], [':page' => $page], 'page=' . $page, $tags);
    }

    public static function getItemById(&$site, $id, $tags=[])
    {
        return static::getItemByField($site, ['id=:id'], [':id' => $id], 'id=' . $id, $tags);
    }

    public static function getItemByField(&$site, $where, $whereParams, $uniqueWhereKey, $tags=[])
    {
        //$_key_where = [];
        /*$_where = ['site_id' => $site['id']]; // это нужно для всех сущностей (кроме самого сайта, н осайт мы ресолвим по своей функцией)
        foreach($where as $k=>$v)
        {
            //$_key_where[] = $k . '=' . urlencode(is_array($v) ? '' : $v);
            $_where[$k] = $v;
        }*/
        $key = implode('-', [
            $site != null ? $site['id'] : '',
            //implode(',', $_key_where),
            $uniqueWhereKey,
            static::getCacheBaseKey(),
            __FUNCTION__
        ]);

        return Yii::$app->cache->getOrSet($key, function () use ($key, $site, $where, $whereParams) {
            Yii::info("getItem. get from DB key=" . $key, __METHOD__);
            $query = static::find()->where(['site_id' => $site['id']]);
            foreach ($where as $w) {
                $query = $query->andWhere($w);
            }

            return $query->addParams($whereParams)//->where($_where)
            ->asArray()
            ->one();
        }, null, new TagDependency([
            'tags' => [
                'site-' . $site['id'],
                static::getCacheBaseKey() . '-' . $site['id'] // чтоб чистить когда чистим все кеши для данной таблицы (у списка точно такой же)
                // а вот нужен ли ключ для отдельной записи пока хз, если понадобится мы передадим его в $tags
            ] + $tags
        ]));
    }
}