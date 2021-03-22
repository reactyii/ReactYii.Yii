<?php
namespace app\models;

use Yii;
use yii\caching\TagDependency;

/**
 * This is the model class for table "{{%language}}".
 *
 * @property int $id
 * @property int $site_id
 * @property string $created_at Created record date
 * @property string|null $updated_at Last modified date
 * @property int $priority Поле для сортировки. По возрастанию
 * @property int $is_blocked Для временного скрытия
 * @property string $name
 * @property string $path Путь для кодирования в урл
 * @property int $is_default Признак для языка по умолчанию
 * @property string|null $messages_json Фразы и сообщения
 *
 * @property Content[] $contents
 * @property Site $site
 */
class Language extends BaseModel
{
    protected static $getAllOrderBy = [
        'is_default' => SORT_DESC, // '1' первым шоб затем нули
        'priority' => SORT_ASC,
        'id' => SORT_ASC
    ];

    /*public static function getAll(&$site)
    {
        $key = implode('-', [
            $site['id'],
            __CLASS__,
            __FUNCTION__
        ]);
        Yii::info("getAll. key=" . $key, __METHOD__);

        return Yii::$app->cache->getOrSet($key, function () use ($key, $site) {
            Yii::info("getAll. get from DB for key=" . $key, __METHOD__);

            return self::find()->where([
                'site_id' => $site['id'],
                'is_blocked' => 0
            ])
                ->orderBy([
                'is_default' => SORT_DESC, // '1' первым шоб затем нули
                'id' => SORT_ASC
            ])
                ->asArray()
                ->all();
        }, null, new TagDependency([
            'tags' => [
                'site-' . $site['id'],
                'languages-' . $site['id']
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
        return '{{%language}}';
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
                    'created_at',
                    'name',
                    'path'
                ],
                'required'
            ],
            [
                [
                    'site_id',
                    'priority',
                    'is_blocked',
                    'is_default'
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
                    'messages_json'
                ],
                'string'
            ],
            [
                [
                    'name',
                    'path'
                ],
                'string',
                'max' => 255
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
            'name' => 'Name',
            'path' => 'Path',
            'is_default' => 'Is Default',
            'messages_json' => 'Messages Json'
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
            'language_id' => 'id'
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
}
