<?php

use yii\db\Migration;
use yii\helpers\Console;

/**
 * Class m200429_063615_content_tables
 */
class m200429_063615_content_tables extends Migration
{
    private function _createIndex($table, $columns, $unique = false)
    {
        $_table = '{{%' . $table . '}}';
        
        if (!is_array($columns)) $columns = [$columns];

        $this->createIndex('idx-' . $table . '-' . implode('-', $columns), $_table, $columns, $unique);
    }

    private function _addForeignKey($table, $columns, $refTable, $refColumns, $delete = 'CASCADE', $update = 'CASCADE')
    {
        $_table = '{{%' . $table . '}}';
        $_refTable = '{{%' . $refTable . '}}';
        
        if (!is_array($columns)) $columns = [$columns];

        $this->addForeignKey('fk-' . $table . '-' . implode('-', $columns),
        $_table,
        $columns,
        $_refTable,
        $refColumns,
        $delete, $update);
    }

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        Yii::info("------> start migration");
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        //$host = 'reactyii.test'; // пока не знаю откуда это взять во время применения миграций
        $host = Console::input('Default host name [reactyii.test]:');
        if (!$host) $host = 'reactyii.test';

        // --------------------------------------------------------------------------------------------
        $_tn = 'site';
        $tn = '{{%' . $_tn . '}}';
        $_tableOptions = $tableOptions;
        if ($this->db->driverName === 'mysql') $_tableOptions .= " COMMENT='Сайты.'";
        $this->createTable($tn, [
            'id' => $this->bigPrimaryKey(),
            'created_at' => $this->datetime()->notNull()->comment('Created record date'),
            'updated_at' => $this->datetime()->comment('Last modified date'),
            'priority' => $this->integer()->defaultValue(100)->notNull()->comment('Поле для сортировки. По возрастанию'),
            'is_blocked' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('Для временного скрытия'),

            'name' => $this->string()->notNull(),
            'main_host' => $this->string()->notNull()->comment('Основной домен сайта - нужно для формирования ссылок с учетом разделов на поддоменах'),

            'template_layout' => $this->string()->defaultValue('default_layout')->comment('Шаблон для "макета" по умолчанию. Может быть определен как в шаблоне так и в таблице шаблонов. Каждый раздел и страница в свою очередь его может преопределить.'),
            'template_page' => $this->string()->defaultValue('default_template')->comment('Шаблон для всех страниц раздела по умолчанию. Может быть определен как в шаблоне так и в таблице шаблонов. Каждый раздел и страница в свою очередь его может преопределить.'),
            
            'settings_json' => $this->text()->comment('Настройки сайта в формате json. Справочник "строка" => "строка"'),
            //'html_entities_json' => $this->text()->comment('Сущности сайта (типа текст в футере, режим работы в шапке, логотип слева, справа)'),
        ], $_tableOptions);

        $this->_createIndex($_tn, ['priority']);
        $this->_createIndex($_tn, ['is_blocked']);

        // db->createCommand() можно и не использовать, но нам понадобится LastInsertID и хз на каких БД это может работать по другому (иногда нужен единый контекст вызова)
        $this/*->db->createCommand()*/->insert($tn, [
            'name' => 'Site',
            'created_at' => date('Y-m-d H:i:s'),
            'main_host' => $host,
        ]);
        $site_id = $this->db->getLastInsertID();
        Yii::info("------> site_id=" . $site_id);
        
        // --------------------------------------------------------------------------------------------
        $_tn = 'language';
        $tn = '{{%' . $_tn . '}}';
        $_tableOptions = $tableOptions;
        if ($this->db->driverName === 'mysql') $_tableOptions .= " COMMENT='Языки.'";
        $this->createTable($tn, [
            'id' => $this->bigPrimaryKey(),
            'site_id' => $this->bigInteger()->notNull(),
            'created_at' => $this->datetime()->notNull()->comment('Created record date'),
            'updated_at' => $this->datetime()->comment('Last modified date'),
            'priority' => $this->integer()->defaultValue(100)->notNull()->comment('Поле для сортировки. По возрастанию'),
            'is_blocked' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('Для временного скрытия'),

            'name' => $this->string()->notNull(),

            'path' => $this->string()->notNull()->comment('Путь для кодирования в урл'),

            'is_default' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('Признак для языка по умолчанию'), // более универсальный способ

            // переопределение сущностей сайта. каждый язык может переопределить сущности сайта
            // переводы сущностей будем сразу включать в исходный html_entities_json = {'ru' => {'footer_text' => '...'}}
            //'html_entities_json' => $this->text(),

            'messages_json' => $this->text()->comment('Фразы и сообщения'),
        ], $_tableOptions);

