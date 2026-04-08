<?php
use app\models\Queue;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var app\models\User[] $users */
/** @var array $summary */
?>

<style>
.fc-badge { display:inline-flex; align-items:center; gap:3px; padding:2px 6px 2px 5px;
            border-radius:4px; font-size:11px; margin:1px; background:#eee; color:#444;
            text-decoration:none; }
.fc-badge:hover { filter:brightness(.93); text-decoration:none; }
.fc-badge.has  { background:#e8f5e9; color:#2e7d32; }
.fc-count { font-weight:600; }
.fc-refresh { background:none; border:none; padding:0 2px; cursor:pointer; color:#aaa; font-size:13px; line-height:1; }
.fc-refresh:hover { color:#1e88e5; }
</style>

<div style="display:flex; align-items:center; justify-content:space-between; margin:20px 0 16px;">
    <h2 style="margin:0;">Użytkownicy</h2>
    <div>
        <?= Html::a('Monitor kolejek', Url::toRoute(['admin/queues']), ['class' => 'btn btn-default btn-sm', 'style' => 'margin-right:4px;']) ?>
        <?= Html::a('Administratorzy', Url::toRoute(['admin/admins']), ['class' => 'btn btn-default btn-sm']) ?>
    </div>
</div>

<?php foreach (Yii::$app->session->getAllFlashes() as $type => $messages): ?>
    <div class="alert alert-<?= $type === 'error' ? 'danger' : $type ?>">
        <?= implode('<br>', (array) $messages) ?>
    </div>
<?php endforeach; ?>

<table class="table table-bordered table-hover" style="font-size:13px;">
    <thead style="background:#333; color:#fff;">
        <tr>
            <th>ID</th>
            <th>Shop</th>
            <th>Aktywny</th>
            <th>Ostatnia sync.</th>
            <th>Feedy</th>
            <th>Błędy</th>
            <th>Akcje</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $user): ?>
        <?php $s = $summary[$user->id] ?? []; ?>
        <tr>
            <td style="color:#999; font-size:12px;"><?= $user->id ?></td>
            <td><?= Html::encode($user->username) ?></td>
            <td>
                <?php if ($user->active): ?>
                    <span style="color:#2e7d32;">&#9679;</span> <span style="color:#2e7d32;">Aktywny</span>
                <?php else: ?>
                    <span style="color:#ccc;">&#9679;</span> <span style="color:#999;">Nieaktywny</span>
                <?php endif ?>
            </td>
            <td style="font-size:12px; color:#666;"><?= Html::encode($s['lastFinished'] ?? '—') ?></td>
            <td>
                <div class="fc-wrap" data-user-id="<?= $user->id ?>">
                    <a class="fc-badge <?= ($s['counts']['product']  ?? 0) > 0 ? 'has' : '' ?>">P <span class="fc-count"><?= $s['counts']['product']  ?? 0 ?></span></a>
                    <a class="fc-badge <?= ($s['counts']['customer'] ?? 0) > 0 ? 'has' : '' ?>">K <span class="fc-count"><?= $s['counts']['customer'] ?? 0 ?></span></a>
                    <a class="fc-badge <?= ($s['counts']['order']    ?? 0) > 0 ? 'has' : '' ?>">O <span class="fc-count"><?= $s['counts']['order']    ?? 0 ?></span></a>
                    <button class="fc-refresh" data-id="<?= $user->id ?>" title="Odśwież liczniki">↻</button>
                </div>
            </td>
            <td>
                <?php $errCount = $s['errors'] ?? 0; ?>
                <?php if ($errCount > 0): ?>
                    <span style="background:#e53935; color:#fff; padding:2px 7px; border-radius:10px; font-size:12px;"><?= $errCount ?></span>
                <?php else: ?>
                    <span style="color:#4caf50;">✔</span>
                <?php endif ?>
            </td>
            <td>
                <?= Html::a('Kolejki',    Url::toRoute(['admin/view',      'id' => $user->id]), ['class' => 'btn btn-xs btn-default']) ?>
                <?= Html::a('Ustawienia', Url::toRoute(['admin/dashboard', 'id' => $user->id]), ['class' => 'btn btn-xs btn-default']) ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
(function () {
    const endpoint  = <?= json_encode(Url::toRoute(['admin/refresh-feed-counts'])) ?>;
    const csrfToken = <?= json_encode(Yii::$app->request->csrfToken) ?>;

    function refresh(btn) {
        const id   = btn.dataset.id;
        const wrap = btn.closest('.fc-wrap');
        btn.textContent = '…';
        btn.disabled    = true;

        fetch(endpoint + '?id=' + encodeURIComponent(id))
            .then(r => r.json())
            .then(data => {
                const badges = wrap.querySelectorAll('.fc-badge');
                const vals   = [data.product, data.customer, data.order];
                badges.forEach((b, i) => {
                    const n = vals[i] ?? 0;
                    b.querySelector('.fc-count').textContent = n;
                    b.classList.toggle('has', n > 0);
                });
                btn.textContent = '↻';
                btn.disabled    = false;
            })
            .catch(() => { btn.textContent = '↻'; btn.disabled = false; });
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.fc-refresh');
        if (btn) { e.preventDefault(); refresh(btn); }
    });
})();
</script>
