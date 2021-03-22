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

    public static function getAll(&$site)
    {
        $key = implode('-', [
            $site != null ? $site['id'] : '',
            __CLASS__,
            __FUNCTION__
        ]);
        Yii::info("getAll. key=" . $key, __METHOD__);

        return Yii::$app->cache->getOrSet($key, function () use ($key, $site) {
            Yii::info("getAll. get from DB key=" . $key, __METHOD__);

            return self::find()->where(self::getAllWhere($site))
                ->orderBy(self::getAllOrderBy)
                ->asArray()
                ->all();
        }, null, new TagDependency([
            'tags' => [
                'site-' . $site['id'],
                self::tableName() . '-' . $site['id']
            ]
        ]));
    }

    public static function getItemByField(&$site, $where, $tags)
    {
        $key = implode('-', [
            $site != null ? $site['id'] : '',
            __CLASS__,
            __FUNCTION__
        ]);

    }
}