        $this->_createIndex($_tn, ['site_id']);
        $this->_createIndex($_tn, ['priority']);
        $this->_createIndex($_tn, ['is_blocked']);
        $this->_createIndex($_tn, ['path']);
        $this->_createIndex($_tn, ['is_default']);
        
        $this->_addForeignKey($_tn, 'site_id', 'site', 'id');  // при удалении сайта языки будут удалены!

        // --------------------------------------------------------------------------------------------
        $_tn = 'section';
        $tn = '{{%' . $_tn . '}}';
        $_tableOptions = $tableOptions;
        if ($this->db->driverName === 'mysql') $_tableOptions .= " COMMENT='Дерево разделов сайта.'";
        $this->createTable($tn, [
            'id' => $this->bigPrimaryKey(),
            'site_id' => $this->bigInteger()->notNull(),
            'created_at' => $this->datetime()->notNull()->comment('Created record date'),
            'updated_at' => $this->datetime()->comment('Last modified date'),
            'priority' => $this->integer()->defaultValue(100)->notNull()->comment('Поле для сортировки. По возрастанию'),
            'is_blocked' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('Для временного скрытия'),

            // defaultValue(-1) - так как NULL выпадает из индекса
            'parent_id' => $this->bigInteger()/*->notNull()->defaultValue(-1)*/->comment('Cразу для реализации дерева разделов. Иногда тут могут быть регионы и города.'),

            'name' => $this->string(1024)->notNull(), // это значение исключительно для админа, так как h1 будем всегда брать со страницы
            'menu_name' => $this->string(255), // если  = NULL, то будем брать со страницы

            'template_layout' => $this->string()->comment('Шаблон для "макета" по умолчанию. Каждая страница в свою очередь может преопределить'),
            'template_page' => $this->string()->comment('Шаблон для всех страниц раздела по умолчанию. Каждая страница в свою очередь может преопределить'),

            'path' => $this->string()->comment('путь для кодирования в урл'), // тут может быть нул!
            'host' => $this->string()->comment('поддомен, если заполнено (не равно нулу и не пустой строке), то раздел будет расположен в поддомене'), // тут может быть нул!

            //'html_entities_json' => $this->text()->comment('переопределение сущностей сайта. каждый раздел может переопределить сущности сайта'),
        ], $_tableOptions);

        $this->_createIndex($_tn, ['site_id']);
        $this->_createIndex($_tn, ['priority']);
        $this->_createIndex($_tn, ['is_blocked']);
        $this->_createIndex($_tn, ['parent_id']);

        $this->_createIndex($_tn, ['path']);
        $this->_createIndex($_tn, ['host']);
        $this->_createIndex($_tn, ['path', 'host'], true); // чтоб нельзя было создать 2 одинаковыx раздела
        
        $this->_addForeignKey($_tn, 'site_id', 'site', 'id');  // при удалении сайта разделы будут удалены!
        $this->_addForeignKey($_tn, 'parent_id', $_tn, 'id', 'SET NULL');  // при удалении родителя все его страницы будут премещены на верхний уровень

