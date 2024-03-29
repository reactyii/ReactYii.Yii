<?php

namespace app\models;

use Yii;
use yii\caching\TagDependency;

/**
 * This is the model class for table "{{%section}}".
 *
 * @property int $id
 * @property int $site_id
 * @property string $created_at Created record date
 * @property string|null $updated_at Last modified date
 * @property int $priority Поле для сортировки. По возрастанию
 * @property int $is_blocked Для временного скрытия
 * @property int|null $parent_id Cразу для реализации дерева разделов. Иногда тут могут быть регионы и города.
 * @property string $name
 * @property string|null $menu_name
 * @property string|null $template_layout Шаблон для "макета" по умолчанию. Каждая страница в свою очередь может преопределить
 * @property string|null $template_page Шаблон для всех страниц раздела по умолчанию. Каждая страница в свою очередь может преопределить
 * @property string|null $path путь для кодирования в урл
 * @property string|null $host поддомен, если заполнено (не равно нулу и не пустой строке), то раздел будет расположен в поддомене
 *
 * @property Content[] $contents
 * @property ContentOnSection[] $contentOnSections
 * @property Menu[] $menus
 * @property MenuOnSection[] $menuOnSections
 * @property Section $parent
 * @property Section[] $sections
 * @property Site $site
 */
class Section extends BaseModel
{
    /**
     * Делаем поиск страницы с по урлу с учетом раздела
     *
     */
    public static function getItemByPath(&$session, $path)
    {
        return Section::getItemByField($session, ['path=:path', 'is_blocked=0'], [':path' => $path], 'path=' . $path . ',is_blocked=0');
    }

    /**
     * Готовим дерево меню для сайта
     *
     */
    public static function getFiltered(&$session, $filter = [])
    {
        //$site = $session !== null && isset($session['site']) ? $session['site'] : null;
        $site = static::getSiteFromSession($session);
        $_key = [
            $site != null ? $site['id'] : '',
            // пока преводить нечего
            //$lang ? $lang['id'] : '',
        ];
        foreach ($filter as $k => $v)
        {
            $_key[] = $k . '=' . $v;
        }
        $_key[] = static::getCacheBaseKey();
        $_key[] = __FUNCTION__;
        $key = implode('-', $_key);
        Yii::info("getFilteredTree. key=" . $key, __METHOD__);

        $menu = Yii::$app->cache->getOrSet($key, function () use ($key, $site, $session, $filter) {
            Yii::info("getFilteredTree. get from DB key=" . $key, __METHOD__);
            $filter['site_id'] = $site['id'];

            $query = self::find()
                ->select('id, path, host, parent_id, name')
                ->from(static::tablename())
                ->where($filter);

            $list = $query->orderBy([
                'priority' => SORT_ASC,
                'id' => SORT_ASC
            ])
                ->asArray()
                ->all();

            return $list;
            //static::json_decode_list($list, ['content_keys_json' => 'content_keys']);

            // тут переводить нечего

            //Yii::info("getContentForPage. source list=" . var_export($list, true), __METHOD__);

            //$list = static::listToHash($list);

            //Yii::info("getContentForPage. hash=" . var_export($list, true), __METHOD__);

            //return static::hashToTree($list);
        }, null, new TagDependency([
            'tags' => [
                'site-' . $site['id'],
                static::getCacheBaseKey() . '-' . $site['id'],
            ]
        ]));

        return $menu;
    }

    // -------------------------------------------- auto generated -------------------------

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%section}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['site_id', 'created_at', 'name'], 'required'],
            [['site_id', 'priority', 'is_blocked', 'parent_id'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['name'], 'string', 'max' => 1024],
            [['menu_name', 'template_layout', 'template_page', 'path', 'host'], 'string', 'max' => 255],
            [['path', 'host'], 'unique', 'targetAttribute' => ['path', 'host']],
            [['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => Section::className(), 'targetAttribute' => ['parent_id' => 'id']],
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
            'name' => 'Name',
            'menu_name' => 'Menu Name',
            'template_layout' => 'Template Layout',
            'template_page' => 'Template Page',
            'path' => 'Path',
            'host' => 'Host',
        ];
    }

    /**
     * Gets query for [[Contents]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getContents()
    {
        return $this->hasMany(Content::className(), ['section_id' => 'id']);
    }

    /**
     * Gets query for [[ContentOnSections]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getContentOnSections()
    {
        return $this->hasMany(ContentOnSection::className(), ['section_id' => 'id']);
    }

    /**
     * Gets query for [[Menus]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMenus()
    {
        return $this->hasMany(Menu::className(), ['section_id' => 'id']);
    }

    /**
     * Gets query for [[MenuOnSections]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMenuOnSections()
    {
        return $this->hasMany(MenuOnSection::className(), ['section_id' => 'id']);
    }

    /**
     * Gets query for [[Parent]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getParent()
    {
        return $this->hasOne(Section::className(), ['id' => 'parent_id']);
    }

    /**
     * Gets query for [[Sections]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSections()
    {
        return $this->hasMany(Section::className(), ['parent_id' => 'id']);
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
}
