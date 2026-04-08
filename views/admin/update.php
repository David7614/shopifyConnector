<?php
use yii\helpers\Html;
use yii\helpers\Url;
use app\models\IntegrationData;

/** @var yii\web\View $this */
/** @var app\models\User $user */
/** @var array $feedUrls */
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Ustawienia — <?= Html::encode($user->username) ?></h2>
    <?= Html::a('← Użytkownicy', Url::toRoute(['admin/index']), ['class' => 'btn btn-secondary']) ?>
</div>

<?php foreach (Yii::$app->session->getAllFlashes() as $type => $messages): ?>
    <div class="alert alert-<?= $type === 'error' ? 'danger' : $type ?>">
        <?= implode('<br>', (array) $messages) ?>
    </div>
<?php endforeach; ?>

<div class="row">
    <!-- Ustawienia ogólne -->
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header"><strong>Ustawienia synchronizacji</strong></div>
            <div class="card-body">
                <?= Html::beginForm('', 'post') ?>
                <div class="form-group">
                    <?= Html::label('Typ eksportu', 'export_type') ?>
                    <?= Html::dropDownList('export_type', $user->config->get('export_type'), [
                        '0' => 'Pełna baza',
                        '1' => 'Inkrementalny',
                    ], ['class' => 'form-control', 'id' => 'export_type']) ?>
                </div>
                <div class="form-group">
                    <?= Html::label('Feed enabled', 'feed_enabled') ?>
                    <?= Html::dropDownList('feed_enabled', $user->config->get('feed_enabled') ?? 1, [
                        '1' => 'Włączony',
                        '0' => 'Wyłączony',
                    ], ['class' => 'form-control', 'id' => 'feed_enabled']) ?>
                </div>
                <?= Html::submitButton('Zapisz', ['class' => 'btn btn-primary']) ?>
                <?= Html::endForm() ?>
            </div>
        </div>
    </div>

    <!-- Feed URLs -->
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header"><strong>Feed URLs</strong></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="thead-light">
                        <tr><th>Typ</th><th>URL</th><th>Rekordy w DB</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Products</td>
                            <td><a href="<?= Html::encode($feedUrls['products']) ?>" target="_blank" class="small">link</a></td>
                            <td><?= $user->countDatabaseElements('products') ?></td>
                        </tr>
                        <tr>
                            <td>Customers</td>
                            <td><a href="<?= Html::encode($feedUrls['customers']) ?>" target="_blank" class="small">link</a></td>
                            <td><?= $user->countDatabaseElements('customer') ?></td>
                        </tr>
                        <tr>
                            <td>Orders</td>
                            <td><a href="<?= Html::encode($feedUrls['orders']) ?>" target="_blank" class="small">link</a></td>
                            <td><?= $user->countDatabaseElements('order') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><strong>Ostatnie integracje</strong></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="thead-light">
                        <tr><th>Typ</th><th>Data</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Products</td>
                            <td><?= Html::encode(IntegrationData::getDataValue('last_products_integration_date', $user->id) ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td>Orders</td>
                            <td><?= Html::encode(IntegrationData::getDataValue('last_orders_integration_date', $user->id) ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td>Customers</td>
                            <td><?= Html::encode(IntegrationData::getDataValue('last_customer_integration_date', $user->id) ?? '-') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="mt-2">
    <?= Html::a('Zobacz kolejki użytkownika', Url::toRoute(['admin/view', 'id' => $user->id]), ['class' => 'btn btn-info']) ?>
</div>