        // --------------------------------------------------------------------------------------------
        $_tn = 'menu';
        $tn = '{{%' . $_tn . '}}';
        $_tableOptions = $tableOptions;
        if ($this->db->driverName === 'mysql')
        {
            $_tableOptions .= " COMMENT='Дерево страниц сайта.<br/><br/>";
            $_tableOptions .= "Разделы.<br/>";
            $_tableOptions .= '1. Раздел по умолчанию "section_id" = NULL есть всегда. В нем расположена, как минимум, одна страница - главная сайта.<br/>';
            $_tableOptions .= '2. Важно понимать в каких разделах показывать страницу. Для этого у нас есть поля "is_all_section" и таблица "menu_on_section".<br/>';
            $_tableOptions .= '3. Важно понимать как отображать страницу (и ссылку на нее). Для этого у нас есть поля "section_id" и "is_current_section". Если "is_current_section" = 1, то страница будет показана так же как в том разделе в котором мы ее отрендерили. Например, юзер находится в разделе "section_1" мы и ссылку делаем так, как будто страница находится в этом разделе. Если "is_current_section"=1 и в поле "section_id" не NULL, то для canonical Url мы будем использовать указанный раздел.<br/>';
            $_tableOptions .= "'";

        }
        $this->createTable($tn, [
            'id' => $this->bigPrimaryKey(),
            'site_id' => $this->bigInteger()->notNull(),
            'created_at' => $this->datetime()->notNull()->comment('Created record date'),
            'updated_at' => $this->datetime()->comment('Last modified date'),
            'priority' => $this->integer()->defaultValue(100)->notNull()->comment('Поле для сортировки. По возрастанию'),
            'is_blocked' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('Для скрытия страниц'),

            'parent_id' => $this->bigInteger()->comment('Для реализации дерева страниц'),

            'section_id' => $this->bigInteger()->comment('Главный раздел в котором находится страница (для canonical). Если NULL, то это раздел по умолчанию. В некоторых шаблонах от раздела зависит дизайн страницы'),
            'is_all_section' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('Размещение во всех разделах. Например такие страницы как "Контакты", "Правила" в футере. И также, например, "Новости"'),
            'is_current_section' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('Размещение как в текущем разделе. Нужно для формирования ссылки. Например, в каждом разделе может быть страница "Новости" и "Фотки", но сам контент таких страниц зависит от раздела.'),
            
            'name' => $this->string(1024)->notNull()->defaultValue('')->comment('H1 страницы. Может быть пустая строка.'),
            'menu_name' => $this->string(255)->notNull()->defaultValue('')->comment('Название страницы в меню. Здесь не может быть пустой строки.'),

            'path' => $this->string()->comment('Путь для кодирования в урл. Если не NULL, то в меню отображается именно эта страница'), // тут может быть нул!
            'menu_id'  => $this->bigInteger()->comment('Линк на внутренню страницу. Если path is NULL, то в меню вставляем линк на внутренню страницу'),
            'url'  => $this->string(1024)->comment('Внешний URL. Если path is NULL and page_id is NULL, то в меню вставляем внешний линк и target="_blank"'),

            'search_words' => $this->text()->comment('Слова для поиска. При сохранении страницы здесь формируем список слов для поиска.'),
            'template_keys_json' => $this->text()->comment('Список ключей для вставки в шаблон (TOP_MENU,FOOTER_MENU,LEFT_MENU). Каждый пункт меню может располагаться в нескольких местах на странице (верхнее, нижнее и боковое меню).'),
            
            'seo_title' => $this->text()->comment('SEO Title'),
            'seo_description' => $this->text()->comment('SEO description meta tag'),
            'seo_keywords' => $this->text()->comment('SEO keywords meta tag'),

            'html_entities_json' => $this->text()->comment('Переопределение сущностей сайта. Каждая страница может переопределить сущности раздела (сайта)'),
        ], $_tableOptions);

        $this->_createIndex($_tn, ['site_id']);
        $this->_createIndex($_tn, ['priority']);
        $this->_createIndex($_tn, ['is_blocked']);
        $this->_createIndex($_tn, ['parent_id']);
        $this->_createIndex($_tn, ['path']);
        $this->_createIndex($_tn, ['section_id']);
        $this->_createIndex($_tn, ['section_id', 'path'], true); // чтоб нельзя было создать 2 одинаковыx раздела

        $this->_addForeignKey($_tn, 'site_id', 'site', 'id'); // при удалении сайта страницы будут удалены!
        $this->_addForeignKey($_tn, 'section_id', 'section', 'id', 'SET NULL'); // при удалении раздела все его страницы переходят в раздел по умолчанию (за уникальностью path будет следить уникальный индекс)
        $this->_addForeignKey($_tn, 'parent_id', $_tn, 'id', 'SET NULL'); // при удалении родителя все его страницы будут премещены на верхний уровень

        // --------------------------------------------------------------------------------------------
        $_tn = 'menu_on_section';
        $tn = '{{%' . $_tn . '}}';
        $_tableOptions = $tableOptions;
        if ($this->db->driverName === 'mysql') $_tableOptions .= " COMMENT='В данной таблице разместим информацию в каких разделах будем показывать страницы.'";
        $this->createTable($tn, [
            'id' => $this->bigPrimaryKey(),
            'site_id' => $this->bigInteger()->notNull(),
            
            'menu_id' => $this->bigInteger()->notNull()->comment('Страница'),
            'section_id' => $this->bigInteger()->comment('Id раздела в котором находится страница. Если NULL, то это раздел по умолчанию'),

        ], $_tableOptions);

        $this->_createIndex($_tn, ['site_id']);
        $this->_createIndex($_tn, ['menu_id']);
        $this->_createIndex($_tn, ['section_id']);
        
