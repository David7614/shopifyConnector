<?php

    /* @var $this \yii\web\View */
    /* @var $content string */

    use app\assets\AppAsset;
    use yii\helpers\Html;

    AppAsset::register($this);
?>
<?php $this->beginPage()?>
<!DOCTYPE html>
<html lang="<?php echo Yii::$app->language?>">
<head>
    <meta charset="<?php echo Yii::$app->charset?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $this->registerCsrfMetaTags()?>
    <title><?php echo Html::encode($this->title)?></title>
    <?php $this->head()?>
</head>
<body>
<?php $this->beginBody()?>

<?php
$currentAction     = Yii::$app->controller->action->id ?? '';
$currentController = Yii::$app->controller->id ?? '';
$navItems = [
    ['label' => 'Użytkownicy',     'url' => ['admin/index'],  'action' => 'index'],
    ['label' => 'Monitor kolejek', 'url' => ['admin/queues'], 'action' => 'queues'],
    ['label' => 'Administratorzy', 'url' => ['admin/admins'], 'action' => 'admins'],
];
?>
<nav style="background:#2c3e50; padding:0 20px; margin-bottom:0;">
    <div style="display:flex; align-items:center; gap:4px;">
        <span style="color:#ecf0f1; font-weight:700; font-size:14px; padding:12px 16px 12px 0; margin-right:8px; border-right:1px solid #445;">
            Shopify Admin
        </span>
        <?php foreach ($navItems as $item):
            $isActive = ($currentController === 'admin' && $currentAction === $item['action']);
        ?>
        <a href="<?= \yii\helpers\Url::to($item['url']) ?>"
           style="display:block; padding:12px 14px; font-size:13px; text-decoration:none;
                  color:<?= $isActive ? '#fff' : '#bdc3c7' ?>;
                  background:<?= $isActive ? '#1a252f' : 'transparent' ?>;
                  border-bottom:<?= $isActive ? '3px solid #3498db' : '3px solid transparent' ?>;">
            <?= $item['label'] ?>
        </a>
        <?php endforeach ?>
        <div style="margin-left:auto;">
            <a href="<?= \yii\helpers\Url::to(['authorization/logout']) ?>"
               style="display:block; padding:9px 14px; font-size:12px; text-decoration:none;
                      color:#bdc3c7; border:1px solid #445; border-radius:4px; white-space:nowrap;"
               onclick="return confirm('Wylogować się?')">
                Wyloguj
            </a>
        </div>
    </div>
</nav>

<div class="wrap">
    <?php echo \app\widgets\Alert::widget()?>

    <div class="container">
        <?php echo $content?>
    </div>
</div>


<?php $this->endBody()?>
</body>
</html>
<?php $this->endPage()?>
