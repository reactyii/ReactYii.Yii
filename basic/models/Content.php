<?php

namespace app\models;

use Yii;
use yii\caching\TagDependency;

/**
 * This is the model class for table "{{%content}}".
 *
 * @property int $id
 * @property int $site_id
 * @property string $created_at Created record date
 * @property string|null $updated_at Last modified date
 * @property int $priority Поле для сортировки. По возрастанию
 * @property int $is_blocked Для временного скрытия
 * @property int|null $parent_id В каком родительском элементе показать
 * @property int|null $language_id Для какого языка данная единица. Если NULL, то для всех языков
 * @property int|null $menu_id Главная страница где размещен контент. Пока не знаю нужно это или нет
 * @property int|null $section_id Главный раздел в котором находится контент. Если NULL, то это раздел по умолчанию. Например, иногда бывает фишка, что сначала показываем новости раздела, а потом все остальные.
 * @property int $is_all_section Для всех разделов
 * @property int $is_all_menu Для всех страниц
 * @property string $name
 * @property string|null $template Ссылка на шаблон для отрисовки данной единицы. Например, для списков или составных блоков. Если NULL, то вставляем как текст.
 * @property string|null $content Сам контент.
 * @property string|null $search_words Слова для поиска. При сохранении здесь формируем список слов для поиска.
 * @property string|null $template_keys_json Список ключей для вставки в родительский шаблон или для вставки на страницу.
 * @property string|null $seo_title SEO Title
 * @property string|null $seo_description SEO description meta tag
 * @property string|null $seo_keywords SEO keywords meta tag
 *
 * @property Language $language
 * @property Menu $menu
 * @property Content $parent
 * @property Content[] $contents
 * @property Section $section
 * @property Site $site
 * @property ContentOnMenu[] $contentOnMenus
 * @property ContentOnSection[] $contentOnSections
 */
class Content extends BaseModel
{

    private static $_sel = 'c.id, c.priority, c.parent_id, c.path, c.content, c.template_keys_json, c.settings_json, t.type, t.settings_json as template_settings_json'; // нужны ли language_id section_id menu_id

    public static function getContentForList(&$site, &$lang, &$section, &$page, $listContent, $offset, $limit, $item = null)
    {
        // в кеш не загоняем так как мы загоним в кеш все узлы контента для страницы

        $count = null;
        $query = self::find()
        //->select('c.*, t.type, t.settings_json')
        // так как у нас контентов может быть много и все они сериализуются и идут на фронт то делаем сразу оптимизацию и убираем все лишнее
        // названия полей таблицы НЕ будем делать короче (все современные браузеры поддерживают сжатие ответа сервера)
        //->select(static::$_sel)
        ->from(static::tablename() . '  c')
        // вот почему я не долюбливаю всякие ормы! при join оно сцуко делает 2 доп НЕНУЖНЫХ запроса на резолв структуры таблицы
        // SHOW FULL COLUMNS FROM `content`
        // и SELECT ... FROM `information_schema`.`REFERENTIAL_CONSTRAINTS` AS `rc`
        ->join('LEFT JOIN', Template::tableName() . ' t' ,  'c.template = t.key')
        ->where([
            'c.site_id' => $site['id'],
            //'c.parent_id' => $parent_id,
        ]);

        $query = $query->andWhere('c.menu_id=:menuid or c.is_all_menu=1', [':menuid' => $page['id']])
        ->andWhere('c.is_blocked=0')
        ->andWhere(['c.language_id' => null]);// NB! делаем поиск строго для языка по умолчанию (пеервод будем делать позднее, его может тупо не быть для какого-то промежуточного узла)

        // NB! нам надо запретить подгрузку элементов списка! так как данных там может быть много и нам надо делать загрузку списка с учетом пагинации
        //$query = $query->andWhere('is_list_item=0');
        $query = $query->andWhere('parent_id=:parent', [':parent' => $listContent['id']]);

        if ($section)
        {
            $query = $query->andWhere('c.section_id=:sectionid or c.is_all_section=1', [':sectionid' => $section['id']]);
        }
        else
        {
            $query = $query->andWhere(['c.section_id' => null]);
        }

        if ($item === null)
        {
            // для начала вычислим коунт
            $countRow = $query->select('count(*)')->asArray()->one();
            $count = $countRow[0];

            // может редирект сделать на первую страницу? но для SEO важнее 404
            if ($offset > $count)
                new \yii\web\NotFoundHttpException();

            $list = $query->select(static::$_sel)->orderBy([
                'c.priority' => SORT_ASC,
                'c.id' => SORT_ASC
            ])
                ->asArray()
                ->limit($limit)->offset($offset)
                ->all();
        }
        else
        {
            $query = $query->andWhere('c.page=:path', [':path' => $item]);
            $list = $query
                ->select('c.*, t.type, t.settings_json as template_settings_json') // при вытаскивании поштучно нам нужны уже все данные
                ->asArray()
                ->one();
        }

        return [$list, $count];
    }