        $this->_addForeignKey($_tn, 'site_id', 'site', 'id');  // при удалении сайта связь будет удалена!
        $this->_addForeignKey($_tn, 'menu_id', 'menu', 'id'); // при удалении страницы удаляем связь
        $this->_addForeignKey($_tn, 'section_id', 'section', 'id'); // при удалении раздела удаляем связь

        // --------------------------------------------------------------------------------------------
        $_tn = 'template';
        $tn = '{{%' . $_tn . '}}';
        $_tableOptions = $tableOptions;
        if ($this->db->driverName === 'mysql') $_tableOptions .= " COMMENT='Шаблоны. Шаблоны в таблице могут преопределять шаблоны шаблона'";
        $this->createTable($tn, [
            'id' => $this->bigPrimaryKey(),
            'site_id' => $this->bigInteger()->notNull(),
            'created_at' => $this->datetime()->notNull()->comment('Created record date'),
            'updated_at' => $this->datetime()->comment('Last modified date'),
            'priority' => $this->integer()->defaultValue(100)->notNull()->comment('Поле для сортировки. По возрастанию'),

            'parent_id' => $this->bigInteger()->comment('Шаблон может быть составным. Здесь укажем ид родительского шаблона.'),

            'type' => $this->string(30)->notNull()->defaultValue('text')->comment('Для какого типа контента: page, layout, list, text, string, block, image ...'),

            'key' => $this->string()->notNull()->comment('Ключ шаблона для ссылки на него из других таблиц.'),
            'name' => $this->string()->notNull()->comment('Название шаблона для людей'),

            'template' => $this->text()->comment('Сам шаблон'),

            'settings_json' => $this->text()->comment('Настройки шаблона. Для списков число элементов на странице, для картинок параметры изображения.'),
            'key_entities_json' => $this->text()->comment('Список сущностей шаблона. Заполняем при сохранении без юзера.'),
        ], $_tableOptions);

        $this->_createIndex($_tn, ['site_id']);
        $this->_createIndex($_tn, ['priority']);
        $this->_createIndex($_tn, ['site_id', 'key'], true);
        
        $this->_addForeignKey($_tn, 'site_id', 'site', 'id');  // при удалении сайта шаблоны будут удалены!

        // --------------------------------------------------------------------------------------------
        $_tn = 'content';
        $tn = '{{%' . $_tn . '}}';
        $_tableOptions = $tableOptions;
        if ($this->db->driverName === 'mysql') $_tableOptions .= " COMMENT='Единица контента. Может содержать другие единицы контента'";
        $this->createTable($tn, [
            'id' => $this->bigPrimaryKey(),
            'site_id' => $this->bigInteger()->notNull(),
            'created_at' => $this->datetime()->notNull()->comment('Created record date'),
            'updated_at' => $this->datetime()->comment('Last modified date'),
            'priority' => $this->integer()->defaultValue(100)->notNull()->comment('Поле для сортировки. По возрастанию'),
            'is_blocked' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('Для временного скрытия'),

            'parent_id' => $this->bigInteger()->comment('В каком родительском элементе показать'),
            'language_id' => $this->bigInteger()->comment('Для какого языка данная единица. Если NULL, то для всех языков'),
            
            // даже для примитивов сделаем шаблоны, например в некоторых шаблонах текст надо вставлять в div, а число форматировать по разному.
            //'type' => $this->string(30)->notNull()->defaultValue('text')->comment('Тип единицы контента: list, text, string, block, image... Тип единицы однозначно определяет шаблон, но могут быть примитивы, например текст или число'),

            // где показывать контент - много ко многим!
            'menu_id' => $this->bigInteger()->comment('Главная страница где размещен контент. Пока не знаю нужно это или нет'),
            'section_id' => $this->bigInteger()->comment('Главный раздел в котором находится контент. Если NULL, то это раздел по умолчанию. Например, иногда бывает фишка, что сначала показываем новости раздела, а потом все остальные.'),
            'is_all_section' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('Для всех разделов'),
            'is_all_menu' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('Для всех страниц'),

            'name' => $this->string()->notNull(), // это значение исключительно для админа

            'template' => $this->string()->comment('Ссылка на шаблон для отрисовки данной единицы. Например, для списков или составных блоков. Если NULL, то вставляем как текст.'),

            'content' => $this->text()->comment('Сам контент.'),

            'search_words' => $this->text()->comment('Слова для поиска. При сохранении здесь формируем список слов для поиска.'),
            //'template_keys_json' => $this->text()->comment('Список ключей для вставки в родительский шаблон или для вставки на страницу.'),
        ], $_tableOptions);

