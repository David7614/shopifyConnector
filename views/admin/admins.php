<?php
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var app\models\User[] $admins */
/** @var string|null $error */
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Administratorzy</h2>
    <?= Html::a('← Użytkownicy', Url::toRoute(['admin/index']), ['class' => 'btn btn-secondary']) ?>
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
        <table class="table table-bordered table-sm">
            <thead class="thead-dark">
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
                    <td><?= $admin->id ?></td>
                    <td><?= Html::encode($admin->username) ?></td>
                    <td><?= Html::encode($admin->email) ?></td>
                    <td>
                        <?php if ($admin->id !== Yii::$app->user->id): ?>
                        <?= Html::beginForm('', 'post', ['style' => 'display:inline']) ?>
                        <?= Html::hiddenInput('action', 'delete') ?>
                        <?= Html::hiddenInput('user_id', $admin->id) ?>
                        <?= Html::submitButton('Usuń', [
                            'class'   => 'btn btn-sm btn-danger',
                            'onclick' => "return confirm('Usunąć administratora " . Html::encode($admin->username) . "?')",
                        ]) ?>
                        <?= Html::endForm() ?>
                        <?php else: ?>
                        <span class="text-muted">(ty)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="col-md-5">
        <div class="card mb-3">
            <div class="card-header"><strong>Zmień hasło</strong></div>
            <div class="card-body">
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

        <div class="card">
            <div class="card-header"><strong>Dodaj administratora</strong></div>
            <div class="card-body">
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
