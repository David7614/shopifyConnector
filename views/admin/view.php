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
    Queue::PENDING  => ['label' => 'Oczekuje',  'color' => '#888',    'bg' => '#eee'],
    Queue::RUNNING  => ['label' => 'W trakcie', 'color' => '#1565c0', 'bg' => '#e3f2fd'],
    Queue::EXECUTED => ['label' => 'Wykonana',  'color' => '#2e7d32', 'bg' => '#e8f5e9'],
    Queue::ERROR    => ['label' => 'Błąd',      'color' => '#b71c1c', 'bg' => '#ffebee'],
    Queue::MISSED   => ['label' => 'Pominięta', 'color' => '#e65100', 'bg' => '#fff3e0'],
];
?>

<div style="display:flex; align-items:center; justify-content:space-between; margin:20px 0 16px;">
    <h2 style="margin:0;">Kolejki — <?= Html::encode($user->username) ?></h2>
    <?= Html::a('← Wróć', Url::toRoute(['admin/index']), ['class' => 'btn btn-default btn-sm']) ?>
</div>

<?php foreach (Yii::$app->session->getAllFlashes() as $type => $messages): ?>
    <div class="alert alert-<?= $type === 'error' ? 'danger' : $type ?>">
        <?= implode('<br>', (array) $messages) ?>
    </div>
<?php endforeach; ?>

<!-- Status tiles -->
<div class="row" style="margin-bottom:20px;">
    <?php foreach ($statusLabels as $status => $info): ?>
    <div class="col-md-2">
        <div style="text-align:center; border:1px solid #ddd; border-radius:6px; padding:10px 6px;">
            <div style="font-size:22px; font-weight:700; color:<?= $info['color'] ?>;"><?= $statusCounts[$status] ?? 0 ?></div>
            <span style="display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; background:<?= $info['bg'] ?>; color:<?= $info['color'] ?>;"><?= $info['label'] ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<form method="get" class="form-inline" style="margin-bottom:16px;">
    <?= Html::hiddenInput('id', $user->id) ?>
    <select name="type" class="form-control" style="margin-right:8px;">
        <option value="">Wszystkie typy</option>
        <option value="product"  <?= $typeFilter === 'product'  ? 'selected' : '' ?>>Product</option>
        <option value="customer" <?= $typeFilter === 'customer' ? 'selected' : '' ?>>Customer</option>
        <option value="order"    <?= $typeFilter === 'order'    ? 'selected' : '' ?>>Order</option>
    </select>
    <select name="status" class="form-control" style="margin-right:8px;">
        <option value="">Wszystkie statusy</option>
        <?php foreach ($statusLabels as $s => $info): ?>
            <option value="<?= $s ?>" <?= $statusFilter === (string)$s ? 'selected' : '' ?>><?= $info['label'] ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary" style="margin-right:4px;">Filtruj</button>
    <?= Html::a('Reset', Url::toRoute(['admin/view', 'id' => $user->id]), ['class' => 'btn btn-default']) ?>
</form>

<!-- Queue table -->
<table class="table table-sm table-bordered table-hover" style="font-size:13px;">
    <thead style="background:#333; color:#fff;">
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
        <?php $info = $statusLabels[$q->integrated] ?? ['label' => $q->integrated, 'color' => '#444', 'bg' => '#eee']; ?>
        <tr style="<?= $q->integrated === Queue::ERROR ? 'background:#fff8f8;' : ($q->integrated === Queue::RUNNING ? 'background:#f0f7ff;' : '') ?>">
            <td style="color:#bbb; font-size:11px;"><?= $q->id ?></td>
            <td><?= Html::encode($q->integration_type) ?></td>
            <td><span style="display:inline-block; padding:1px 7px; border-radius:10px; font-size:11px; background:<?= $info['bg'] ?>; color:<?= $info['color'] ?>;"><?= $info['label'] ?></span></td>
            <td><?= $q->page ?> / <?= $q->max_page ?></td>
            <td style="font-size:12px; color:#666;"><?= $q->executed_at ?></td>
            <td style="font-size:12px; color:#666;"><?= $q->finished_at ?></td>
            <td style="font-size:12px; color:#666;"><?= $q->next_integration_date ?></td>
            <td>
                <?php $params = $q->getAdditionalParameters(); ?>
                <?= $params ? '<small style="color:#999;">' . Html::encode(json_encode($params)) . '</small>' : '—' ?>
            </td>
            <td>
                <?= Html::beginForm(Url::toRoute(['admin/reset-queue']), 'post', ['style' => 'display:inline']) ?>
                <?= Html::hiddenInput('queueId', $q->id) ?>
                <?= Html::submitButton('Reset', [
                    'class'   => 'btn btn-xs btn-warning',
                    'onclick' => 'return confirm("Reset kolejki #' . $q->id . '?")',
                ]) ?>
                <?= Html::endForm() ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if (empty($queues)): ?>
    <p style="color:#aaa;">Brak kolejek spełniających kryteria.</p>
<?php endif; ?>
