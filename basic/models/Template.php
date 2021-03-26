<?php

namespace app\models;

use Yii;
use yii\caching\TagDependency;

/**
 * This is the model class for table "{{%template}}".
 *
 * @property int $id
 * @property int $site_id
 * @property string $created_at Created record date
 * @property string|null $updated_at Last modified date
 * @property int $priority Поле для сортировки. По возрастанию
 * @property int|null $parent_id Шаблон может быть составным. Здесь укажем ид родительского шаблона.
 * @property string $type Для какого типа контента: page, layout, list, text, string, block, image ...
 * @property string $key Ключ шаблона для ссылки на него из других таблиц.
 * @property string $name Название шаблона для людей
 * @property string|null $template Сам шаблон
 * @property string|null $settings_json Настройки шаблона. Для списков число элементов на странице, для картинок параметры изображения.
 * @property string|null $key_entities_json Список сущностей шаблона. Заполняем при сохранении без юзера.
 *
 * @property Site $site
 */
class Template extends BaseModel
{
    const TYPE_LIST = 'list';
    const TYPE_TEXT = 'text';
    const TYPE_NUMBER = 'number';
    const TYPE_STRING = 'string';
    const TYPE_DATE = 'date';
    //const TYPE_ = '';

    // -------------------------------------------- auto generated -------------------------
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%template}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['site_id', 'created_at', 'key', 'name'], 'required'],
            [['site_id', 'priority', 'parent_id'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['template', 'settings_json', 'key_entities_json'], 'string'],
            [['type'], 'string', 'max' => 30],
            [['key', 'name'], 'string', 'max' => 255],
            [['site_id', 'key'], 'unique', 'targetAttribute' => ['site_id', 'key']],
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
            'parent_id' => 'Parent ID',
            'type' => 'Type',
            'key' => 'Key',
            'name' => 'Name',
            'template' => 'Template',
            'settings_json' => 'Settings Json',
            'key_entities_json' => 'Key Entities Json',
        ];
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