    /**
     * Готовим список единиц контента для страницы.
     *
     */
    public static function getContentForPage(&$site, &$lang, &$section, &$page, &$content_args)
    {
        $key = implode('-', [
            $site != null ? $site['id'] : '',
            $lang ? $lang['id'] : '',
            $section ? $section['id'] : '',
            $page['id'],
            //$parent_id != null ? $parent_id : '',
            implode('_', $content_args),
            static::getCacheBaseKey(),
            __FUNCTION__
        ]);
        Yii::info("getContentForPage. key=" . $key, __METHOD__);

        $contentList = Yii::$app->cache->getOrSet($key, function () use ($key, $site, $lang, $page, $section, $content_args) {
            Yii::info("getContentForPage. get from DB key=" . $key, __METHOD__);

            $query = self::find()
                //->select('c.*, t.type, t.settings_json')
                // так как у нас контентов может быть много и все они сериализуются и идут на фронт то делаем сразу оптимизацию и убираем все лишнее
                // названия полей таблицы НЕ будем делать короче (все современные браузеры поддерживают сжатие ответа сервера)
                ->select(static::$_sel)
                ->from(static::tablename() . '  c')
                // вот почему я не долюбливаю всякие ормы! при join оно сцуко делает 2 доп НЕНУЖНЫХ запроса на резолв структуры таблицы
                // SHOW FULL COLUMNS FROM `content`
                // и SELECT ... FROM `information_schema`.`REFERENTIAL_CONSTRAINTS` AS `rc`
                ->join('LEFT JOIN', Template::tableName() . ' t' ,  'c.template = t.key')
                ->where([
                    'c.site_id' => $site['id'],
                    //'c.parent_id' => $parent_id,
                ]);

            $query = $query->andWhere('c.menu_id=:menuid or c.is_all_menu=1', [':menuid' => $page['id']])
                ->andWhere('c.is_blocked=0')
                ->andWhere(['c.language_id' => null]);// NB! делаем поиск строго для языка по умолчанию (пеервод будем делать позднее, его может тупо не быть для какого-то промежуточного узла)

            // NB! нам надо запретить подгрузку элементов списка! так как данных там может быть много и нам надо делать загрузку списка с учетом пагинации
            $query = $query->andWhere('is_list_item=0');

            if ($section)
            {
                $query = $query->andWhere('c.section_id=:sectionid or c.is_all_section=1', [':sectionid' => $section['id']]);
            }
            else
            {
                $query = $query->andWhere(['c.section_id' => null]);
            }

            $list = $query->orderBy([
                'c.priority' => SORT_ASC,
                'c.id' => SORT_ASC
            ])
            ->asArray()
            ->all();
            // Yii::info("getContentForPage. ----------------------------------- request to db end " . $key, __METHOD__);

            static::json_decode($list, ['template_keys_json' => 'template_keys', 'settings_json' => 'settings', 'template_settings_json' => 'template_settings']);

            // а вот после загрузки основного контента мы сделаем подгрузку элементов списка с учетом пагинации или детальной инфы
            if ($list) // если хоть что-то есть в списке
            {
                $listDatas = []; // сюда будем догружать контенты для списков
                $forRemove = [];
                foreach ($list as $k=>$c)
                {
                    // если тип контента это список, то в $content_args у нас могут быть переданы параметры типа номер текущей страницы или имя конкретного элемента
                    if ($c['type'] === Template::TYPE_LIST) // $c['type'] это тип шаблона
                    {
                        $limit = 10; // число итемов на странице берем из настроек контента и если там нет, то шаблона

                        // так как списков на странице может быть несколько, то надо проверить, а может юзер пошел по именнно по этому списку. если нет то показываем первую страницу спсика
                        if (sizeof($content_args) > 0 && $content_args[0] === $c['path']) // наш список
                        {
                            // очень важно в эту ветку мы можем зайти тока 1 раз на все контенты! а мы это гарантируем проверкой $content_args после этой ветки

                            array_shift($content_args); // удаляем чаcть кодирующиую имя списка

                            if (sizeof($content_args) === 0) // такой урл не коректен если мы зашли в список то должны либо выбрать страницу либо конкретный итем
                                throw new \yii\web\NotFoundHttpException();

                            if (ctype_digit($content_args[0])) // не будем вводить лишних сущностей и слов в путь. если число то считаем его номеров страницы, если строка то это path единицы списка
                            {
                                $offset = array_shift($content_args);
                                list($_listDatas, $count) = static::getContentForList($site, $lang, $section, $page, $c, $offset * $limit, $limit);
                                $listDatas += $_listDatas;
                                $list[$k]['count'] = $count;
                                $list[$k]['offset'] = $offset;
                            }
                            else
                            {
                                $item = array_shift($content_args);
                                list($_listDatas, $count) = static::getContentForList($site, $lang, $section, $page, $c, null, null, $item);

                                // а вот тут мы должны заменить? сам список элементом - НЕТ.
                                // здесь мы должны
                                // 1. убрать список из контента
                                $forRemove[] = $k; // индекс
                                // 2. поместить контент элемента на самый верхний уровень (в корень)

                                // и переписать SEO и заголовок страницы h1 хлебные крошки

                                Yii::error("getContentForPage. not implemented yet", __METHOD__);
                            }

                            // нельзя прерывать break; так как мы должны заполнить другие списки по крайней мере перовой страницей
                            //break;

                            // здесь должно быть так - мы можем зайти тока в 1 список строго
                            if (sizeof($content_args) > 0)
                            {
                                throw new \yii\web\NotFoundHttpException();
                            }
                        }
                        else // заполняем первую страницу
                        {
                            list($_listDatas, $count) = static::getContentForList($site, $lang, $section, $page, $c, 0, $limit);
                            $listDatas += $_listDatas;
                            $list[$k]['count'] = $count;
                            $list[$k]['offset'] = 0;
                        }
                    }
                    //$contentList[$k]['childs'] = static::getContentForPage($site, $lang, $section, $page, $content_args, $c['id'], $recursion_level + 1);
                }

                // а вот после прохождения всего списка у нас должен быть абсолютно пустой $content_args и если это не так, то мы должны выдать 404, чтоб поисковики не ползали там где не надо
                if (sizeof($content_args) > 0)
                {
                    throw new \yii\web\NotFoundHttpException();
                }

                if (sizeof($forRemove) > 0)
                {
                    // обязательно учесть что елси удаляемых больше 1, то все след после первого надо уменьшать на -1 затем на -2 и тд или удалять начиная с конца!
                    $forRemove = array_reverse($forRemove);
                    foreach ($forRemove as $removeInd)
                    {
                        // ...
                    }

                    Yii::error("getContentForPage. not implemented yet", __METHOD__);
                }

                $list += $listDatas; // позже мы сварганим корректное дерево
            }



            Yii::info("getContentForPage. source list=" . var_export($list, true), __METHOD__);

            $list = static::listToHash($list);

            Yii::info("getContentForPage. hash=" . var_export($list, true), __METHOD__);

            return static::hashToTree($list);
        }, null, new TagDependency([
            'tags' => [
                'site-' . $site['id'],
                static::getCacheBaseKey() . '-' . $site['id'],
                'page-' . $page['id'] // чтобы при изменении одной единицы контента скинуть все для конкретной страницы
            ]
        ]));

        return $contentList;
    }

