<?php

namespace app\components;
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

    abstract function getContentForList(&$site, &$lang, &$section, &$page, $listContent, $offset, $limit, $item = null);
}
