<?php
namespace app\models;

use app\components\Form;
use Yii;
use yii\caching\TagDependency;
use yii\db\Exception;

abstract class BaseModel extends \yii\db\ActiveRecord
{
    public static function checkRights(&$site, &$formContent, $action = 'read')
    {
        return true;
    }

    // ----------------------------------------------- SAVE
    public static function checkForm(&$site, &$fields, &$lang, &$formData, &$errors)
    {
        // блок тестирования отображения ошибок
        /*$errors[''] = ['text' => 'Error test'];
        $errors['name'] = ['title'=>'Название', 'text' => 'Поле обязательно для заполнения'];
        /**/

        return Form::checkForm($site, $fields, $lang, $formData, $errors);
    }

    /*
     * Дополнительная установка значений, в частности site_id
     */
    public static function setAdditionalValues(&$site, &$fields, &$lang, &$formData, $id)
    {
        if (!$id) // надо прописать доп переменные
        {
            $formData['site_id'] = $site['id'];
        }
    }

    public static function editItem(&$site, &$lang, $id, &$formContent, &$get, &$post)
    {
        // todo проверка прав доступа
        if (!static::checkRights($site, $formContent, 'write')) {
            //$errors[''] = ['text' => 'Method not allowed'];
            // todo надо заменить форму сообщением, и елси юзер не авторизован, то признак что требуется авторизация
            $formContent = [
                [
                    'id' => -999, // id нужен для ключа (key) на фронте
                    'content' => 'Method not allowed',
                    'type' => '',
                    'template_key' => 'Error',
                    // не все формы требуют авторизации!
                    //'settings' => ['authRequired' => 'true'],
                    'childs' => [],
                ]
            ];
            return;
        }

        $fields = [];
        //Yii::info('-----------$formContent=' . json_encode($formContent, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT), __METHOD__);
        Form::getFieldsFromContent($formContent, $fields);

        $formData = null;//$post !== null ? $post : $get;
        if ($post !== null) { // сохраниение
            $formData = $post;
            $errors = [];

            //Yii::info('-----------$fields=' . var_export($fields, true), __METHOD__);
            if (static::checkForm($site, $fields, $lang, $formData, $errors)) { // все ок

                // сохраняем в БД
                //sleep(3); // отладка

                static::setAdditionalValues($site, $fields, $lang, $formData, $id);
                $res = static::saveItem($id, $formData, $fields);
                if ($res !== false) {
                    // замещаем ед контента
                    foreach(array_keys($formContent) as $key) {
                        unset($formContent[$key]);
                    }

                    array_unshift($formContent, [
                        'id' => -20, // id нужен для ключа (key) на фронте
                        'content' => 'Save success',
                        'type' => '',
                        'template_key' => 'MessageFormSuccess,MessageForm,Message',
                        //'content_keys' => [],
                        'settings' => [],
                        'childs' => [],
                    ]);/**/

                    // скинем кеш (чистим кеш полностью)
                    //Yii::$app->cache->flush();
                    // чистим кеш тока определенного сайта (по идее разницы нет, у нас 1 сайт на 1 БД)
                    TagDependency::invalidate(Yii::$app->cache, 'site-' . $site['id']);

                    return;
                }

                $errors[''] = ['text' => 'Ошибка сохранения'];
                Form::setError($formContent, $errors);
            } else {
                Yii::info('-----------$formErrors=' . var_export($errors, true), __METHOD__);
                // показываем сообщение об ошибке
                Form::setError($formContent, $errors);

                //Yii::info('-----------$formContent with errors=' . var_export($formContent, true), __METHOD__);
                //sleep(3); // отладка
            }
        } else { // читаем данные из БД
            $formData = $get;
            if ($id !== '0') { // грузим данные из БД
                $formData = static::getItemById($site, $id);
            } else {
                $formData['id'] = '0';
            }
        }

        //Yii::info('-----------$formData=' . var_export($formData, true), __METHOD__);
        Form::fillForm($formContent, $formData);
    }

