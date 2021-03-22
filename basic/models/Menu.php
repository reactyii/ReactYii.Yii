<?php
namespace app\models;

use Yii;
use yii\caching\TagDependency;

/**
 * This is the model class for table "{{%menu}}".
 *
 * @property int $id
 * @property int $site_id
 * @property string $created_at Created record date
 * @property string|null $updated_at Last modified date
 * @property int $priority Поле для сортировки. По возрастанию
 * @property int $is_blocked Для скрытия страниц
 * @property int|null $parent_id Для реализации дерева страниц
 * @property int|null $section_id Главный раздел в котором находится страница (для canonical). Если NULL, то это раздел по умолчанию. В некоторых шаблонах от раздела зависит дизайн страницы
 * @property int $is_all_section Размещение во всех разделах. Например такие страницы как "Контакты", "Правила" в футере. И также, например, "Новости"
 * @property int $is_current_section Размещение как в текущем разделе. Нужно для формирования ссылки. Например, в каждом разделе может быть страница "Новости" и "Фотки", но сам контент таких страниц зависит от раздела.
 * @property string $name H1 страницы. Может быть пустая строка.
 * @property string $menu_name Название страницы в меню. Здесь не может быть пустой строки.
 * @property string|null $path Путь для кодирования в урл. Если не NULL, то в меню отображается именно эта страница
 * @property int|null $menu_id Линк на внутренню страницу. Если path is NULL, то в меню вставляем линк на внутренню страницу
 * @property string|null $url Внешний URL. Если path is NULL and page_id is NULL, то в меню вставляем внешний линк и target="_blank"
 * @property string|null $search_words Слова для поиска. При сохранении страницы здесь формируем список слов для поиска.
 * @property string|null $template_keys_json Список ключей для вставки в шаблон (TOP_MENU,FOOTER_MENU,LEFT_MENU). Каждый пункт меню может располагаться в нескольких местах на странице (верхнее, нижнее и боковое меню).
 * @property string|null $seo_title SEO Title
 * @property string|null $seo_description SEO description meta tag
 * @property string|null $seo_keywords SEO keywords meta tag
 * @property string|null $html_entities_json Переопределение сущностей сайта. Каждая страница может переопределить сущности раздела (сайта)
 *
 * @property Content[] $contents
 * @property ContentOnMenu[] $contentOnMenus
 * @property Menu $parent
 * @property Menu[] $menus
 * @property Section $section
 * @property Site $site
 * @property MenuOnSection[] $menuOnSections
 */
class Menu extends BaseModel
{

    /*public static function getAll(&$site)
    {
        $key = implode('-', [
            $site['id'],
            __CLASS__,
            __FUNCTION__
        ]);
        Yii::info("getAll. key=" . $key, __METHOD__);

        return Yii::$app->cache->getOrSet($key, function () use ($key, $site) {
            Yii::info("getAll. get from DB key=" . $key, __METHOD__);

            return self::find()->where([
                'site_id' => $site['id'],
                'is_blocked' => 0
            ])
                ->orderBy([
                'priority' => SORT_ASC,
                'id' => SORT_ASC
            ])
                ->asArray()
                ->all();
        }, null, new TagDependency([
            'tags' => [
                'site-' . $site['id'],
                'menus-' . $site['id']
            ]
        ]));
    }/**/

    // -------------------------------------------- auto generated -------------------------

    /**
     *
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%menu}}';
    }

    /**
     *
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [
                [
                    'site_id',
                    'created_at'
                ],
                'required'
            ],
            [
                [
                    'site_id',
                    'priority',
                    'is_blocked',
                    'parent_id',
                    'section_id',
                    'is_all_section',
                    'is_current_section',
                    'menu_id'
                ],
                'integer'
            ],
            [
                [
                    'created_at',
                    'updated_at'
                ],
                'safe'
            ],
            [
                [
                    'search_words',
                    'template_keys_json',
                    'seo_title',
                    'seo_description',
                    'seo_keywords',
                    'html_entities_json'
                ],
                'string'
            ],
            [
                [
                    'name',
                    'url'
                ],
                'string',
                'max' => 1024
            ],
            [
                [
                    'menu_name',
                    'path'
                ],
                'string',
                'max' => 255
            ],
            [
                [
                    'section_id',
                    'path'
                ],
                'unique',
                'targetAttribute' => [
                    'section_id',
                    'path'
                ]
            ],
            [
                [
                    'parent_id'
                ],
                'exist',
                'skipOnError' => true,
                'targetClass' => Menu::className(),
                'targetAttribute' => [
                    'parent_id' => 'id'
                ]
            ],
            [
                [
                    'section_id'
                ],
                'exist',
                'skipOnError' => true,
                'targetClass' => Section::className(),
                'targetAttribute' => [
                    'section_id' => 'id'
                ]
            ],
            [
                [
                    'site_id'
                ],
                'exist',
                'skipOnError' => true,
                'targetClass' => Site::className(),
                'targetAttribute' => [
                    'site_id' => 'id'
                ]
            ]
        ];
    }

    /**
     *
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
            'section_id' => 'Section ID',
            'is_all_section' => 'Is All Section',
            'is_current_section' => 'Is Current Section',
            'name' => 'Name',
            'menu_name' => 'Menu Name',
            'path' => 'Path',
            'menu_id' => 'Menu ID',
            'url' => 'Url',
            'search_words' => 'Search Words',
            'template_keys_json' => 'Template Keys Json',
            'seo_title' => 'Seo Title',
            'seo_description' => 'Seo Description',
            'seo_keywords' => 'Seo Keywords',
            'html_entities_json' => 'Html Entities Json'
        ];
    }

    /**
     * Gets query for [[Contents]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getContents()
    {
        return $this->hasMany(Content::className(), [
            'menu_id' => 'id'
        ]);
    }

    /**
     * Gets query for [[ContentOnMenus]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getContentOnMenus()
    {
        return $this->hasMany(ContentOnMenu::className(), [
            'menu_id' => 'id'
        ]);
    }

    /**
     * Gets query for [[Parent]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getParent()
    {
        return $this->hasOne(Menu::className(), [
            'id' => 'parent_id'
        ]);
    }

    /**
     * Gets query for [[Menus]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMenus()
    {
        return $this->hasMany(Menu::className(), [
            'parent_id' => 'id'
        ]);
    }

    /**
     * Gets query for [[Section]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSection()
    {
        return $this->hasOne(Section::className(), [
            'id' => 'section_id'
        ]);
    }

    /**
     * Gets query for [[Site]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSite()
    {
        return $this->hasOne(Site::className(), [
            'id' => 'site_id'
        ]);
    }

    /**
     * Gets query for [[MenuOnSections]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMenuOnSections()
    {
        return $this->hasMany(MenuOnSection::className(), [
            'menu_id' => 'id'
        ]);
    }
}
