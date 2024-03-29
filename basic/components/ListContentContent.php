<?php

namespace app\components;
use app\models\BaseModel;
use app\models\Template;
use yii;
use app\models\Content;
use app\models\Menu;
use yii\base\BaseObject;
use yii\db\ActiveRecord;

class ListContentContent extends ListContentBase
{
    protected $id = 'content'; // это значение указывается в Content->model у единицы контента типа список

    /*public $prop1;
    public $prop2;

    public function __construct($config = [])
    {
        // ... инициализация происходит перед тем, как будет применена конфигурация.

        parent::__construct($config);

        //Yii::info('ListContentContent::__construct() $config=', var_export($config, true), '1');
    }

    public function init()
    {
        parent::init();

        Yii::info('ListContentContent::init()', __METHOD__);

        // ... инициализация происходит после того, как была применена конфигурация.

        Content::registerContentList();
    }/**/

    /*
    function getContentForItemEdit(&$session, &$lang, &$section, &$page, $listContent, &$get, &$post)
    {
        $pageOptions = Menu::getAllForSelect($site, null, 'id', 'menu_name');
        array_unshift($pageOptions, ['id' => 0, 'path' => '', 'content' => 'Выберите страницу', 'type' => 'option']);
        $form = [
            [
                'content' => '',
                'id' => $listContent['id'],
                'template_key' => 'FormFilterContent,FormFilter,Form',
                'type' => 'form',
                //'model' => 'content', // ссылка на самих себя
                //'path' => $listContent['path'], //'contentslist', // для формирования action
                'settings' => ['method' => 'post', 'path' => $listContent['path']],
                'content_keys' => [], // занимаем основное поле контента
                'childs' => [
                    [
                        'id' => 10, // id нужен для ключа (key) на фронте
                        'content' => '',
                        'type' => 'field',
                        'template_key' => 'FieldHidden',
                        'settings' => ['type' => 'text', 'formpath' => $listContent['path'], 'fieldname' => 'id', 'value' => ''],
                        'childs' => [],
                    ],
                    [
                        'id' => 20, // id нужен для ключа (key) на фронте
                        'content' => '',
                        'type' => 'field',
                        'template_key' => 'Field',
                        'settings' => ['formpath' => $listContent['path'], 'fieldname' => 'name', 'value' => '', 'label' => 'Название'],
                    ],
                    [
                        'id' => 30, // id нужен для ключа (key) на фронте
                        'content' => '',
                        'type' => 'field',
                        'template_key' => 'FieldSelectTreePage,FieldSelectTree,FieldSelect',
                        'settings' => ['formpath' => $listContent['path'], 'fieldname' => 'menu_id', 'value' => '', 'label' => 'Страница'],
                        'childs' => $pageOptions
                    ],
                    [
                        'id' => 1000,
                        'content' => 'Сохранить',
                        'type' => 'submitform',
                        'template_key' => 'FormContentSubmit,FormSubmit', // FormFilterSubmit или даже FormFilterContentSubmit
                        'settings' => ['formpath' => $listContent['path'], 'fieldname' => 'fsubm', 'value' => 'Сохранить', 'ignore' => '1'],
                        'childs' => [],
                    ],
                ],
            ],
        ];
        return $form;
    }
    /**/
    public function getContentForListFilter(&$session, &$lang, &$section, &$page, $listContent, &$content_args)
    {
        //$site = BaseModel::getSiteFromSession($session);
        //$pageOptions = Menu::getAllForSelect($site, null, 'id', 'menu_name');
        //array_unshift($pageOptions, ['id' => 0, 'path' => '', 'content' => 'Выберите страницу', 'type' => 'option']);
        $pageOptions = [
            [
                'id' => '',
                'path' => '',
                'content' => 'Выберите страницу',
                'childs' => Menu::getAllForSelect($session, null, 'id', 'menu_name'),
                'type' => 'option'
            ]
        ];

        $form = [
            [
                'content' => '',
                'id' => $listContent['id'],
                'template_key' => 'FormFilterContent,FormFilter,Form',
                'type' => 'form',
                //'model' => 'content', // ссылка на самих себя
                //'path' => $listContent['path'], //'contentslist', // для формирования action
                'settings' => ['method' => 'get', 'path' => $listContent['path']],
                'content_keys' => ['FILTER'],
                'childs' => [
                    [
                        'id' => 10, // id нужен для ключа (key) на фронте
                        'content' => '',
                        'type' => 'field',
                        'template_key' => 'FieldFilter,Field',
                        'settings' => ['fieldtype' => 'text', 'formpath' => $listContent['path'], 'fieldname' => 'name', 'value' => '', 'label' => 'Название', 'tablefieldname' => 'c.name', 'where' => ''],
                        'childs' => [],
                    ],
                    [
                        'id' => 20, // id нужен для ключа (key) на фронте
                        'content' => '',
                        'type' => 'field',
                        'template_key' => 'FieldFilterSelectTreePage,FieldFilterSelectTree,FieldFilterSelect,FieldSelect',
                        'settings' => ['formpath' => $listContent['path'], 'multiple' => true, 'fieldname' => 'menu_id', 'value' => 'sel2', 'label' => 'Страница', 'tablefieldname' => 'c.menu_id'],
                        'childs' => $pageOptions
                    ],
                    [
                        'id' => 1000,
                        'content' => 'Найти',
                        'type' => 'submitform',
                        'template_key' => 'FormFilterContentSubmit,FormFilterSubmit,FormSubmit', // FormFilterSubmit или даже FormFilterContentSubmit
                        'settings' => ['formpath' => $listContent['path'], 'fieldname' => 'fsubm', 'value' => 'Найти', 'ignore' => '1'],
                        'childs' => [],
                    ],
                    [
                        'id' => 1010,
                        'content' => 'Сбросить',
                        'type' => 'resetform',
                        'template_key' => 'FormFilterContentReset,FormFilterReset,FormReset',
                        'settings' => ['formpath' => $listContent['path'], 'value' => 'Сбросить', 'ignore' => '1'],
                        'childs' => [],
                    ],
                ],
            ],
        ];

        return $form;
    }

