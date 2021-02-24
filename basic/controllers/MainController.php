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
        Yii::info("--------------------start " . $request->method . ': ' . $path, __METHOD__);

        if (!$this->checkCORS($request))
        {
            return; // ответ уже выслан или ничего не надо посылать
        }

        Yii::info('continue afterCORS', __METHOD__);

        if ($request->isAjax)
        {
            Yii::info('prepare json data for page', __METHOD__);

            $response = Yii::$app->response;
            $response->format = \yii\web\Response::FORMAT_JSON;
            $response->data = ['message' => 'hello world', 'path' => $path];
            return;
        }

        Yii::info('render only react container', __METHOD__);

        return $this->render('index');
    }

    /**
     * check CORS and set Access-Control-Allow-Origin header
     *
     * @return boolean
     */
    private function checkCORS(&$request)
    {
        $headers = $request->headers;
        $origin = $headers->get('Origin');
        if (!$origin) return true; // нет заголовка для проверки. ничего делать не нужно

        //Yii::info('host: ' . $request->userHost . '; ip: ' . $request->userIP, __METHOD__);
        Yii::info('Origin: ' . $origin, __METHOD__); // http://localhost:3000
        list($proto, $hostport) = explode('//', $origin, 2);
        list($host, $port) = explode(':', $hostport, 2); 
        $allwedOrigins = ['localhost']; // разрешенные хосты для аякс реквестов

        if (!in_array($host, $allwedOrigins)) // запрос не с нашего сайта. выдадим ответ not allowed
        { 
            throw new \yii\web\MethodNotAllowedHttpException;
            return false;
        }

        // https://developer.mozilla.org/ru/docs/Web/HTTP/CORS
        // настроим корс для работы на локале (там у нас реакт обычно запущен на другом домене)
        $headers = Yii::$app->response->headers;
        $headers->set('Access-Control-Allow-Origin', $origin);
        //$headers->set('Access-Control-Allow-Credentials', 'true');
        $headers->set('Access-Control-Allow-Headers', 'X-Requested-With');
        $headers->set('Access-Control-Allow-Methods', 'OPTIONS,GET,POST');

        if ($request->method === 'OPTIONS') return false; // в этом случае нам надо будет завершить выполнение скрипта. 

        return true;
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
