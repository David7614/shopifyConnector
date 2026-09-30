<?php
use yii\helpers\Html;
use yii\helpers\Url;
use app\models\IntegrationData;
use app\modules\shopify\models\Product as ShopifyProduct;

/** @var yii\web\View $this */
/** @var app\models\User $user */
/** @var array $feedUrls */
/** @var array $xmlCounts */

$this->title = 'Ustawienia — ' . $user->username;
?>

<div style="display:flex; align-items:center; justify-content:space-between; margin:20px 0 16px;">
    <h2 style="margin:0;">Ustawienia — <?= Html::encode($user->username) ?></h2>
    <?= Html::a('← Użytkownicy', Url::toRoute(['admin/index']), ['class' => 'btn btn-default btn-sm']) ?>
</div>

<?php foreach (Yii::$app->session->getAllFlashes() as $type => $messages): ?>
    <div class="alert alert-<?= $type === 'error' ? 'danger' : $type ?>">
        <?= implode('<br>', (array) $messages) ?>
    </div>
<?php endforeach; ?>

<div class="row">
    <!-- Ustawienia synchronizacji -->
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Ustawienia synchronizacji</strong></div>
            <div class="panel-body">
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
                <div class="form-group">
                    <?= Html::label('Źródło kategorii produktowej', 'product_category_source') ?>
                    <?= Html::dropDownList(
                        'product_category_source',
                        $user->config->get('product_category_source') ?: ShopifyProduct::CATEGORY_SOURCE_TAXONOMY,
                        [
                            ShopifyProduct::CATEGORY_SOURCE_TAXONOMY     => 'Shopify Standard Product Taxonomy',
                            ShopifyProduct::CATEGORY_SOURCE_PRODUCT_TYPE => 'Typ produktu (productType)',
                        ],
                        ['class' => 'form-control', 'id' => 'product_category_source']
                    ) ?>
                    <p class="help-block" style="margin-bottom:6px;">
                        Zmiana wymusza pełne ponowne pobranie produktów tego sklepu.
                    </p>
                    <div class="checkbox" id="product_category_fallback_wrapper">
                        <label>
                            <?= Html::checkbox(
                                'product_category_fallback_taxonomy',
                                (bool) $user->config->get('product_category_fallback_taxonomy'),
                                ['id' => 'product_category_fallback_taxonomy']
                            ) ?>
                            Użyj taksonomii, gdy Typ produktu jest pusty
                        </label>
                    </div>
                    <?= Html::button('Pokaż przykłady', [
                        'class' => 'btn btn-default btn-sm',
                        'id'    => 'preview_categories_btn',
                        'data-url' => Url::toRoute(['admin/preview-categories', 'id' => $user->id]),
                    ]) ?>
                    <div id="preview_categories_result" style="margin-top:10px;"></div>
                </div>
                <?= Html::submitButton('Zapisz', ['class' => 'btn btn-primary']) ?>
                <?= Html::endForm() ?>
            </div>
        </div>
    </div>

    <!-- Feed URLs + integracje -->
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Feed URLs</strong></div>
            <div class="panel-body" style="padding:0;">
                <table class="table table-sm" style="margin:0;">
                    <thead style="background:#f5f5f5;">
                        <tr><th>Typ</th><th>URL</th><th>Rekordy w DB</th><th>Rekordy w XML</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Products</td>
                            <td><a href="<?= Html::encode($feedUrls['products']) ?>" target="_blank" class="small">link</a></td>
                            <td><?= $user->countDatabaseElements('products') ?></td>
                            <td><?= $xmlCounts['products'] ?? '—' ?></td>
                        </tr>
                        <tr>
                            <td>Customers</td>
                            <td><a href="<?= Html::encode($feedUrls['customers']) ?>" target="_blank" class="small">link</a></td>
                            <td><?= $user->countDatabaseElements('customers') ?></td>
                            <td><?= $xmlCounts['customers'] ?? '—' ?></td>
                        </tr>
                        <tr>
                            <td>Orders</td>
                            <td><a href="<?= Html::encode($feedUrls['orders']) ?>" target="_blank" class="small">link</a></td>
                            <td><?= $user->countDatabaseElements('orders') ?></td>
                            <td><?= $xmlCounts['orders'] ?? '—' ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading"><strong>Ostatnie integracje</strong></div>
            <div class="panel-body" style="padding:0;">
                <table class="table table-sm" style="margin:0;">
                    <thead style="background:#f5f5f5;">
                        <tr><th>Typ</th><th>Data</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Products</td>
                            <td><?= Html::encode(IntegrationData::getDataValue('last_products_integration_date', $user->id) ?? '—') ?></td>
                        </tr>
                        <tr>
                            <td>Orders</td>
                            <td><?= Html::encode(IntegrationData::getDataValue('last_orders_integration_date', $user->id) ?? '—') ?></td>
                        </tr>
                        <tr>
                            <td>Customers</td>
                            <td><?= Html::encode(IntegrationData::getDataValue('last_customer_integration_date', $user->id) ?? '—') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div style="margin-top:8px;">
    <?= Html::a('Zobacz kolejki użytkownika', Url::toRoute(['admin/view', 'id' => $user->id]), ['class' => 'btn btn-default btn-sm']) ?>