    public function getContentForEditForm(&$session, &$lang, &$section, &$page, $listContent)
    {
        //$pageOptions = Menu::getAllForSelect($site, null, 'id', 'menu_name');
        //array_unshift($pageOptions, ['id' => 0, 'path' => '', 'content' => 'Выберите страницу', 'type' => 'option']);
        $pageOptions = [
            [
                'id' => '',
                'path' => '',
                'content' => 'Самый верхний уровень',
                'childs' => Menu::getAllForSelect($session, null, 'id', 'menu_name'),
                'type' => 'option'
            ]
        ];
        //Yii::info('!!!-----------!!!!!!!!!' . var_export($pageOptions, true), __METHOD__);

        $contentOptions = [
            [
                'id' => '',
                'path' => '',
                'content' => 'Самый верхний уровень',
                'childs' => Content::getAllForSelect($session, null, 'id', 'name'),
                'type' => 'option'
            ]
        ];

        $form = [
            [
                'content' => '',
                'id' => $listContent['id'],
                'template_key' => 'FormContent,Form',
                'type' => 'form',
                //'model' => 'content', // ссылка на самих себя
                //'path' => $listContent['path'], //'contentslist', // для формирования action
                'settings' => ['method' => 'post', 'path' => $listContent['path']],
                //'content_keys' => [],
                'childs' => [
                    [
                        'id' => -10, // id нужен для ключа (key) на фронте
                        'content' => '',
                        'type' => '',
                        'template_key' => 'ErrorForm,Error',
                        'content_keys' => ['ERROR'],
                        'settings' => [],
                        'childs' => [],
                    ],
                    [
                        'id' => 10, // id нужен для ключа (key) на фронте
                        'content' => '',
                        'type' => 'field',
                        'template_key' => 'Field',
                        'settings' => ['fieldtype' => 'hidden', 'formpath' => $listContent['path'], 'fieldname' => 'id', 'value' => ''],
                        'childs' => [],
                    ],
                    [
                        'id' => 20, // id нужен для ключа (key) на фронте
                        'content' => '',
                        'type' => 'field',
                        'template_key' => 'Field',
                        'settings' => ['fieldtype' => 'string', 'required' => '1', 'formpath' => $listContent['path'], 'fieldname' => 'name', 'value' => '', 'label' => 'Название'],
                    ],
                    [
                        'id' => 30, // id нужен для ключа (key) на фронте
                        'content' => '',
                        'type' => 'field',
                        'template_key' => 'FieldSelectTreePage,FieldSelectTree,FieldSelect',
                        'settings' => ['fieldtype' => 'tree', 'formpath' => $listContent['path'], 'fieldname' => 'menu_id', 'value' => '', 'label' => 'Страница'],
                        'childs' => $pageOptions
                    ],
                    [
                        'id' => 40, // id нужен для ключа (key) на фронте
                        'content' => '',
                        'type' => 'field',
                        'template_key' => 'FieldSelectTreePage,FieldSelectTree,FieldSelect',
                        'settings' => ['fieldtype'=>'tree', 'formpath' => $listContent['path'], 'selfrefto'=>'id', 'fieldname' => 'parent_id', 'value' => '', 'label' => 'Разместить в'],
                        'childs' => $contentOptions
                    ],/**/
                    [
                        'id' => 1000,
                        'content' => 'Сохранить',
                        'type' => 'submitform',
                        'template_key' => 'FormContentSubmit,FormSubmit', // FormFilterSubmit или даже FormFilterContentSubmit
                        'settings' => ['formpath' => $listContent['path'], 'fieldname' => 'fsubm', 'value' => 'Сохранить'],
                        'childs' => [],
                    ],
                ],
            ]
        ];

        return $form;
    }
    /**/

