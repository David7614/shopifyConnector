<?php
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var app\models\User[] $admins */
/** @var string|null $error */
?>

<div style="display:flex; align-items:center; justify-content:space-between; margin:20px 0 16px;">
    <h2 style="margin:0;">Administratorzy</h2>
    <?= Html::a('← Użytkownicy', Url::toRoute(['admin/index']), ['class' => 'btn btn-default btn-sm']) ?>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= Html::encode($error) ?></div>
<?php endif; ?>

<?php foreach (Yii::$app->session->getAllFlashes() as $type => $messages): ?>
    <div class="alert alert-<?= $type === 'error' ? 'danger' : $type ?>">
        <?= implode('<br>', (array) $messages) ?>
    </div>
<?php endforeach; ?>

<div class="row">
    <div class="col-md-7">
        <h4>Lista administratorów</h4>
        <table class="table table-bordered table-sm" style="font-size:13px;">
            <thead style="background:#333; color:#fff;">
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $admin): ?>
                <tr>
                    <td style="color:#999; font-size:12px;"><?= $admin->id ?></td>
                    <td><?= Html::encode($admin->username) ?></td>
                    <td><?= Html::encode($admin->email) ?></td>
                    <td>
                        <?php if ($admin->id !== Yii::$app->user->id): ?>
                        <?= Html::beginForm('', 'post', ['style' => 'display:inline']) ?>
                        <?= Html::hiddenInput('action', 'delete') ?>
                        <?= Html::hiddenInput('user_id', $admin->id) ?>
                        <?= Html::submitButton('Usuń', [
                            'class'   => 'btn btn-xs btn-danger',
                            'onclick' => "return confirm('Usunąć administratora " . Html::encode($admin->username) . "?')",
                        ]) ?>
                        <?= Html::endForm() ?>
                        <?php else: ?>
                        <span style="color:#aaa;">(ty)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="col-md-5">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Zmień hasło</strong></div>
            <div class="panel-body">
                <?= Html::beginForm('', 'post') ?>
                <?= Html::hiddenInput('action', 'change-password') ?>
                <div class="form-group">
                    <label>Administrator</label>
                    <?= Html::dropDownList('user_id', null,
                        array_column($admins, 'username', 'id'),
                        ['class' => 'form-control']
                    ) ?>
                </div>
                <div class="form-group">
                    <label>Nowe hasło</label>
                    <?= Html::passwordInput('new_password', '', ['class' => 'form-control', 'required' => true]) ?>
                </div>
                <?= Html::submitButton('Zmień hasło', ['class' => 'btn btn-warning']) ?>
                <?= Html::endForm() ?>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading"><strong>Dodaj administratora</strong></div>
            <div class="panel-body">
                <?= Html::beginForm('', 'post') ?>
                <?= Html::hiddenInput('action', 'add') ?>
                <div class="form-group">
                    <label>Username</label>
                    <?= Html::textInput('username', '', ['class' => 'form-control', 'required' => true]) ?>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <?= Html::input('email', 'email', '', ['class' => 'form-control', 'required' => true]) ?>
                </div>
                <div class="form-group">
                    <label>Hasło</label>
                    <?= Html::passwordInput('password', '', ['class' => 'form-control', 'required' => true]) ?>
                </div>
                <?= Html::submitButton('Dodaj', ['class' => 'btn btn-success']) ?>
                <?= Html::endForm() ?>
            </div>
        </div>
    </div>
</div>
