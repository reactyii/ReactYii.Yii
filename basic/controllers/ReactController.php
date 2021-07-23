<?php
namespace app\controllers;

use app\models\BaseModel;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
// use yii\filters\VerbFilter;
// use app\models\LoginForm;
// use app\models\ContactForm;
use app\models\Language;
use app\models\Menu;
use app\models\Section;
use app\models\Site;
use app\models\Content;
use yii\caching\TagDependency;

class ReactController extends Controller
{

    public $layout = 'react';
    public $enableCsrfValidation = false; // чтобы отправить post запрос надо обойти еще проверку от самого yii

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

        $host = null;
        $session = Site::getSite($host); // по сути тут инициализируем сессию вернется сессия с найденным сайтом
        $session['user'] = null; // грузим данные юзера

        $post = $request->isPost ? $request->post() : null;

        if ($request->isAjax) {
            $_get = $request->get();
            $get = [];
            foreach ($_get as $k => $v) {
                if ($k === 'url') continue; // вот нахрена $request->get() сует url в гет параметры? ппц!!!
                if (strpos($k, '__') === 0) continue; // скипаем наши системные параметры типа "__siteLM"
                $get[$k] = $v;
            }
            //$get = null; // для тестирования условия

            list ($lang, $section, $page, $content) = $this->parsePath($session, $path, $get, $post);

            $session['site']['menus'] = Menu::getFilteredTree($session, $lang, ['is_blocked' => 0]);

            $menusContent = Menu::getContentFromMenu($session, $session['site']['menus']);
            // заменить на https://www.php.net/manual/ru/function.array-merge.php
            foreach($menusContent as $c) {
                $content[] = $c;
            }

            // sleep(3); // отладка
            //Yii::info('prepare json data for page', __METHOD__);
            if ($path == '404.html') {
                throw new \yii\web\NotFoundHttpException();
                // return false;
            }

            $response = Yii::$app->response;
            $response->format = \yii\web\Response::FORMAT_JSON;
            $seo = [
                'title' => 'hello world for /' . $path,
                'desc' => 'descr'
            ];
            $page['seo'] = $seo;
            // $page['requestedpath'] = '/' . $path; // это не нужно. на фронте запрашиваемый путь передается в колбэке запроса (через замыкание)
            $_session = [];
            $siteLM = $request->get('__siteLM');
            //Yii::info('__siteLM=[' . $siteLM . '] site[lastModified]=' . $session['site']['lastModified'], __METHOD__);
            // todo надо учесть время изменения юзера также как и сайта!!!
            if (!$siteLM || $siteLM < $session['site']['lastModified']) {
                //$_session['site'] = $session['site'];
                $_session = $session;
                // Yii::info('send session', __METHOD__);
            }
            if ($_session)
                $page['session'] = $_session;

            $page['section'] = $section;
            $page['lang'] = $lang;
            $page['content'] = $content;

            $response->data = $page;
            return;
        }

        Yii::info('render only react container', __METHOD__);

        // пост запросы тока в аякс режиме!
        if ($post !== null) throw new \yii\web\NotFoundHttpException();

        if (strpos($path, '=')) { // признак посиковой формы (наличие get)
            $get = $request->get(); // в случае не аякс запроса никаких системных пременных в урлах быть не должно!

            // решение конечно не айс. так как мы дважды разбираем путь и делаем поиск контента, НО при включенном кешировании это допустимо
            // также в эту ветку мы зайдем если юзер в поиске нажмет "F5" для обновления страницы и такое должно происходить редко - ожидание
            list ($lang, $section, $page, $content) = $this->parsePath($session, $path, $get, $post);
            // если такой страницы на сайте нет то parsePath выкинет 404, а если мы прошли то значит вернем корректную страницу, но без SSR

            $data = $this->getDefaultSSRContent($session, $path);
        }
        else {
            // SSR
            $data = $this->setSSR($session, $path);
        }

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

    private function getDefaultSSRContent(&$session, $path) {
        return [
            'status' => 200,
            'content' => '<noscript>You need to enable JavaScript to run this app.</noscript><div id="root"></div>',
            'header' => ''
        ];
    }

