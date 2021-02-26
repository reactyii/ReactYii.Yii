<?php
namespace app\assets;

use Yii;
use yii\web\AssetBundle;
use yii\helpers\FileHelper;

// линк на доки https://www.yiiframework.com/doc/guide/2.0/ru/structure-assets

/**
 * React application asset bundle.
 */
class ReactAsset extends AssetBundle
{

    // путь к скриптам реакта и стилям задаем через алиас в конфиге
    public $sourcePath = '@react';

    public $css = [];

    public $js = [];

    /**
     * Так как реакт собирает скрипты с хэшами, то мы не знаем имен скриптов и стилей и потому в рантайме будем делать поиск нужных имен
     * правила формирования см тут https://create-react-app.dev/docs/production-build
     *
     * main.[hash].chunk.js - This is your application code App.js, etc
     * [number].[hash].chunk.js - These files can either be vendor code, or code splitting chunks.
     * runtime-main.[hash].js - This is a small chunk of webpack runtime logic which is used to load and run your application.
     */
    public function init()
    {
        parent::init();

        // Yii::info("--> " . ': ' . $this->sourcePath, __METHOD__);
        $jss = FileHelper::findFiles($this->sourcePath . '/js', [
            'only' => [
                '*.js'
            ]
        ]);
        $csss = FileHelper::findFiles($this->sourcePath . '/css', [
            'only' => [
                '*.css'
            ]
        ]);

        // собираем список js бандлов
        $js_runtimemain = null; // должен быть первым
        $js_vendor = []; // вторым (может быть несколько? нужно ли сортировать? пока неясно не будем ничего оптимизирвоать)
        $js_main = null; // последним

        // array_walk потому что изначально пробовал через array_map(), а так вполне можно заменить на обычный цикл
        array_walk($jss, function ($item) use (&$js_runtimemain, &$js_vendor, &$js_main) {
            $n = basename($item);
            $tmp = explode('.', $n, 2);
            switch ($tmp[0]) {
                case 'main': // This is your application code. App.js, etc.
                    $js_main = 'js/' . $n;
                    break;

                case 'runtime-main': // This is a small chunk of webpack runtime logic which is used to load and run your application.
                    $js_runtimemain = 'js/' . $n;
                    break;

                default:
                    // Yii::info('--> $vendor founded ' . $n, __METHOD__);
                    $js_vendor[] = 'js/' . $n;
            }
        });

        // Yii::info('--> $js_vendor=' . var_export($js_vendor, true), __METHOD__);

        if ($js_runtimemain != null) {
            $this->js[] = $js_runtimemain; // надо бы инлайом его подключить, как в самом реакте сделано, но yii так не умеет %( пока оставим как есть
        }

        foreach ($js_vendor as $v)
            $this->js[] = $v;

        if ($js_main != null) {
            $this->js[] = $js_main;
        }

        // подключение стилей
        $this->css = array_map(function ($item) {
            $n = basename($item);
            return 'css/' . $n;
        }, $csss);

        // Yii::info('--> $js=' . var_export($this->js, true), __METHOD__);
    } /* */
}
