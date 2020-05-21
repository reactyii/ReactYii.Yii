<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'app\commands',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
        '@tests' => '@app/tests',
    ],
    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'log' => [
            'targets' => [
                'console' => [
                    'class' => 'yii\log\FileTarget',
                    'rotateByCopy' => true,
                    'logFile' => '@runtime/logs/' . date('Y-m-d') . '.console.log',
                    'levels' => YII_DEBUG ? ['error', 'warning', 'info'] : ['error', 'warning'],
                    'logVars' => [], // '_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_SERVER'
                    'except' => [
                        'yii\db\*',
                    ],
                ],/* */
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
    ],
    'params' => $params,
    /*
    'controllerMap' => [
        'fixture' => [ // Fixture generation command line.
            'class' => 'yii\faker\FixtureController',
        ],
    ],
    */
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}

return $config;