    /**
     * Устанавливаем данные от серверного рендера (если они есть и если нужно)
     *
     * @return array
     */
    private function setSSR(&$session, $path)
    {
        $site = BaseModel::getSiteFromSession($session);
        $result = $this->getDefaultSSRContent($session, $path);

        // если юзер авторизован, то сразу выходим и возвращаем код 200
        if ($session['user'] !== null) return $result;

        // очень важный момент - результаты поисковых форм. пока признак наличие "=" в урле
        // плохой вариант! так как если в урле есть "=" то 404 не будет никогда, а это плохо
        // РЕШЕНИЕ! выносим эту проверку до входа в SSR и если в урле есть "=" то будем парсить урл
        //if (strpos($path, '=')) return $result;

        //return $result; // на локале в режиме разработки иногда нужно вызвать страницу без ssr

        // check cache!

        $key = implode('-', [
            $path,
            __CLASS__,
            __FUNCTION__
        ]);
        Yii::info("setSSR. cachekey=" . $key, __METHOD__);

        return Yii::$app->cache->getOrSet($key, function () use ($key, $site, $session, $path, $result) {
            Yii::info("eval setSSR for cachekey=" . $key, __METHOD__);

            $ssr_path = rtrim(Yii::getAlias('@reactSSR'), '/\\');

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
     * @throws \yii\web\NotFoundHttpException
     */
    private function parsePath(&$session, $path, &$get, &$post)
    {
        $site = BaseModel::getSiteFromSession($session);
        /*if ($post) {
            Yii::info('=====> post[' . $path . ']=' . var_export($post, true), __METHOD__);
        }/**/
        $lang = null;
        $section = null;
        $page = null;
        $content = null;

        // уберем .html с конца
        $parts = explode('/', (strrpos($path, '.html') === strlen($path)-5 ? substr($path, 0, strlen($path)-5) : $path));

        // чисто для оптимизации если путь пустой то $parts = [''] и мы делаем поиск по языкам и разделам
        // НО! надо протестировать такой адрес yii.test// и в этом случае по идее должна быть 404!
        // решение на пустые части в пути (кроме строго единственного) будем кидать 404
        if (sizeof($parts) === 1 && $parts[0] === '') array_shift($parts);

        //Yii::info('=====> $parts[' . $path . ']=' . var_export($parts, true), __METHOD__);

        // 2. начнем с резолва языка, если он есть, то он занимает первую часть пути
        if (sizeof($parts) > 0) { // первая часть пути вполне может быть языком
            if ($parts[0] === '') throw new \yii\web\NotFoundHttpException();
            foreach ($site['langs'] as $l) {
                if ($l['path'] === $parts[0]) { // так и есть
                    $lang = $l;
                    array_shift($parts);
                    break;
                }
            }
        }
        // язык по умолчанию не зависимо от того что еcть в path
        if (! $lang && sizeof($site['langs']) > 0) { // языка в пути нет и языков больше чем 1, но мы выберем язык по умолчанию
            $lang = $site['langs'][0]; // язык по умолчанию мы ставим на первое место при выборе из БД
        }

        // 3. разделы
        if (sizeof($parts) > 0) {
            if ($parts[0] === '') throw new \yii\web\NotFoundHttpException();
            $section = Section::getItemByPath($session, $parts[0]);
            if ($section) {
                array_shift($parts);
            }
        }
        //Yii::info("=====> section=" . var_export($section, true), __METHOD__);

        // 4. страница
        $page_path = '';
        if (sizeof($parts) > 0) {
            if ($parts[0] === '') throw new \yii\web\NotFoundHttpException();
            $page_path = $parts[0];
            array_shift($parts);
        }
        if ($page_path === '') $page_path = 'index';

        //Yii::info('=====> $page_path=' . $page_path, __METHOD__);
        $page = Menu::getItemBySectionPage($session, $section, $page_path);
        //Yii::info("=====> page=" . var_export($page, true), __METHOD__);
        if (!$page)
        {
            throw new \yii\web\NotFoundHttpException();
        }

        // еще надо заполнить контентом
        $content = Content::getContentForPage($session, $lang, $section, $page, $parts, $get, $post);
        //Yii::info('=====> $content[' . $path . ']=' . var_export($content, true), __METHOD__);

        // дополним контент менюшками! снаружи! так как данная фнукция используется и для парсинга поисковых форм


        return [
            $lang,
            $section,
            $page,
            $content
        ];
    }
}
