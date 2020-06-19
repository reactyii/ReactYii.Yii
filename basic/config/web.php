<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$settings = require __DIR__ . '/settings.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => $settings['cookieValidationKey'],
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
            //'errorAction' => 'main/index',
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => true,
        ],
        // https://www.yiiframework.com/doc/guide/2.0/ru/runtime-logging
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0, // в отладке каждое сообщение лога будет содержать до 3 уровней стека
            'targets' => [
                'app' => [
                    'class' => 'yii\log\FileTarget',
                    'rotateByCopy' => true,
                    'logFile' => '@runtime/logs/' . date('Y-m-d') . '.app.log',
                    /*'prefix' => function ($message) {
                        //$m = Target::formatMessage($message);
                        $user = Yii::$app->has('user', true) ? Yii::$app->get('user') : null;
                        $userID = $user ? $user->getId(false) : '-';
                        return "[$userID]";
                    },/* */
                    'levels' => YII_DEBUG ? ['error', 'warning', 'info'] : ['error', 'warning'],
                    'logVars' => [], // '_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_SERVER'
                    'except' => [
                        'yii\web\Session::*',
                    ],
                ],
                'errordb' => [
                    'class' => 'yii\log\FileTarget',
                    'rotateByCopy' => true,
                    'logFile' => '@runtime/logs/' . date('Y-m-d') . '.errordb.log',
                    'levels' => ['error', 'warning'],
                    'logVars' => [], // '_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_SERVER'
                    'categories' => ['yii\db\*'],
                ],
            ],
        ],
        'db' => $db,

        // https://www.yiiframework.com/doc/guide/2.0/ru/runtime-routing
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'enableStrictParsing' => false,
            'suffix' => '.html',
            'rules' => [
                'site/<action>' => 'site/<action>', 
                //'<url:[a-zA-Z0-9-_&%\\/]+>' => 'main/index', 
                '<url:.*>' => 'main/index', 
            ],
        ],/* */
    ],
    'defaultRoute' => 'main/index',
    //'catchAll' => ['main/index'],

    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}/* */

return $config;
