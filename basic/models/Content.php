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

        $contentList = Yii::$app->cache->getOrSet($key, function () use ($key, $site, $page, $section, $content_args) {
            Yii::info("getContentForPage. get from DB key=" . $key, __METHOD__);

            $query = self::find()
                //->select('c.*, t.type, t.settings_json')
                // так как у нас контентов может быть много и все они сериализуются и идут на фронт то делаем сразу оптимизацию и убираем все лишнее
                // названия полей таблицы НЕ будем делать короче (все современные браузеры поддерживают сжатие ответа сервера)
                ->select('c.id, c.priority, c.parent_id, c.path, c.content, c.template_keys_json, c.settings_json, t.type, t.settings_json as template_settings_json') // нужны ли language_id section_id menu_id
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

        if ($contentList) // если хоть что-то есть в списке
        {
            foreach ($contentList as $k=>$c)
            {
                // если тип контента это список, то в $content_args у нас могут быть переданы параметры типа номер текущей страницы или имя конкретного элемента
                if ($c['type'] == 'list') // $c['type'] это тип шаблона
                {
                    //if()

                }
                //$contentList[$k]['childs'] = static::getContentForPage($site, $lang, $section, $page, $content_args, $c['id'], $recursion_level + 1);
            }

            // а вот после прохождения всего списка у нас должен быть абсолютно пустой $content_args и если это не так, то мы должны выдать 404, чтоб поисковики не ползали там где не надо
            if (sizeof($content_args) > 0)
            {
                throw new \yii\web\NotFoundHttpException();
            }
        }

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
