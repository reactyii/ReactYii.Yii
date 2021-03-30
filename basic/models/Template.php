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
    // ----------------------- атомарные единицы контента - примитивы
    // не будем делать отдельный тип для группировки
    //const TYPE_CONTENT = 'content'; // сделаем возможность группировки единиц контента. короче нода с этим типом может содержать другие единицы контента

    // так как данный тип по умолчанию, то константа для его обозначения не нужна. в таблице контента вместо шаблона всегда будет указан NULL
    // если единица контента имеет дочерние узлы и заполненное поле content, то пока будем отображать дочерние узлы (поле content игнорируем)
    // кстати это вриант для переводов. перевод на языки идет дочерним узлом. потестируем уточним удобно ли так будет пока хз
    //const TYPE_HTML = 'html'; // этот тип будет тип по умолчанию. также данный тип будем использовать для группировки других единиц контента

    const TYPE_NUMBER = 'number'; // число. параметры отображения в настройках типа и единицы контента
    const TYPE_STRING = 'string'; // простая строка без html атрибутов. может имет чилдов с переводами
    const TYPE_DATE = 'date'; // тип дата
    const TYPE_TIME = 'time'; // тип время
    const TYPE_DATETIME = 'datetime'; // тип дата время
    const TYPE_IMAGE = 'image'; // картинка
    const TYPE_FILE = 'file'; // файл

    // ----------------------- сложные единицы контента. всегда содержат дочерние элементы
    const TYPE_LIST = 'list'; // тип список с пагинатором! с переходом на страницу с единицей окнтента (статьи, новости, списки итемов в админке)
    const TYPE_IMGGALLERY = 'img_gallery'; // галерея картинок

    // ----------------------- формы и их элементы
    const TYPE_FIELD_SELECT = 'field_select'; // список опций (может иметь атрибут "multiple", также может иметь атрибут "view" равный "select" или "radiocheckbox" (списко чекбоксов или радио кнопок в зависимости от значение "multiple"))
    const TYPE_FIELD_OPTION = 'field_option'; // опция всегда дочерняя для ноды с типом TYPE_FIELD_SELECT
    //const TYPE_FIELD_CHECKBOX = ''; //
    //const TYPE_FIELD_RADIO = ''; //
    //const TYPE_FIELD_GROUP_RADIO = ''; //

    const TYPE_FIELD_INPUT = 'field_input'; // type=password, text, number, hidden ...
    const TYPE_FIELD_TEXTAREA = 'field_textarea'; //

    const TYPE_FORM = 'form'; // форма всегда содержит TYPE_FIELD_* но всегда в прямых потомках, элементы формы могут быть глубоко в потомках потомков
    const TYPE_FIELD_GROUP = 'fieldgroup'; // группа полей формы, может быть списком группы (пример список адресов доставки ("адрес", "получатель") добавить группу удалить)


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
