<?php
use app\models\Queue;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var app\models\User $user */
/** @var Queue[] $queues */
/** @var array $statusCounts */
/** @var string $typeFilter */
/** @var string $statusFilter */

$statusLabels = [
    Queue::PENDING  => ['label' => 'Oczekuje',  'class' => 'badge-secondary'],
    Queue::RUNNING  => ['label' => 'W trakcie', 'class' => 'badge-primary'],
    Queue::EXECUTED => ['label' => 'Wykonana',  'class' => 'badge-success'],
    Queue::ERROR    => ['label' => 'Błąd',      'class' => 'badge-danger'],
    Queue::MISSED   => ['label' => 'Pominięta', 'class' => 'badge-warning'],
];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Kolejki — <?= Html::encode($user->username) ?></h2>
    <?= Html::a('← Wróć', Url::toRoute(['admin/index']), ['class' => 'btn btn-secondary']) ?>
</div>

<?php foreach (Yii::$app->session->getAllFlashes() as $type => $messages): ?>
    <div class="alert alert-<?= $type === 'error' ? 'danger' : $type ?>">
        <?= implode('<br>', (array) $messages) ?>
    </div>
<?php endforeach; ?>

<!-- Kafelki statusów -->
<div class="row mb-4">
    <?php foreach ($statusLabels as $status => $info): ?>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="h4"><?= $statusCounts[$status] ?? 0 ?></div>
                <span class="badge <?= $info['class'] ?>"><?= $info['label'] ?></span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filtry -->
<form method="get" class="form-inline mb-3">
    <?= Html::hiddenInput('id', $user->id) ?>
    <select name="type" class="form-control mr-2">
        <option value="">Wszystkie typy</option>
        <option value="product"  <?= $typeFilter === 'product'  ? 'selected' : '' ?>>Product</option>
        <option value="customer" <?= $typeFilter === 'customer' ? 'selected' : '' ?>>Customer</option>
        <option value="order"    <?= $typeFilter === 'order'    ? 'selected' : '' ?>>Order</option>
    </select>
    <select name="status" class="form-control mr-2">
        <option value="">Wszystkie statusy</option>
        <?php foreach ($statusLabels as $s => $info): ?>
            <option value="<?= $s ?>" <?= $statusFilter === (string)$s ? 'selected' : '' ?>><?= $info['label'] ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary mr-2">Filtruj</button>
    <?= Html::a('Reset', Url::toRoute(['admin/view', 'id' => $user->id]), ['class' => 'btn btn-outline-secondary']) ?>
</form>

<!-- Tabela kolejek -->
<table class="table table-sm table-bordered table-hover">
    <thead class="thead-dark">
        <tr>
            <th>ID</th>
            <th>Typ</th>
            <th>Status</th>
            <th>Strona</th>
            <th>Uruchomiona</th>
            <th>Zakończona</th>
            <th>Nast. integracja</th>
            <th>Parametry</th>
            <th>Akcje</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($queues as $q): ?>
        <?php $info = $statusLabels[$q->integrated] ?? ['label' => $q->integrated, 'class' => 'badge-dark']; ?>
        <tr class="<?= $q->integrated === Queue::ERROR ? 'table-danger' : ($q->integrated === Queue::RUNNING ? 'table-info' : '') ?>">
            <td><?= $q->id ?></td>
            <td><?= Html::encode($q->integration_type) ?></td>
            <td><span class="badge <?= $info['class'] ?>"><?= $info['label'] ?></span></td>
            <td><?= $q->page ?> / <?= $q->max_page ?></td>
            <td><?= $q->executed_at ?></td>
            <td><?= $q->finished_at ?></td>
            <td><?= $q->next_integration_date ?></td>
            <td>
                <?php $params = $q->getAdditionalParameters(); ?>
                <?= $params ? '<small>' . Html::encode(json_encode($params)) . '</small>' : '-' ?>
            </td>
            <td>
                <?= Html::beginForm(Url::toRoute(['admin/reset-queue']), 'post', ['style' => 'display:inline']) ?>
                <?= Html::hiddenInput('queueId', $q->id) ?>
                <?= Html::submitButton('Reset', [
                    'class' => 'btn btn-xs btn-warning',
                    'onclick' => 'return confirm("Reset kolejki #' . $q->id . '?")',
                ]) ?>
                <?= Html::endForm() ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if (empty($queues)): ?>
    <p class="text-muted">Brak kolejek spełniających kryteria.</p>
<?php endif; ?>
