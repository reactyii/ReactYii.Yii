<?php

namespace app\components;
use app\models\Template;
use yii;
use app\models\Content;
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

    public static function addFiltersFromContentArgs(&$query, &$content_args)
    {
        // todo
        // ...

        return $query;
    }

    /**
     * @throws yii\web\NotFoundHttpException
     */
    function getContentForList(&$site, &$lang, &$section, &$page, $listContent, &$content_args, $offset, $limit, $item = null, $recursion_level = 0)
    {
        //Yii::info('!!!-----------!!!!!!!!!', __METHOD__);
        //return [[], 0];
        $count = null;
        $query = Content::find()
            //->select('c.*, t.type, t.settings_json')
            // так как у нас контентов может быть много и все они сериализуются и идут на фронт то делаем сразу оптимизацию и убираем все лишнее
            // названия полей таблицы НЕ будем делать короче (все современные браузеры поддерживают сжатие ответа сервера)
            //->select(static::$_sel)
            ->from(Content::tablename() . '  c')
            // вот почему я не долюбливаю всякие ормы! при join оно сцуко делает 2 доп НЕНУЖНЫХ запроса на резолв структуры таблицы
            // SHOW FULL COLUMNS FROM `content`
            // и SELECT ... FROM `information_schema`.`REFERENTIAL_CONSTRAINTS` AS `rc`
            ->join('LEFT JOIN', Template::tableName() . ' t' ,  'c.template_key = t.key')
            ->where([
                'c.site_id' => $site['id'],
                //'c.parent_id' => $parent_id,
            ]);

        if ($item === null) // сам список
        {
            // здесь именно "static::"
            $query = static::addFiltersFromContentArgs($query, $content_args);

            // для начала вычислим коунт
            // при вычислении count можно похерить join для оптимизации! todo!
            $countRow = $query->select('count(*) as `total_rows`')->asArray()->one();
            //Yii::info('-----------' . var_export($countRow, true), __METHOD__);
            $count = $countRow['total_rows'];

            Yii::info('-----------$count=' . $count . '; $offset=' . $offset, __METHOD__);

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
            foreach ($list as $k => $v)
            {
                // не меняем парента! теперь мы идем рекурсией
                //$list[$k]['parent_id'] = $listContent['id'];

                // а вот поменять "content_keys" надо. причем сохранив исходный вариант
                $list[$k]['settings']['content_keys'] = isset($v['content_keys']) && $v['content_keys'] ? json_encode($v['content_keys']) : [];
                $list[$k]['content_keys'] = ['CONTENT'];

                $list[$k]['settings']['name'] = $v['name']; // нужно для формирования удобочитаемого списка
            }

        }
        else // элемент списка
        {
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
