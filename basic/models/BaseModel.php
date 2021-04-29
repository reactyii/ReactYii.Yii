<?php
namespace app\models;

use Yii;
use yii\caching\TagDependency;
use phpDocumentor\Reflection\Types\Static_;

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

    public static function getAllForSelect(&$site, $parent = null, $fNameForValue = 'id', $fNameForTitle = 'name', $parentName = 'parent_id')
    {
        $key = implode('-', [
            $site != null ? $site['id'] : '',
            $parent != null ? $parent : '',
            $fNameForValue, $fNameForTitle, $parentName,
            static::getCacheBaseKey(),
            __FUNCTION__
        ]);
        return Yii::$app->cache->getOrSet($key, function () use ($key, $site, $parent, $fNameForValue, $fNameForTitle, $parentName) {
            Yii::info("getAllForSelect. get from DB key=" . $key, __METHOD__);
            $where = $site != null ? [
                'site_id' => $site['id'],
            ] : [];
            if ($parentName != null) $where[$parentName] = $parent;

            $list = self::find()
                ->select(['id' => $fNameForValue, 'content' => $fNameForTitle])
                ->where($where)
                ->orderBy(static::$getAllOrderBy)
                ->asArray()
                ->all();

            foreach ($list as $k => $v) {
                $list[$k]['path'] = $v[$fNameForValue];
                $list[$k]['type'] = 'option';
                if ($parentName != null) {
                    $list[$k]['childs'] = static::getAllForSelect($site, $v[$fNameForValue], $fNameForValue, $fNameForTitle, $parentName);
                }
            }

            return $list;
        }, null, new TagDependency([
            'tags' => [
                'site-' . $site['id'],
                static::getCacheBaseKey() . '-' . $site['id']
            ]
        ]));
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

    public static function getItemByPage(&$site, $page, $tags = [])
    {
        return static::getItemByField($site, ['page=:page'], [':page' => $page], 'page=' . $page, $tags);
    }

    public static function getItemById(&$site, $id, $tags = [])
    {
        return static::getItemByField($site, ['id=:id'], [':id' => $id], 'id=' . $id, $tags);
    }

    public static function getItemByField(&$site, $where, $whereParams, $uniqueWhereKey, $tags = [])
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

    /**
     * Преобразуем список в ассоциативный массив по ключу (обычно по id)
     * Новый массив возвращаем. Исходный не меняем (передаем по ссылке для оптимизации)!
     *
     */
    public static function listToHash(&$list, $keyName = 'id')
    {
        $res = [];
        array_map(function ($v) use (&$res, $keyName) {
            $res[$v[$keyName]] = $v;
        }, $list);
        /*foreach ($list as $v)
        {
            $res[$v[$keyName]] = $v;
        }*/
        return $res;
    }

    /**
     * Преобразуем ассоциативный массив в дерево.
     *
     */
    public static function hashToTree(&$list, $idName = 'id', $childsName = 'childs', $parentName = 'parent_id')
    {
        $tree = [];
        foreach ($list as $k => $v) // не стоит делать &$v как то оно не очень предсказуемо работает (см пример с foreach https://www.php.net/manual/ru/language.references.php)
        {
            if ($v[$parentName]) // парент есть значит вставляем его в чилдсы паренту
            {
                //Yii::info("-----" . var_export($v[$parentName], true), __METHOD__);
                if (isset($list[$v[$parentName]])) {
                    if (!isset($list[$v[$parentName]][$childsName])) $list[$v[$parentName]][$childsName] = [];
                    $list[$v[$parentName]][$childsName][] = &$list[$k]; // NB! &$ альтернатива делать рекурсию
                } else // у нас нарушена целостность данных в БД
                {
                    Yii::error('Нарушена целостность данных в БД. Таблица: ' . static::tableName() . ' Итем с id=' . $v[$idName] . ' отсутствует парент с ид: ' . $v[$parentName], __METHOD__);
                }
            }
        }
        //Yii::info("====" . var_export($list, true), __METHOD__);
        foreach ($list as $v) {
            if (!$v[$parentName]) // тока корневые узлы
            {
                $tree[] = $v;
            }
        }
        return $tree;
    }

    /**
     * Десериализуем жсоны в списке
     *
     */
    public static function json_decode_list(&$list, $fields, $remove_source = true, $depth = 512, $options = 0)
    {
        array_walk($list, function (&$item) use ($fields, $remove_source, $depth, $options) {
            static::json_decode_item($item, $fields, $remove_source, $depth, $options);
        });
    }

    public static function json_decode_item(&$item, $fields, $remove_source = true, $depth = 512, $options = 0)
    {
        foreach ($fields as $k => $v) {
            if ($item[$k]) {
                $item[$v] = json_decode($item[$k], true, $depth, $options);
            }
            // нулы не будем делать. пока не вижу особой разницы делать проверку на нулл или undefined
            /*else {
                $row[$v] = null;
            }*/
            if ($remove_source) unset ($item[$k]);
        }
    }
}