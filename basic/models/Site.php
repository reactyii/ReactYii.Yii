<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "{{%site}}".
 *
 * @property int $id
 * @property string $created_at Created record date
 * @property string|null $updated_at Last modified date
 * @property int $priority Поле для сортировки. По возрастанию
 * @property int $is_blocked Для временного скрытия
 * @property string $name
 * @property string $main_host Основной домен сайта - нужно для формирования ссылок с учетом разделов на поддоменах
 * @property string|null $template_layout Шаблон для "макета" по умолчанию. Может быть определен как в шаблоне так и в таблице шаблонов. Каждый раздел и страница в свою очередь его может преопределить.
 * @property string|null $template_page Шаблон для всех страниц раздела по умолчанию. Может быть определен как в шаблоне так и в таблице шаблонов. Каждый раздел и страница в свою очередь его может преопределить.
 * @property string|null $settings_json Настройки сайта в формате json. Справочник "строка" => "строка"
 *
 * @property Content[] $contents
 * @property ContentOnMenu[] $contentOnMenus
 * @property ContentOnSection[] $contentOnSections
 * @property Language[] $languages
 * @property Menu[] $menus
 * @property MenuOnSection[] $menuOnSections
 * @property Section[] $sections
 * @property Template[] $templates
 */
class Site extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%site}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['created_at', 'name', 'main_host'], 'required'],
            [['created_at', 'updated_at'], 'safe'],
            [['priority', 'is_blocked'], 'integer'],
            [['settings_json'], 'string'],
            [['name', 'main_host', 'template_layout', 'template_page'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'priority' => 'Priority',
            'is_blocked' => 'Is Blocked',
            'name' => 'Name',
            'main_host' => 'Main Host',
            'template_layout' => 'Template Layout',
            'template_page' => 'Template Page',
            'settings_json' => 'Settings Json',
        ];
    }

    /**
     * Gets query for [[Contents]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getContents()
    {
        return $this->hasMany(Content::className(), ['site_id' => 'id']);
    }

    /**
     * Gets query for [[ContentOnMenus]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getContentOnMenus()
    {
        return $this->hasMany(ContentOnMenu::className(), ['site_id' => 'id']);
    }

    /**
     * Gets query for [[ContentOnSections]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getContentOnSections()
    {
        return $this->hasMany(ContentOnSection::className(), ['site_id' => 'id']);
    }

    /**
     * Gets query for [[Languages]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getLanguages()
    {
        return $this->hasMany(Language::className(), ['site_id' => 'id']);
    }

    /**
     * Gets query for [[Menus]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMenus()
    {
        return $this->hasMany(Menu::className(), ['site_id' => 'id']);
    }

    /**
     * Gets query for [[MenuOnSections]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMenuOnSections()
    {
        return $this->hasMany(MenuOnSection::className(), ['site_id' => 'id']);
    }

    /**
     * Gets query for [[Sections]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSections()
    {
        return $this->hasMany(Section::className(), ['site_id' => 'id']);
    }

    /**
     * Gets query for [[Templates]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTemplates()
    {
        return $this->hasMany(Template::className(), ['site_id' => 'id']);
    }
}