    /**
     * @throws yii\web\NotFoundHttpException
     */
    function getContentForList(&$session, &$lang, &$section, &$page, $listContent, &$content_args, &$get = null, &$post = null, $offset=0, $limit=null, $item = null, $recursion_level = 0)
    {
        $site = BaseModel::getSiteFromSession($session);
        //Yii::info('!!!-----------!!!!!!!!!', __METHOD__);
        //return [[], 0];
        //$count = null;
        $query = Content::find()
            //->select('c.*, t.type, t.settings_json')
            // так как у нас контентов может быть много и все они сериализуются и идут на фронт то делаем сразу оптимизацию и убираем все лишнее
            // названия полей таблицы НЕ будем делать короче (все современные браузеры поддерживают сжатие ответа сервера)
            //->select(static::$_sel)
            ->from(Content::tablename() . '  c')
            // вот почему я не долюбливаю всякие ормы! при join оно сцуко делает 2 доп НЕНУЖНЫХ запроса на резолв структуры таблицы
            // SHOW FULL COLUMNS FROM `content`
            // и SELECT ... FROM `information_schema`.`REFERENTIAL_CONSTRAINTS` AS `rc`
            ->join('LEFT JOIN', Template::tableName() . ' t', 'c.template_key = t.key')
            ->where([
                'c.site_id' => $site['id'],
                //'c.parent_id' => $parent_id,
            ]);
        $list = null;
        $count = null;

        if ($item === null) {
            //------------------------------------- сам список
            // добавим фильтр в список
            $formFilterContent = $this->getContentForListFilter($session, $lang, $section, $page, $listContent, $content_args);
            //Yii::info('-----------$formFilterContent=' . var_export($formFilterContent, true), __METHOD__);

            $fields = [];
            Form::getFieldsFromContent($formFilterContent, $fields);
            $formData = Form::getFormDataFromContentArgsPath($content_args);
            //Yii::info('-----------$formData=' . var_export($formData, true), __METHOD__);
            Form::fillForm($formFilterContent, $formData);
            //Yii::info('-----------filled filter=' . var_export($formFilterContent, true), __METHOD__);
            $query = $this->addFiltersFromFormData($query, $formData, $fields);

            // для начала вычислим total_rows
            // при вычислении count можно похерить left join для оптимизации! todo!
            $countRow = $query->select('count(*) as `total_rows`')->asArray()->one();
            //Yii::info('-----------' . var_export($countRow, true), __METHOD__);
            $count = $countRow['total_rows'];

            //Yii::info('-----------$count=' . $count . '; $offset=' . $offset, __METHOD__);

            // может редирект сделать на первую страницу? но для SEO важнее 404
            if ($offset > $count)
                throw new \yii\web\NotFoundHttpException();

            $list = $query->select(Content::$_sel . ', c.name')->orderBy([
                //'c.priority' => SORT_ASC,
                'c.id' => SORT_ASC
            ])
                ->asArray()
                ->limit($limit)->offset($offset)
                ->all();

            Content::json_decode_list($list, ['content_keys_json' => 'content_keys', 'settings_json' => 'settings', 'template_settings_json' => 'template_settings']);

            // меняем парента. так как модель строит списки по БД и создает элементы контента как бы исскуственно.
            // в данной модели нам надо тока поменять парента в других мы будем создавать элементы полностью
            foreach ($list as $k => $v) {
                // не меняем парента! теперь мы идем рекурсией
                //$list[$k]['parent_id'] = $listContent['id'];

                // а вот поменять "content_keys" надо. причем сохранив исходный вариант
                $list[$k]['settings']['content_keys'] = isset($v['content_keys']) && $v['content_keys'] ? json_encode($v['content_keys']) : json_encode([]);
                $list[$k]['content_keys'] = ['CONTENT'];

                $list[$k]['settings']['type'] = $list[$k]['type'];
                $list[$k]['type'] = 'html';

                $list[$k]['settings']['template'] = $list[$k]['template'];
                $list[$k]['template'] = '';

                $list[$k]['content'] = '<b>' . $v['id'] . '</b> ' . $v['name'];

                $list[$k]['settings']['template_key'] = isset($v['template_key']) && $v['template_key'] ? $v['template_key'] : '';
                $list[$k]['template_key'] = '';

                $list[$k]['settings']['name'] = $v['name']; // нужно для формирования удобочитаемого списка

                // вот так разрешаем редактировать
                $list[$k]['childs'][] = [
                    'content' => 'Редактировать',
                    'id' => -100,
                    'template_key' => 'AContentEdit,AEdit,A',
                    'type' => 'linkedit',
                    'content_keys' => ['LINKEDIT'],
                    'settings' => [
                        'url' => '' // формируется на фронте
                    ],
                ];
            }

            foreach ($formFilterContent as $i) {
                $list[] = $i;
            }

            // добавим ссылку на добавление итема
            $list[] = [
                'content' => 'Добавить',
                'id' => -100,
                'template_key' => 'AContentAdd,AAdd,A',
                'type' => 'linkadd',
                'content_keys' => ['LINKADD'],
                'settings' => [
                    'url' => '' // формируется на фронте
                ],
            ];
        } else if (strpos($item, '__') === 0) {
            // ---------------------------- редактирование
            // лишнии сущности все таки понадобились
            $action = $item;
            // todo внести слова начинающиеся с "__" в черный список для path
            if ($action === '__edit') {
                if (sizeof($content_args) === 0) // ожидаем id сущности
                    throw new \yii\web\NotFoundHttpException();

                $id = array_shift($content_args);

                //$list = null;
                $form = $this->getContentForEditForm($session, $lang, $section, $page, $listContent);
                //Yii::info('-----------$formFilterContent=' . var_export($formFilterContent, true), __METHOD__);

                Content::editItem($session, $lang, $id, $form, $get, $post);

                // NB! снаружи если $item !== null, ожидается один элемент, которые замещает элемент список
                if (sizeof($form) === 1) {
                    //Yii::info('-----------$form=' . var_export($form, true), __METHOD__);
                    $list = $form[0];
                } else if (sizeof($form) > 1) {
                    // да такой случай возможен, если присутствует не только форма, но и какие-то другие блоки контента (инструкции? описания? сео?)
                    // todo создать элемент контэйнер с 'childs' = $form
                    // ...
                }
            } // возможно будут еще варианты
            // else {}
        } else {
            // --------------------------------- элемент списка
            // а вот сюда мы скорее всего заходить не должны, так как фича для админки и из списка будут тока линки на формы редактирования
            throw new \yii\web\NotFoundHttpException();

            /*$query = $query->andWhere('c.page=:path', [':path' => $item]);
            $list = $query
                ->select('c.*, t.type, t.settings_json as template_settings_json') // при вытаскивании поштучно нам нужны уже все данные
                ->asArray()
                ->one();
            */
        }

        // заполнить элементы потомками.
        // а вот не надо!!!! мы сделаем переход внутрь так как данный режим для редактирования контента

        return [$list, $count];
    }
}