</div>

<script>
(function () {
    const SOURCE_PRODUCT_TYPE = <?= json_encode(ShopifyProduct::CATEGORY_SOURCE_PRODUCT_TYPE) ?>;

    const sourceSelect    = document.getElementById('product_category_source');
    const fallbackWrapper = document.getElementById('product_category_fallback_wrapper');
    const fallbackInput   = document.getElementById('product_category_fallback_taxonomy');
    const previewBtn      = document.getElementById('preview_categories_btn');
    const previewResult   = document.getElementById('preview_categories_result');

    // The fallback only means anything when the source can come up empty.
    function syncFallbackState() {
        const enabled = sourceSelect.value === SOURCE_PRODUCT_TYPE;
        fallbackInput.disabled = !enabled;
        fallbackWrapper.style.opacity = enabled ? '1' : '0.5';
    }

    sourceSelect.addEventListener('change', syncFallbackState);
    syncFallbackState();

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    }

    function cell(value, highlighted) {
        const empty = value === '' || value === null;
        const text  = empty ? '<em style="color:#999;">(brak)</em>' : escapeHtml(value);
        const style = highlighted ? ' style="background:#eef7ee; font-weight:600;"' : '';
        return '<td' + style + '>' + text + '</td>';
    }

    function renderRows(data) {
        if (!data.rows.length) {
            return '<p class="text-muted">Sklep nie zwrócił żadnych produktów.</p>';
        }

        const usesProductType = data.source === SOURCE_PRODUCT_TYPE;

        let html = '<table class="table table-condensed table-bordered" style="margin:0;">'
            + '<thead style="background:#f5f5f5;"><tr>'
            + '<th>Produkt</th>'
            + '<th' + (usesProductType ? '' : ' style="background:#eef7ee;"') + '>Taksonomia</th>'
            + '<th' + (usesProductType ? ' style="background:#eef7ee;"' : '') + '>Typ produktu</th>'
            + '</tr></thead><tbody>';

        data.rows.forEach(row => {
            html += '<tr>'
                + '<td>' + escapeHtml(row.title) + '</td>'
                + cell(row.taxonomy, !usesProductType)
                + cell(row.productType, usesProductType)
                + '</tr>';
        });

        return html + '</tbody></table>'
            + '<p class="help-block">Wyróżniona kolumna to aktualnie zapisane źródło. Próbka 10 produktów, prosto z API.</p>';
    }

    previewBtn.addEventListener('click', function () {
        previewBtn.disabled = true;
        previewResult.innerHTML = '<span class="text-muted">Pobieram z Shopify...</span>';

        fetch(previewBtn.dataset.url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => {
                previewResult.innerHTML = data.error
                    ? '<div class="alert alert-danger" style="margin:0;">' + escapeHtml(data.error) + '</div>'
                    : renderRows(data);
            })
            .catch(e => {
                previewResult.innerHTML = '<div class="alert alert-danger" style="margin:0;">Błąd: ' + escapeHtml(e.message) + '</div>';
            })
            .finally(() => { previewBtn.disabled = false; });
    });
})();
</script>