        $this->_createIndex($_tn, ['site_id']);
        $this->_createIndex($_tn, ['priority']);
        $this->_createIndex($_tn, ['is_blocked']);
        $this->_createIndex($_tn, ['parent_id']);
        $this->_createIndex($_tn, ['language_id']);
        $this->_createIndex($_tn, ['section_id']);
        $this->_createIndex($_tn, ['menu_id']);
        $this->_createIndex($_tn, ['is_all_section']);
        $this->_createIndex($_tn, ['is_all_menu']);

        $this->_addForeignKey($_tn, 'site_id', 'site', 'id');  // при удалении сайта весь контент будет удален
        $this->_addForeignKey($_tn, 'parent_id', $_tn, 'id', 'SET NULL');  // при удалении родителя все его страницы будут премещены на верхний уровень

        $this->_addForeignKey($_tn, 'language_id', 'language', 'id');  // при удалении языка удалим весь контент к нему привязанный иначе будет каша разноязычного контента на странице
        $this->_addForeignKey($_tn, 'section_id', 'section', 'id', 'SET NULL'); // при удалении раздела единица контента не удаляется
        $this->_addForeignKey($_tn, 'menu_id', 'menu', 'id', 'SET NULL'); // при удалении страницы не удаляем

        // --------------------------------------------------------------------------------------------
        $_tn = 'content_on_section';
        $tn = '{{%' . $_tn . '}}';
        $_tableOptions = $tableOptions;
        if ($this->db->driverName === 'mysql') $_tableOptions .= " COMMENT='В данной таблице разместим информацию в каких разделах будем показывать единицу контента.'";
        $this->createTable($tn, [
            'id' => $this->bigPrimaryKey(),
            'site_id' => $this->bigInteger()->notNull(),
            
            'content_id' => $this->bigInteger()->notNull(),
            'section_id' => $this->bigInteger()->comment('Id раздела в котором находится контент. Если NULL, то это раздел по умолчанию'),

        ], $_tableOptions);

        $this->_createIndex($_tn, ['site_id']);
        $this->_createIndex($_tn, ['content_id']);
        $this->_createIndex($_tn, ['section_id']);
        
        $this->_addForeignKey($_tn, 'site_id', 'site', 'id');  // при удалении сайта связь будет удалены!
        $this->_addForeignKey($_tn, 'content_id', 'content', 'id'); // при удалении единицы удаляем связь
        $this->_addForeignKey($_tn, 'section_id', 'section', 'id'); // при удалении раздела удаляем связь

        // --------------------------------------------------------------------------------------------
        $_tn = 'content_on_menu';
        $tn = '{{%' . $_tn . '}}';
        $_tableOptions = $tableOptions;
        if ($this->db->driverName === 'mysql') $_tableOptions .= " COMMENT='В данной таблице разместим информацию на каких страницах будем показывать единицу контента.'";
        $this->createTable($tn, [
            'id' => $this->bigPrimaryKey(),
            'site_id' => $this->bigInteger()->notNull(),
            
            'content_id' => $this->bigInteger()->notNull()->comment('Страница'),
            'menu_id' => $this->bigInteger()->notNull()->comment('Cтраница где показываем контент.'),

        ], $_tableOptions);

        $this->_createIndex($_tn, ['site_id']);
        $this->_createIndex($_tn, ['content_id']);
        $this->_createIndex($_tn, ['menu_id']);
        
        $this->_addForeignKey($_tn, 'site_id', 'site', 'id');  // при удалении сайта связь будет удалены!
        $this->_addForeignKey($_tn, 'content_id', 'content', 'id'); // при удалении единицы удаляем связь
        $this->_addForeignKey($_tn, 'menu_id', 'menu', 'id'); // при удалении страницы удаляем связь

        // --------------------------------------------------------------------------------------------

        // --------------------------------------------------------------------------------------------
        // --------------------------------------------------------------------------------------------
        // создадим примеры шаблонов новости, факи, галереи картинок


    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        
        $this->dropTable('{{%content_on_section}}');
        $this->dropTable('{{%content_on_menu}}');

        $this->dropTable('{{%template}}');
        $this->dropTable('{{%content}}');

        $this->dropTable('{{%menu_on_section}}');
        $this->dropTable('{{%menu}}');
        $this->dropTable('{{%section}}');
        $this->dropTable('{{%language}}');
        $this->dropTable('{{%site}}');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200429_063615_content_tables cannot be reverted.\n";

        return false;
    }
    */
}
