<?php
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var int $running */
/** @var int $errors */
/** @var int $pending */
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Monitor kolejek</h2>
    <div>
        <?= Html::beginForm(Url::toRoute(['admin/prepare-queue']), 'post', ['style' => 'display:inline']) ?>
        <?= Html::submitButton('Przygotuj kolejki', [
            'class'   => 'btn btn-success mr-2',
            'onclick' => 'return confirm("Przygotować kolejki dla wszystkich użytkowników?")',
        ]) ?>
        <?= Html::endForm() ?>
        <?= Html::a('← Użytkownicy', Url::toRoute(['admin/index']), ['class' => 'btn btn-secondary']) ?>
    </div>
</div>

<?php foreach (Yii::$app->session->getAllFlashes() as $type => $messages): ?>
    <div class="alert alert-<?= $type === 'error' ? 'danger' : $type ?>">
        <?= implode('<br>', (array) $messages) ?>
    </div>
<?php endforeach; ?>

<!-- Karty zdrowia -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-center border-primary">
            <div class="card-body">
                <div class="display-4 text-primary"><?= $running ?></div>
                <div>W trakcie</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center border-<?= $errors > 0 ? 'danger' : 'success' ?>">
            <div class="card-body">
                <div class="display-4 text-<?= $errors > 0 ? 'danger' : 'success' ?>"><?= $errors ?></div>
                <div>Błędy</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center border-secondary">
            <div class="card-body">
                <div class="display-4 text-secondary"><?= $pending ?></div>
                <div>Oczekujące</div>
            </div>
        </div>
    </div>
</div>

<!-- Sekcje z auto-refresh -->
<div class="mb-2 d-flex align-items-center">
    <label class="mr-2 mb-0">Auto-refresh:</label>
    <select id="refresh-interval" class="form-control form-control-sm" style="width:auto">
        <option value="0">Wyłączony</option>
        <option value="15">15s</option>
        <option value="30" selected>30s</option>
        <option value="60">60s</option>
    </select>
</div>

<?php foreach (['running' => 'W trakcie', 'errors' => 'Błędy', 'recent' => 'Ostatnio wykonane', 'pending' => 'Oczekujące'] as $section => $label): ?>
<div class="card mb-3" id="section-<?= $section ?>">
    <div class="card-header d-flex justify-content-between align-items-center" style="cursor:pointer" onclick="toggleSection('<?= $section ?>')">
        <strong><?= $label ?></strong>
        <span class="badge badge-secondary section-count" id="count-<?= $section ?>">…</span>
    </div>
    <div class="card-body p-0" id="body-<?= $section ?>">
        <div class="text-center p-3 text-muted">Ładowanie…</div>
    </div>
</div>
<?php endforeach; ?>

<script>
const sectionsUrl = '<?= Url::toRoute(['admin/queues-sections']) ?>';
const collapsed = JSON.parse(localStorage.getItem('queues_collapsed') || '{}');

function toggleSection(section) {
    const body = document.getElementById('body-' + section);
    collapsed[section] = !collapsed[section];
    body.style.display = collapsed[section] ? 'none' : '';
    localStorage.setItem('queues_collapsed', JSON.stringify(collapsed));
}

function renderTable(rows) {
    if (!rows.length) return '<p class="text-muted p-3">Brak</p>';
    const statusMap = {0:'Oczekuje', 1:'W trakcie', 2:'Wykonana', 99:'Błąd', 5:'Pominięta'};
    let html = '<table class="table table-sm table-bordered mb-0"><thead class="thead-light"><tr>'
        + '<th>ID</th><th>Użytkownik</th><th>Typ</th><th>Status</th><th>Strona</th><th>Uruchomiona</th><th>Zakończona</th><th>Akcje</th>'
        + '</tr></thead><tbody>';
    rows.forEach(q => {
        html += `<tr>
            <td>${q.id}</td>
            <td>${q.username}</td>
            <td>${q.type}</td>
            <td>${statusMap[q.status] ?? q.status}</td>
            <td>${q.page}/${q.max_page}</td>
            <td>${q.executed_at ?? '-'}</td>
            <td>${q.finished_at ?? '-'}</td>
            <td>
                <form method="post" action="<?= Url::toRoute(['admin/reset-queue']) ?>" style="display:inline">
                    <input type="hidden" name="_csrf" value="<?= Yii::$app->request->getCsrfToken() ?>">
                    <input type="hidden" name="queueId" value="${q.id}">
                    <button class="btn btn-xs btn-warning" onclick="return confirm('Reset #${q.id}?')">Reset</button>
                </form>
            </td>
        </tr>`;
    });
    return html + '</tbody></table>';
}

function loadSection(section) {
    fetch(sectionsUrl + '?section=' + section)
        .then(r => r.json())
        .then(rows => {
            document.getElementById('body-' + section).innerHTML = renderTable(rows);
            document.getElementById('count-' + section).textContent = rows.length;
        });
}

function loadAll() {
    ['running', 'errors', 'recent', 'pending'].forEach(s => {
        if (!collapsed[s]) loadSection(s);
    });
}

// Apply collapsed state
Object.keys(collapsed).forEach(s => {
    const body = document.getElementById('body-' + s);
    if (body && collapsed[s]) body.style.display = 'none';
});

loadAll();

// Auto-refresh
let refreshTimer = null;
document.getElementById('refresh-interval').addEventListener('change', function() {
    clearInterval(refreshTimer);
    if (this.value > 0) {
        refreshTimer = setInterval(loadAll, this.value * 1000);
    }
});
refreshTimer = setInterval(loadAll, 30000);
</script>