    // -------------------------------------------- auto generated -------------------------

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%content}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['site_id', 'created_at', 'name'], 'required'],
            [['site_id', 'priority', 'is_blocked', 'parent_id', 'language_id', 'menu_id', 'section_id', 'is_all_section', 'is_all_menu'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['content', 'search_words', 'template_keys_json', 'seo_title', 'seo_description', 'seo_keywords'], 'string'],
            [['name', 'template'], 'string', 'max' => 255],
            [['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::className(), 'targetAttribute' => ['language_id' => 'id']],
            [['menu_id'], 'exist', 'skipOnError' => true, 'targetClass' => Menu::className(), 'targetAttribute' => ['menu_id' => 'id']],
            [['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => Content::className(), 'targetAttribute' => ['parent_id' => 'id']],
            [['section_id'], 'exist', 'skipOnError' => true, 'targetClass' => Section::className(), 'targetAttribute' => ['section_id' => 'id']],
            [['site_id'], 'exist', 'skipOnError' => true, 'targetClass' => Site::className(), 'targetAttribute' => ['site_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'site_id' => 'Site ID',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'priority' => 'Priority',
            'is_blocked' => 'Is Blocked',
            'parent_id' => 'Parent ID',
            'language_id' => 'Language ID',
            'menu_id' => 'Menu ID',
            'section_id' => 'Section ID',
            'is_all_section' => 'Is All Section',
            'is_all_menu' => 'Is All Menu',
            'name' => 'Name',
            'template' => 'Template',
            'content' => 'Content',
            'search_words' => 'Search Words',
            'template_keys_json' => 'Template Keys Json',
            'seo_title' => 'Seo Title',
            'seo_description' => 'Seo Description',
            'seo_keywords' => 'Seo Keywords',
        ];
    }

    /**
     * Gets query for [[Language]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getLanguage()
    {
        return $this->hasOne(Language::className(), ['id' => 'language_id']);
    }

    /**
     * Gets query for [[Menu]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMenu()
    {
        return $this->hasOne(Menu::className(), ['id' => 'menu_id']);
    }

    /**
     * Gets query for [[Parent]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getParent()
    {
        return $this->hasOne(Content::className(), ['id' => 'parent_id']);
    }

    /**
     * Gets query for [[Contents]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getContents()
    {
        return $this->hasMany(Content::className(), ['parent_id' => 'id']);
    }

    /**
     * Gets query for [[Section]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSection()
    {
        return $this->hasOne(Section::className(), ['id' => 'section_id']);
    }

    /**
     * Gets query for [[Site]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSite()
    {
        return $this->hasOne(Site::className(), ['id' => 'site_id']);
    }

    /**
     * Gets query for [[ContentOnMenus]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getContentOnMenus()
    {
        return $this->hasMany(ContentOnMenu::className(), ['content_id' => 'id']);
    }

    /**
     * Gets query for [[ContentOnSections]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getContentOnSections()
    {
        return $this->hasMany(ContentOnSection::className(), ['content_id' => 'id']);
    }
}
