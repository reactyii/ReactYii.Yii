<?php

namespace app\models;

use Yii;

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
 * @property string|null $search_words Слова для поиска. При сохранении здесь формируем список слов для поиска.
 * @property string|null $template_keys_json Список ключей для вставки в родительский шаблон или для вставки на страницу.
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
class Content extends \yii\db\ActiveRecord
{
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
            [['template', 'search_words', 'template_keys_json'], 'string'],
            [['name'], 'string', 'max' => 255],
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
            'search_words' => 'Search Words',
            'template_keys_json' => 'Template Keys Json',
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
