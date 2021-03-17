<?php
use yii\helpers\Html;
use app\assets\ReactAsset;

/* @var $this yii\web\View */
/* @var $content string */

ReactAsset::register($this);

?>
<?php
$this->beginPage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <?=Html::csrfMetaTags()?>
    <?=$this->params['header']?>
    <?php
    $this->head();
    ?>
</head>
<body>
<?php
$this->beginBody();
?>
    <?=$content?>
<?php
$this->endBody();
?>
</body>
</html>
<?php
$this->endPage();
?>