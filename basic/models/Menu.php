<?php
namespace app\models;

use Yii;
use yii\base\BaseObject;
use yii\caching\TagDependency;
use yii\helpers\Html;

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
 * @property string|null $content_keys_json Список ключей для вставки в шаблон (TOP_MENU,FOOTER_MENU,LEFT_MENU). Каждый пункт меню может располагаться в нескольких местах на странице (верхнее, нижнее и боковое меню).
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

    /*public static function getAll(&$session)
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

    public static function getAllForSelect(&$session, $parent = null, $fNameForValue = 'id', $fNameForTitle = 'name', $parentName = 'parent_id')
    {
        //$site = $session !== null && isset($session['site']) ? $session['site'] : null;
        $site = static::getSiteFromSession($session);
        $key = implode('-', [
            $site != null ? $site['id'] : '',
            $parent != null ? $parent : '',
            $fNameForValue, $fNameForTitle, $parentName,
            static::getCacheBaseKey(),
            __FUNCTION__
        ]);
        return Yii::$app->cache->getOrSet($key, function () use ($key, $site, $session, $parent, $fNameForValue, $fNameForTitle, $parentName) {
            Yii::info("getAll. get from DB key=" . $key, __METHOD__);
            $where = $site != null ? [
                static::tableName() . '.' . 'site_id' => $site['id'],
            ] : [];
            if ($parentName != null) $where[static::tableName() . '.' . $parentName] = $parent;

            $list = self::find()
                ->select(['id' => static::tableName() . '.' . $fNameForValue, 'content' => $fNameForTitle, 'section'=> 's.name'])
                ->where($where)
                ->join('LEFT JOIN', Section::tableName() . ' s', 'section_id = s.id')
                ->orderBy([
                    static::tableName() . '.' . 'priority' => SORT_ASC,
                    static::tableName() . '.' . 'id' => SORT_ASC
                ])
                ->asArray()
                ->all();

            foreach ($list as $k => $v) {
                $list[$k]['path'] = $v[$fNameForValue];
                $list[$k]['type'] = 'option';
                if ($v['section']) $list[$k]['content'] = $list[$k]['content'] . '('.$v['section'].')';
                if ($parentName != null) {
                    $list[$k]['childs'] = static::getAllForSelect($session, $v[$fNameForValue], $fNameForValue, $fNameForTitle, $parentName);
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

    /**
     * Готовим дерево меню для сайта
     * @param $session
     * @param $lang
     * @param array $filter
     * @return mixed
     * @throws \ErrorException
     */
    public static function getFilteredTree(&$session, &$lang, $filter = [])
    {
        //$site = $session !== null && isset($session['site']) ? $session['site'] : null;
        $site = static::getSiteFromSession($session);
        $_key = [
            $site['id'],
            $lang ? $lang['id'] : '',
        ];
        foreach ($filter as $k => $v)
        {
            $_key[] = $k . '=' . $v;
        }
        $_key[] = static::getCacheBaseKey();
        $_key[] = __FUNCTION__;
        $key = implode('-', $_key);
        Yii::info("getFilteredTree. key=" . $key, __METHOD__);

        $menu = Yii::$app->cache->getOrSet($key, function () use ($key, $site, $session, $lang, $filter) {
            Yii::info("getFilteredTree. get from DB key=" . $key, __METHOD__);
            $filter['site_id'] = $site['id'];

            $query = self::find()
                ->select('id, path, url, parent_id, name, menu_name, is_all_section, is_current_section, section_id, menu_id, content_keys_json')
                ->from(static::tablename())
                ->where($filter);

            $list = $query->orderBy([
                'priority' => SORT_ASC,
                'id' => SORT_ASC
            ])
                ->asArray()
                ->all();

            static::json_decode_list($list, ['content_keys_json' => 'content_keys']);

            // todo перевод !!! возможно сделаем таблицу связи (many to many) menu_translates и там будем хранить ссылки на переводы из таболицы контента или сделаем таблицу translates как в 2garin.com
            // или просто будем в талицу контента делать записи (возможно добавим туда признак что это перевод названия пункта меню и SEO которое и так есть в контенте)

            //Yii::info("getContentForPage. source list=" . var_export($list, true), __METHOD__);

            $list = static::listToHash($list);

            //Yii::info("getContentForPage. hash=" . var_export($list, true), __METHOD__);

            return static::hashToTree($list);
        }, null, new TagDependency([
            'tags' => [
                'site-' . $site['id'],
                static::getCacheBaseKey() . '-' . $site['id'],
            ]
        ]));

        return $menu;
    }

    public static function get500BySection(&$session, &$lang, $section, $e, $tags=[])
    {
        $message = $e->getMessage();
        if (YII_ENV_DEV) {
            // сильно сбивает сообщение (в отладке) если генерим 500.html страницу для SSR (я помню что мы здесь в YII_ENV_DEV)
            $request = Yii::$app->request;
            //Yii::info("get500BySection for userAgent=" . $request->userAgent);
            if ($request->userAgent != 'ReactSnap') {
                $message .= "\n" . $e->getFile() . ' (' . $e->getLine() . ")\n" . $e->getTraceAsString();
            }
        }
        $template_key = 'Error500,Error';
        $ctype = 'error';
        $ccontent = ['Oops fatal error' . ($message ? ': ' . nl2br(Html::encode($message)) : '!')];

        return static::getErrorPageBySection($session, $lang, $section, '500', $template_key, $ctype, $ccontent, $tags);
    }

    public static function get404BySection(&$session, &$lang, $section, $tags=[])
    {
        $template_key = 'Error404,Error';
        $ctype = 'error';
        $ccontent = ['Not founded.', 'Not founded. (Content for default 404 not founded.)'];

        return static::getErrorPageBySection($session, $lang, $section, '404', $template_key, $ctype, $ccontent, $tags);
    }

    public static function getErrorPageBySection(&$session, &$lang, $section, $code, $template_key, $ctype, $ccontent, $tags=[])
    {
        try {
            $page = static::getItemBySectionPage($session, $section, $code, $tags);
        } catch (\Throwable $e) { // For PHP 7
            $page = false;
        } catch (\Exception $e) { // For PHP 5
            $page = false;
        }

        if (!$page) { // этой страницы может не быть в БД, а можеть быть для каждого раздела своя кастомизированная
            $page = [];

            // todo fill seo by default for default 404 page
            $page['seo'] = [

            ];
            $page['section'] = $section;
            $page['lang'] = $lang;
            $content = [
                [
                    'id' => '-' . $code,
                    'content' => $ccontent[0],
                    'template_key' => $template_key,
                    'type' => $ctype,
                    //'content_keys' => $_item['content_keys'], // с исходного элемента!
                ]
            ];

        } else {
            $parts = [];
            $get = null;
            $post = null;
            try {
                $content = Content::getContentForPage($session, $lang, $section, $page, $parts, $get, $post);
            } catch (\Exception $e) {
                // здесь мы не должны возвращать 404! но в dev режиме (! to do?) мы можем выдать и 500
                $content = [
                    [
                        'id' => '-' . $code,
                        'content' => sizeof($ccontent) > 1 ? $ccontent[0] : $ccontent[1],
                        'template_key' => $template_key,
                        'type' => $ctype,
                    ]
                ];
            }

        }
        static::fillContentFromMenu($session, $lang, $content);

        $page['content'] = $content;

        return $page;
    }

    /**
     * Делаем поиск страницы с по урлу с учетом раздела
     *
     * @param $session
     * @param $section
     * @param $page_path
     * @param array $tags
     * @return mixed
     * @throws \ErrorException
     */
    public static function getItemBySectionPage(&$session, $section, $page_path, $tags=[])
    {
        //$site = $session !== null && isset($session['site']) ? $session['site'] : null;
        $site = static::getSiteFromSession($session);
        $where = ['path=:page', 'is_blocked=0'];
        $key = 'path=' . $page_path . ',is_blocked=0';
        $whereParams = [':page' => $page_path];
        if ($site['sections']) // с учетом раздела делаем поиск тока если разделы на сайте определены
        {
            if (!$section) // если раздел был в пути, то берем его
                           // а вот тут надо взять раздел по умолчанию (это раздел у которого path и host пустые или нул)
            {
                foreach ($site['sections'] as $s)
                {
                    if (!$s['path'] && !$s['host'])
                    {
                        $section = $s;
                        break;
                    }
                }
            }

            if ($section)
            {
                // /news.html
                //$where['section_id'] = $section['id']; // or is_all_section = 1
                //$where[] = ['or', ['section_id' => $section['id']], [['is_all_section' => 1]]];
                $where[] = '(section_id=:section or is_all_section=1)';
                $whereParams[':section'] = $section['id'];
                $key .= ',section_id=' . $section['id'] . ',is_all_section=1';
            }
            else // если раздел (скорее всего нет раздела по умолчанию) не нашли, то по идее ошибка можно писнуть в логи (но ошибка не критичная можем продолжать)
            {
                // это не ошибка. основной раздел не прописывается в таблице
                //Yii::error('У сайта отсутствует раздел по умолчанию', __METHOD__);
                $where[] = 'section_id is null or is_all_section=1'; // а вот у страницы у нас тут в обязательном порядке null
                $key .= ',section_id=null,is_all_section=1';
            }
        }

        return static::getItemByField($session, $where, $whereParams, $key, $tags);
    }

    /**
     * заполняем $session['site']['menus']
     * также дополняем $content контентом для формирования менюшек
     *
     * @param $session
     * @param $lang
     * @param $content
     * @throws \ErrorException
     */
    public static function fillContentFromMenu(&$session, &$lang, &$content)
    {
        // заполняем после парса урла (нам нужен lang для перевода менюшек)
        $session['site']['menus'] = static::getFilteredTree($session, $lang, ['is_blocked' => 0]);

        $menusContent = static::getContentFromMenu($session, $lang,$session['site']['menus']);
        // заменить на https://www.php.net/manual/ru/function.array-merge.php
        foreach ($menusContent as $c) {
            //Yii::info('fillContentFromMenu:' . var_export($c, true), __METHOD__);
            $content[] = $c;
        }
    }

    /**
     * Формируем единицы контента из меню. рекурсия!
     * @param $session
     * @param $lang
     * @param $menu
     * @return array
     */
    private static function getContentFromMenu(&$session, &$lang, &$menu)
    {
        $content = [];
        foreach ($menu as $_item) {
            $item = ($_item['menu_id']) ? static::getItemById($session, $_item['menu_id']) : $_item;

            $ii = [
                'id' => $_item['id'],  // с исходного элемента!
                'content' => $_item['menu_name'],//'', // вот передать название ссылки через контент или через settings? еще подумаем
                'type' => 'link',
                'template_key' => 'A', // а надо ли еще какие шаблоны указать или шаблон сам решит как отобразить сей линк?
                //'content_keys' => $_item['content_keys'], // с исходного элемента!
                'settings' => [
                    //'menu_name' => $_item['menu_name'], // название в ссылке возьмем с иходного элемента
                    // может быть что то еще взять с исходного элемента ? надо подумать
                    'is_all_section' => $item['is_all_section'],
                    'is_current_section' => $item['is_current_section'],
                    'path' => $item['path'],
                    'section_id' => $item['section_id'],
                    //'' => $item[''],
                    //'' => $item[''],
                    'url' => $_item['url'], // внешний урл. с исходного элемента! так как это первое проверяем и если оно не пусто то сразу лепим внешний линк

                ],
                //'childs' => $item['childs'] ? static::getContentFromMenu($session, $lang, $item['childs']) : [],
            ];
            if (isset($_item['content_keys'])) {
                $ii['content_keys'] = $_item['content_keys'];
            }
            if (isset($item['childs'])) {
                $ii['childs'] = static::getContentFromMenu($session, $lang, $item['childs']);
            }
            $content[] = $ii;
        }
        return $content;
    }

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
                    'content_keys_json',
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
            'content_keys_json' => 'Content Keys Json',
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
