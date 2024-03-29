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
use yii\base\ErrorException;

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

    public function actionError()
    {
        // перенаправление ошибок работает тока в проде в разработке мы попадаем в перехватчик ошибок модуля debug
        // сюда мы завалимся тока при 500 ошибке
        // как оказалось и 404 тоже вполне себе. на путь /path/ без указания index.html (как я понял нет суффикса)
        $e = Yii::$app->errorHandler->exception;

        $statusCode = '500';
        if($e!= null) {
            Yii::error("\n\n\n\n------>>>> actionError: " . $e->statusCode . ' ' . $e->getMessage() . "\n" . $e->getFile() . ' (' . $e->getLine() . ")\n" . $e->getTraceAsString(), __METHOD__);
            //Yii::error("actionError: ----------------\n\n\n" . var_export($e, true));

            $statusCode = $e->statusCode == 404 ? '404' : '500';
        }

        $_data = $this->loadSSR($statusCode.'.html');
        if ($_data['status'] === 200) // найдена отрендеренная 404
        {
            $request = Yii::$app->request;
            $path = $request->pathInfo;
            //Yii::error("actionError: ----------------\n\n\n" . $path);
            /*$_data['content'] = str_replace(
                '"pageWraper":{"key":"\u002F500.html"',
                '"pageWraper":{"key":"' . str_replace(['/', '"', "\n"], ['\\u002F', '', ''], '/' . $path) . '"',
                $_data['content']
            );/**/
            $_data['content'] = $this->replaceWrapperKey($statusCode.'.html', $path, $_data['content']);
            Yii::error("content=" . $_data['content']);
            return $this->render('index', $_data);
        }

        // не понятная ошибка

    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        $request = Yii::$app->request;
        $path = $request->pathInfo;

        // частая ошибка показываем 404
        //Yii::info('!!!! strpos("'.$path . '", "//")='. var_export(strpos($path, '//'), true));
        // да бля если в браузере набрать http://reactyii.test// то $path будет равен '/'
        if ($path === '/' || strpos($path, '//') !== false) throw new \yii\web\NotFoundHttpException();

        // путь заканчивается на / значит надо добавить в него index.html
        if ($path !== '') {
            if (Site::endsWith($path, '/')) {
                $path .= 'index.html';
            }
            if (!Site::endsWith($path, '.html')) {
                // а вот тут надо сделать редирект на ту же страницу, но с .html
                // тут мы не знаем папка это или path? будем предполагать что path
                return $this->redirect($path . '.html', 301); // 301 Moved Permanently
            }
        } else {
            $path = 'index.html';
        }
        Yii::info("\n\n\n");
        Yii::info("--------------------start " . $request->method . ': ' . $path, __METHOD__);

        if (!$this->checkCORS($request)) {
            return; // ответ уже выслан или ничего не надо посылать
        }

        //Yii::info('continue afterCORS', __METHOD__);

        $host = null;
        $session = Site::getSite($host); // по сути тут инициализируем сессию вернется сессия с найденным сайтом
        $session['user'] = null; // грузим данные юзера

        $post = $request->isPost ? $request->post() : null;

        //throw new ErrorException("Test 500 error");

        if ($request->isAjax) {
            list ($lang, $section, $page, $content) = [null, null, null, null];
            $response = Yii::$app->response;
            $response->format = \yii\web\Response::FORMAT_JSON;
            try {

                //throw new ErrorException("Test 500 error");

                $_get = $request->get();
                $get = [];
                foreach ($_get as $k => $v) {
                    if ($k === 'url') continue; // вот нахрена $request->get() сует url в гет параметры? ппц!!!
                    if (strpos($k, '__') === 0) continue; // скипаем наши системные параметры типа "__siteLM"
                    $get[$k] = $v;
                }
                //$get = null; // для тестирования условия

                list ($lang, $section, $page, $content, $seo) = $this->parsePath($session, $path, $get, $post);

                // после $this->parsePath (нам нужен lang для перевода менюшек)
                Menu::fillContentFromMenu($session, $lang, $content);

                $page['content'] = $content;

                //Yii::info('content after Menu::fillContentFromMenu:' . json_encode($content, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT), __METHOD__);

                // sleep(3); // отладка
                //Yii::info('prepare json data for page', __METHOD__);

                // $page['requestedpath'] = '/' . $path; // это не нужно. на фронте запрашиваемый путь передается в колбэке запроса (через замыкание)

                // заполним $page['session'] если нужно
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

                $response->data = $page;
            } catch (\yii\web\NotFoundHttpException $e) { // 404 в аякс режиме - нужно вернуть вместо контента спец сформированный блок
                // при возникновении этой ошибки у нас не отресолвлен только $content и (или) $page, но в любом случае мы делаем поиск 404 страницы в БД

                // $section (!) также может быть не отресолвлен, НО мы уже можем быть в ветке где нет такого раздела! и мы пытались отресолвить страницу с этим именем
                // /ru/section_bad/page1.html у нас нет раздела section_bad и parsePath попробует найти страницу с этим именем и если не найдет, то выкинет исключение 404
                // хотя и языка у нас тоже может не быть запрашиваемого
                $page = Menu::get404BySection($session, $lang, $section);

                $page['session'] = $session; // в 404 сделаем возврат и сессии также (подумать) в теории можно также как и для обычной страницы, но пока некада
                $response->data = $page;
            } catch (\Exception $e) {
                Yii::error("=====> Exception: " . $e->getMessage() . "\n" . $e->getFile() . ' (' . $e->getLine() . ")\n" . $e->getTraceAsString(), __METHOD__);
                // в проде тут может быть все плохо. самое херовое это когда и $session нема (БД ушла покурить)
                // но мы должны попытаться вытащить все что можно (в надежде что БД тут и просто где то деление на ноль или ошибка в запросе)
                $page = Menu::get500BySection($session, $lang, $section, $e);
                $page['session'] = $session; // по аналогии с 404
                $response->data = $page;
            }
            return;
        }

        Yii::info('render only react container', __METHOD__);

        // пост запросы тока в аякс режиме!
        if ($post !== null) throw new \yii\web\NotFoundHttpException();

        if (strpos($path, '=')) { // признак посиковой формы (наличие get)
            $get = $request->get(); // в случае не аякс запроса никаких системных пременных в урлах быть не должно!

            // решение конечно не айс. так как мы дважды разбираем путь и делаем поиск контента, НО при включенном кешировании это допустимо
            // также в эту ветку мы зайдем если юзер в поиске нажмет "F5" для обновления страницы и такое должно происходить редко - ожидание
            try {
                list ($lang, $section, $page, $content) = $this->parsePath($session, $path, $get, $post);
                // если такой страницы на сайте нет то parsePath выкинет 404, а если мы прошли то значит вернем корректную страницу, но без SSR
            } catch (\yii\web\NotFoundHttpException $e) {
                throw $e;
            } catch (Exception $e) {
                Yii::error("=====> Exception: " . $e->getMessage() . "\n" . $e->getFile() . ' (' . $e->getLine() . ")\n" . $e->getTraceAsString(), __METHOD__);
                throw $e;
            }

            $data = $this->getDefaultSSRContent($session);
        } else {
            // SSR
            $data = $this->setSSR($session, $path);
        }

        switch ($data['status']) {
            case 404:
                Yii::$app->getResponse()->setStatusCode(404);
                // попробуем найти заранее сгенеренную
                $_data = $this->loadSSR('404.html');
                if ($_data['status'] === 200) // найдена отрендеренная 404
                {
                    // вот тут надо вставить костылик!
                    // так как сгенеренная 404 содержит "pageWraper":{"key":"\u002F404.html" а у нас урл $path отличается от 404.html
                    // и реакт делает повторную загрузку этой страницы
                    // пока вот так по тупому - не надежно может отвалится при изменении структуры данных
                    /*$_data['content'] = str_replace(
                        '"pageWraper":{"key":"\u002F404.html"',
                        '"pageWraper":{"key":"' . str_replace(['/', '"', "\n"], ['\\u002F', '', ''], '/' . $path) . '"',
                        $_data['content']
                    );/**/
                    $_data['content'] = $this->replaceWrapperKey('404.html', $path, $_data['content']);
                    return $this->render('index', $_data);
                }

                $_data = $this->getDefaultSSRContent($session);
                return $this->render('index', $_data);
            case 301:
            case 302:
                // редирект (пока кинем ошибку - не реализовано)
                throw new yii\web\ServerErrorHttpException();
                // $this->redirect('http://example.com/new', 301);
                break;
            case 200:
                if ($path === '404.html') // спец страница, чтоб поисковики туда не шарились, ssr ее находит и корректно возвращает 200 (так и должнор быть)
                {
                    Yii::$app->getResponse()->setStatusCode(404);
                } else if ($path === '500.html') // спец страница, чтоб поисковики туда не шарились, ssr ее находит и корректно возвращает 200 (так и должнор быть)
                {
                    Yii::$app->getResponse()->setStatusCode(500);
                }
                return $this->render('index', $data);
            default:
                // https://www.yiiframework.com/doc/guide/2.0/ru/runtime-responses
                throw new yii\web\ServerErrorHttpException();
        }
    }

    private function getDefaultSSRContent()
    {
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
        $result = $this->getDefaultSSRContent();

        // чисто для оптимизации, чтоб не делать поиск заведомо не существующей страницы
        // вот так по любому не надо и мы должны найти сгенерированную страницу
        /*if ($path === '404.html')
        {
            $result['status'] = 404;
            return $result;
        }/**/

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

            return static::loadSSR($path);
        }, null, new TagDependency([
            'tags' => [
                'site-' . $site['id'], // чтоб скинуть кеши всего сайта
                'pages-' . $site['id'] // чтоб скинуть тока страницы сайта
                // 'page-' . $path // чтоб обновить конкретную страницу (скорее всего это использовать не будем!)
            ]
        ]));
    }

    private function replaceWrapperKey($key, $path, $content)
    {
        $request = Yii::$app->request;
        $_path = $request->pathInfo; // здесь нам нужен исходный путь, а не доработанный моими проверками

        return str_replace(
            '"pageWraper":{"key":"\u002F' . $key . '"',
            '"pageWraper":{"key":"' . str_replace(['/', '"', "\n"], ['\\u002F', '', ''], '/' . $_path) . '"',
            $content
        );
    }

    private function loadSSR($path)
    {
        $result = $this->getDefaultSSRContent();
        $ssr_path = rtrim(Yii::getAlias('@reactSSR'), '/\\');

        $filename = $path;
        switch ($path) {
            case '':
            case 'index.html':
                $filename = 'index.html';
                break;
        }

        $page = @implode('', @file($ssr_path . '/' . $filename));
        if (!$page) // нет страницы вернем 404
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
        if (!$origin)
            return true; // нет заголовка для проверки. ничего делать не нужно

        // Yii::info('host: ' . $request->userHost . '; ip: ' . $request->userIP, __METHOD__);
        Yii::info('Origin: ' . $origin, __METHOD__); // http://localhost:3000
        list ($proto, $hostport) = explode('//', $origin, 2);
        list ($host, $port) = explode(':', $hostport, 2);
        $allwedOrigins = [
            'localhost'
        ]; // разрешенные хосты для аякс реквестов

        if (!in_array($host, $allwedOrigins)) // запрос не с нашего сайта. выдадим ответ not allowed
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
        $parts = explode('/', (strrpos($path, '.html') === strlen($path) - 5 ? substr($path, 0, strlen($path) - 5) : $path));

        // чисто для оптимизации если путь пустой то $parts = [''] и мы делаем поиск по языкам и разделам
        // НО! надо протестировать такой адрес yii.test// и в этом случае по идее должна быть 404!
        // решение на пустые части в пути (кроме строго единственного) будем кидать 404
        // вынес эти проверки наружу
        /*if (sizeof($parts) === 1 && $parts[0] === '') {
            throw new \yii\web\NotFoundHttpException();
            //array_shift($parts);
        }*/

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
        if (!$lang && sizeof($site['langs']) > 0) { // языка в пути нет и языков больше чем 1, но мы выберем язык по умолчанию
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

        // если имя страницы 404 то сразу кинем исключение. оно будет перехвачено сверху и будет сгенерен контент для 404 страницы
        if ($page_path === '404') throw new \yii\web\NotFoundHttpException();

        // тоже самое и для 500 страницы
        if ($page_path === '500') throw new \Exception();

        //Yii::info('=====> $page_path=' . $page_path, __METHOD__);
        $page = Menu::getItemBySectionPage($session, $section, $page_path);
        Yii::info("=====> page=" . var_export($page, true), __METHOD__);
        if (!$page) {
            throw new \yii\web\NotFoundHttpException();
        }

        // еще надо заполнить контентом
        $content = Content::getContentForPage($session, $lang, $section, $page, $parts, $get, $post);
        //Yii::info('=====> $content[' . $path . ']=' . var_export($content, true), __METHOD__);

        // дополним контент менюшками! снаружи! так как данная фнукция используется и для парсинга поисковых форм

        $seo = [
            'title' => 'hello world for /' . $path,
            'desc' => 'descr'
        ];

        $page['seo'] = $seo;
        $page['section'] = $section;
        $page['lang'] = $lang;
        // $content мы еще изменим (дополним) снаружи
        //$page['content'] = $content;

        return [
            $lang,
            $section,
            $page,
            $content,
            $seo
        ];
    }
}
