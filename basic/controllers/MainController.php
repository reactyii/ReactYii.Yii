<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;

class MainController extends Controller
{

    /**
     * {@inheritdoc}
     */
    /*public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }/* */

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        $request = Yii::$app->request;
        $path = $request->pathInfo;
        Yii::info("start: " . $path, __METHOD__);

        if ($request->isAjax) { /* текущий запрос является AJAX запросом */ }

        $response = Yii::$app->response;
        $response->format = \yii\web\Response::FORMAT_JSON;
        $response->data = ['message' => 'hello world', 'path' => $path];

        //return $this->render('index');
    }

    /**
     * Определяем язык, раздел и страницу которую запросил юзер.
     * Правила
     * 1. Раздел может быть определен в домене
     * 2. На первом месте в пути у нас может быть указан язык
     * 3. Если раздел не указан в домене, то следующим после языка (или если его нет, то первым) может быть указан раздел
     * 4. Следующим идет страница.
     * 5. Может быть не указано ничего. Если не указан язык, то используем язык по умолчанию, также с разделом и страницей.
     * 6. Для простоты условимся что символ '/' не может быть использован в языке и разделе, но может быть использован в странице
     * 7. если $path есть, а страницы с таким именем нет, то выдаем 404
     * 8. Также проверим $host он должен быть равен базовому хосту (если базовый хост прописан в конфиге) или разделу, для корректной выдачи 404 для поисковиков
     *    Если базовый хоост не определен в конфиге, то просто пытаемся отрезолвить раздел по домену
     *
     * @return string
     */
    private function parsePath($host, $path)
    {
        $result = [];
        $parts = explode('/', $path);
        
        // 2. начнем с резолва языка, еслит он есть, то он занимает первую часть пути
        //$result['lang'] = $this->

    }

}
