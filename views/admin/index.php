<?php
use app\models\Queue;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var app\models\User[] $users */
/** @var array $summary */
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Użytkownicy</h2>
    <div>
        <?= Html::a('Monitor kolejek', Url::toRoute(['admin/queues']), ['class' => 'btn btn-info mr-2']) ?>
        <?= Html::a('Administratorzy', Url::toRoute(['admin/admins']), ['class' => 'btn btn-secondary']) ?>
    </div>
</div>

<?php foreach (Yii::$app->session->getAllFlashes() as $type => $messages): ?>
    <div class="alert alert-<?= $type === 'error' ? 'danger' : $type ?>">
        <?= implode('<br>', (array) $messages) ?>
    </div>
<?php endforeach; ?>

<table class="table table-bordered table-hover table-sm">
    <thead class="thead-dark">
        <tr>
            <th>ID</th>
            <th>Shop</th>
            <th>Aktywny</th>
            <th>Ostatnia sync.</th>
            <th>Produkty</th>
            <th>Klienci</th>
            <th>Zamówienia</th>
            <th>Błędy</th>
            <th>Akcje</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $user): ?>
        <?php $s = $summary[$user->id] ?? []; ?>
        <tr>
            <td><?= $user->id ?></td>
            <td><?= Html::encode($user->username) ?></td>
            <td><?= $user->active
                    ? '<span class="badge badge-success">Tak</span>'
                    : '<span class="badge badge-secondary">Nie</span>' ?></td>
            <td><?= Html::encode($s['lastFinished'] ?? '-') ?></td>
            <td id="count-product-<?= $user->id ?>"><?= $s['counts']['product'] ?? 0 ?></td>
            <td id="count-customer-<?= $user->id ?>"><?= $s['counts']['customer'] ?? 0 ?></td>
            <td id="count-order-<?= $user->id ?>"><?= $s['counts']['order'] ?? 0 ?></td>
            <td>
                <?php if (($s['errors'] ?? 0) > 0): ?>
                    <span class="badge badge-danger"><?= $s['errors'] ?></span>
                <?php else: ?>
                    <span class="badge badge-success">0</span>
                <?php endif; ?>
            </td>
            <td>
                <?= Html::a('Kolejki', Url::toRoute(['admin/view', 'id' => $user->id]), ['class' => 'btn btn-sm btn-primary']) ?>
                <?= Html::a('Ustawienia', Url::toRoute(['admin/dashboard', 'id' => $user->id]), ['class' => 'btn btn-sm btn-secondary']) ?>
                <button class="btn btn-sm btn-outline-secondary refresh-counts" data-id="<?= $user->id ?>" title="Odśwież liczniki">↻</button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
document.querySelectorAll('.refresh-counts').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        this.disabled = true;
        fetch('<?= Url::toRoute(['admin/refresh-feed-counts']) ?>?id=' + id)
            .then(r => r.json())
            .then(data => {
                document.getElementById('count-product-'  + id).textContent = data.product;
                document.getElementById('count-customer-' + id).textContent = data.customer;
                document.getElementById('count-order-'    + id).textContent = data.order;
                this.disabled = false;
            });
    });
});
</script>
