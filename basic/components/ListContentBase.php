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
        if ($formData)
        {
            //Yii::info('----------$formData=' . var_export($formData, true), __METHOD__);
            foreach ($formData as $name => $value)
            {
                // обязательн опроверяем а есть ли у нас такое поле в фильтре если нет, то 404
                if (!isset($fields[$name])) throw new \yii\web\NotFoundHttpException();
                $isMultiple = isset($fields[$name]['settings']['multiple']) && $fields[$name]['settings']['multiple'];
                if ($isMultiple)
                {
                    // проверить а массив ли у нас в гете
                }
                $tableFieldName = isset($fields[$name]['settings']['tablefieldname']) ? $fields[$name]['settings']['tablefieldname'] : $name;
                $query->andWhere([$tableFieldName => $value]);
            }
        }

        return $query;
    }

    abstract function getContentForList(&$site, &$lang, &$section, &$page, $listContent, &$content_args, $offset, $limit, $item = null, $recursion_level = 0);

    abstract function getContentForListFilter(&$site, &$lang, &$section, &$page, $listContent, &$content_args);
}
