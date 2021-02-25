<?php
use yii\helpers\Html;
use app\assets\ReactAsset;

/* @var $this yii\web\View */
/* @var $content string */

ReactAsset::register($this);

?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <?= Html::csrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
</head>
<body>
<?php $this->beginBody() ?>
    <div id="root"><?= $content ?></div>
    <noscript>You need to enable JavaScript to run this app.</noscript>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>