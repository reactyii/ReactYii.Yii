<?php

namespace app\components;
use yii;
use app\models\Content;
use yii\base\BaseObject;

abstract class ListContentBase extends BaseObject
{
    protected $id = ''; // это значение указывается в Content->model у единицы контента типа список

    public function __construct($config = [])
    {
        // ... инициализация происходит перед тем, как будет применена конфигурация.

        parent::__construct($config);
    }

    public function init()
    {
        parent::init();

        // ... инициализация происходит после того, как была применена конфигурация.

        Content::registerContentList($this->id, $this);
    }

    /*
     * Добавляем условия фильтра в запрос
     */
    public function addFiltersFromFormData(&$query, &$formData, &$fields)
    {
        if ($formData) {
            //Yii::info('----------$formData=' . var_export($formData, true), __METHOD__);
            foreach ($formData as $name => $value) {
                // обязательн опроверяем а есть ли у нас такое поле в фильтре если нет, то 404
                //Yii::info('----------(field)$name=' . $name, __METHOD__);
                if (!isset($fields[$name])) throw new \yii\web\NotFoundHttpException();
                $isMultiple = isset($fields[$name]['settings']['multiple']) && $fields[$name]['settings']['multiple'];
                $type = isset($fields[$name]['settings']['type']) ? $fields[$name]['settings']['type'] : '';
                if ($isMultiple) {
                    // проверить а массив ли у нас в гете
                }
                $tableFieldName = isset($fields[$name]['settings']['tablefieldname']) ? $fields[$name]['settings']['tablefieldname'] : $name;
                switch ($type) {
                    case 'text':
                        $query->andWhere(['like', $tableFieldName, $value]);
                        //$query->andWhere(['like', $tableFieldName, '%' . str_replace('%','\\%',$value), false]); // чтобы сделать `c`.`name` LIKE '%sfd\%s'
                        break;
                    default:
                        $query->andWhere([$tableFieldName => $value]);
                }
            }
        }

        return $query;
    }

    abstract function getContentForList(&$session, &$lang, &$section, &$page, $listContent, &$content_args, &$get = null, &$post = null, $offset = 0, $limit = null, $item = null, $recursion_level = 0);

    abstract function getContentForListFilter(&$session, &$lang, &$section, &$page, $listContent, &$content_args);

    function getContentForItemEdit(&$session, &$lang, &$section, &$page, $listContent, &$get, &$post)
    {
        return false;
    }

}
