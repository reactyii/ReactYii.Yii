<?php
namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
// use yii\filters\VerbFilter;
// use app\models\LoginForm;
// use app\models\ContactForm;
use app\models\Language;
use yii\caching\TagDependency;

class ReactController extends Controller
{

    public $layout = 'react';

    /**
     *
     * {@inheritdoc}
     */
    /*
     * public function actions()
     * {
     * return [
     * 'error' => [
     * 'class' => 'yii\web\ErrorAction',
     * ],
     * ];
     * }/*
     */

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        $request = Yii::$app->request;
        $path = $request->pathInfo;
        Yii::info("\n\n\n");
        Yii::info("--------------------start " . $request->method . ': ' . $path, __METHOD__);

        if (! $this->checkCORS($request)) {
            return; // ответ уже выслан или ничего не надо посылать
        }

        Yii::info('continue afterCORS', __METHOD__);

        list ($site, $lang, $section, $page) = $this->parsePath(null, $path);

        if ($request->isAjax) {
            sleep(3); // отладка
            Yii::info('prepare json data for page', __METHOD__);
            if ($path == '404.html') {
                throw new \yii\web\NotFoundHttpException();
                // return false;
            }

            $response = Yii::$app->response;
            $response->format = \yii\web\Response::FORMAT_JSON;
            $seo = [
                'title' => 'hello world for /' . $path
            ];
            $response->data = [
                'seo' => $seo,
                'path' => '/' . $path
            ];
            return;
        }

        Yii::info('render only react container', __METHOD__);

        // SSR
        $data = $this->setSSR($site, $path);

        switch ($data['status']) {
            case 404:
                throw new \yii\web\NotFoundHttpException();
            case 301:
            case 302:
                // редирект (пока кинем ошибку - не реализовано)
                throw new yii\web\ServerErrorHttpException();
                // $this->redirect('http://example.com/new', 301);
                break;
            case 200:
                return $this->render('index', $data);
            default:
                // https://www.yiiframework.com/doc/guide/2.0/ru/runtime-responses
                throw new yii\web\ServerErrorHttpException();
        }
    }

    /**
     * Устанавливаем данные от серверного рендера (если они есть и если нужно)
     *
     * @return array
     */
    private function setSSR(&$site, $path)
    {
        $result = [
            'status' => 200,
            'content' => '',
            'header' => ''
        ];

        if (false) // если юзер авторизован, то сразу выходим и возвращаем код 200
        {
            return $result;
        }

        // check cache!

        $key = implode('-', [
            $path,
            __CLASS__,
            __FUNCTION__
        ]);
        Yii::info("setSSR. cachekey=" . $key, __METHOD__);

        return Yii::$app->cache->getOrSet($key, function () use ($key, $site, $path, $result) {
            Yii::info("eval setSSR for cachekey=" . $key, __METHOD__);

            $ssr_path = rtrim(Yii::getAlias('@reactSSR', '/\\'));

            $filename = $path;
            switch ($path) {
                case '':
                case 'index.html':
                    $filename = 'index.html';
                    break;
            }

            $page = @implode('', @file($ssr_path . '/' . $filename));
            if (! $page) // нет страницы вернем 404
            {
                $result['status'] = 404;
                return $result;
            }
            list ($header, $body) = explode('</head><body>', $page, 2);
            list ($tmp, $header) = explode('<head>', $header);
            list ($body, $tmp) = explode('</body>', $body);

            // уберем из хеадера линки на стили
            $header = preg_replace([
                '/<link [^>]* rel="stylesheet">/'
            ], [
                ''
            ], $header);
            $header = str_replace([
                '<link href="/manifest.json" rel="manifest">',
                '<meta charset="utf-8">'
            ], [
                '',
                ''
            ], $header);

            // из боди линки на скрипты
            $body = preg_replace([
                '/<script>.*?<\\/script>/',
                '/<script src=.*?<\\/script>/'
            ], [
                '',
                ''
            ], $body);

            // Yii::info('SSR [' . $ssr_path . '/' . $path . '] page= ' . var_export($page, true), __METHOD__); // http://localhost:3000
            $result['content'] = $body;
            $result['header'] = $header;

            return $result;
        }, null, new TagDependency([
            'tags' => [
                'site-' . $site['id'], // чтоб скинуть кеши всего сайта
                'pages-' . $site['id'] // чтоб скинуть тока страницы сайта
                                       // 'page-' . $path // чтоб обновить конкретную страницу (скорее всего это использовать не будем!)
            ]
        ]));
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
        if (! $origin)
            return true; // нет заголовка для проверки. ничего делать не нужно

        // Yii::info('host: ' . $request->userHost . '; ip: ' . $request->userIP, __METHOD__);
        Yii::info('Origin: ' . $origin, __METHOD__); // http://localhost:3000
        list ($proto, $hostport) = explode('//', $origin, 2);
        list ($host, $port) = explode(':', $hostport, 2);
        $allwedOrigins = [
            'localhost'
        ]; // разрешенные хосты для аякс реквестов

        if (! in_array($host, $allwedOrigins)) // запрос не с нашего сайта. выдадим ответ not allowed
        {
            throw new \yii\web\MethodNotAllowedHttpException();
            return false;
        }

        // https://developer.mozilla.org/ru/docs/Web/HTTP/CORS
        // настроим корс для работы на локале (там у нас реакт обычно запущен на другом домене)
        $headers = Yii::$app->response->headers;
        $headers->set('Access-Control-Allow-Origin', $origin);
        // $headers->set('Access-Control-Allow-Credentials', 'true');
        $headers->set('Access-Control-Allow-Headers', 'content-type,X-Requested-With');
        $headers->set('Access-Control-Allow-Methods', 'OPTIONS,GET,POST');

        if ($request->method === 'OPTIONS')
            return false; // в этом случае нам надо будет завершить выполнение скрипта.

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
     * Если базовый хоост не определен в конфиге, то просто пытаемся отрезолвить раздел по домену
     *
     * @return string
     */
    private function parsePath($host, $path)
    {
        $site = null;
        $lang = null;
        $section = null;
        $page = null;

        $parts = explode('/', $path);

        // резолв сайта (пока будем считать что у нас один сайт по умолчанию)
        $siteid = 1;
        $site['id'] = $siteid;

        // 2. начнем с резолва языка, если он есть, то он занимает первую часть пути
        // $result['lang'] = $this->
        Language::getAll($site);

        return [
            $site,
            $lang,
            $section,
            $page
        ];
    }
}