    // вызывать без $fields опасно!!! так как все поля с поста будут писаться в запрос!!!
    // вызывать нe опасно после вызова form_check($form, &$data) там происходит проверка на присутствие поля в форме
    // без $fields вызываем тока то что сами проверяем
    static function saveItem($id, $data, $fields = [])
    {
        $table_id_name = 'id';

        if (!$data) // обновлять нечего
        {
            return true;
        }
        $sql_params = array();
        $sql_update = array();
        $sql_insert_fields = array();
        $sql_insert_values = array();
        foreach ($data as $name => $value) {
            if (strpos($name, '-') !== false) continue; // языковое поле !

            if ($name == $table_id_name) {
                continue;
            }

            // признак того что поле не надо записывать в БД
            if ($fields && isset($fields[$name]) && isset($fields[$name]['settings']['notfromdb']) && $fields[$name]['settings']['notfromdb']) {
                continue;
            }

            // поле принадлежит форме его записываем по другому
            /*if ($fields && isset($fields[$name]) && isset($fields[$name]['field_ID']) && $fields[$name]['field_ID'])
            {
                continue;
            }*/

            if ($fields && isset($fields[$name]) && $fields[$name]['settings']['fieldtype'] == 'datetime' && !$value && $fields[$name]['settings']['default'] == 'now()') {
                $sql_update[] = '`' . $name . '`' . '=now()';
                $sql_insert_fields[] = '`' . $name . '`';
                $sql_insert_values[] = 'now()';
            } else if ($fields && isset($fields[$name]) && $fields[$name]['settings']['fieldtype'] == 'date' && !$value && $fields[$name]['settings']['default'] == 'now()') {
                $sql_update[] = '`' . $name . '`' . '=now()';
                $sql_insert_fields[] = '`' . $name . '`';
                $sql_insert_values[] = 'now()';
            } else if ($fields && isset($fields[$name]) && !$value && isset($fields[$name]['settings']['default']) && $fields[$name]['settings']['default'] === 'NULL') {
                //log_message('error', 'MY_Model::save(). '.$name.'=NULL. $fields[$name][default]='.$fields[$name]['default']);
                $sql_update[] = '`' . $name . '`' . '=NULL';
                $sql_insert_fields[] = '`' . $name . '`';
                $sql_insert_values[] = 'NULL';
            }
            // эта проверка делается тут form_check($form, &$data)
            //else if ($fields)
            //{
            //	// если форма задана и в ней нет такого поля значит пропускаем поле!!!
            //	if (!isset($fields[$name])) continue;
            //}
            else {
                $sql_update[] = '`' . $name . '`' . '=:' . $name;
                $sql_insert_fields[] = '`' . $name . '`';
                $sql_insert_values[] = ':' . $name;
                $sql_params[':' . $name] = $value; //is_null($value)?'NULL':$value;
            }
        }

        $db = Yii::$app->db;
        try {
            if ($id > 0) {
                $sql_params[':id'] = $id;
                $sql = 'update ' . static::tableName() . ' set ' . implode(',', $sql_update) . ' where ' . $table_id_name . '=:id';
                Yii::info('-----------update sql = ' . $sql . ' params=' . var_export($sql_params, true), __METHOD__);
                $db->createCommand($sql, $sql_params)->execute();
                //log_message('error', 'MY_Model::save(). SQL:'.$sql);
                //$query = $this->query($sql, $sql_params);
                $result = $id;
            } else {
                $sql = 'INSERT INTO ' . static::tableName() . ' (' . implode(',', $sql_insert_fields) . ') VALUES (' . implode(',', $sql_insert_values) . ')';
                //Yii::info('-----------insert sql = ' . $sql . ' params=' . var_export($sql_params, true), __METHOD__);
                $db->createCommand($sql, $sql_params)->execute();
                $result = $db->getLastInsertID();
                //$query = $this->query('insert into '.$this->table_name.' ('.implode(',', $sql_insert_fields).') values ('.implode(',', $sql_insert_values).')', $sql_params);
                //$result = $this->insert_id();

            }
        } catch (\yii\db\Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
            return false;
        }


        /*if (!$query)
        {
            return false;
        }*/

        //$this->clear_cache($result);
        // очень плохой котсыль, но пока так при редактировании нет этого поля !!!
        // унес на уровень потомка модели, пусь сам разбирается
        //if (isset($post['site_ID']))
        //{
        //	$this->clear_cache($post['site_ID'], $result);
        //}

        return $result;
    }

    // ----------------------------------------------- /SAVE

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