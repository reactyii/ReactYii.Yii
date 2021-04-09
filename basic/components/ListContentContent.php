<?php

namespace app\components;
use yii;
use app\models\Content;
use yii\base\BaseObject;

class ListContentContent extends ListContentBase
{
    protected $id = 'content'; // это значение указывается в Content->model у единицы контента типа список
    /*public $prop1;
    public $prop2;

    public function __construct($config = [])
    {
        // ... инициализация происходит перед тем, как будет применена конфигурация.

        parent::__construct($config);

        //Yii::info('ListContentContent::__construct() $config=', var_export($config, true), '1');
    }

    public function init()
    {
        parent::init();

        Yii::info('ListContentContent::init()', __METHOD__);

        // ... инициализация происходит после того, как была применена конфигурация.

        Content::registerContentList();
    }/**/

    function getContentForList(&$site, &$lang, &$section, &$page, $listContent, $offset, $limit, $item = null)
    {

    }
}
