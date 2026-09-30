<?php
declare(strict_types=1);

namespace app\controllers;

use app\commands\XmlGeneratorService;
use app\models\DisabledFeeds;
use app\models\IntegrationData;
use app\models\Queue;
use app\models\User;
use app\modules\shopify\ApiClient;
use app\modules\shopify\models\Product as ShopifyProduct;
use app\modules\xml_generator\src\XmlFeed;
use app\services\FeedStorageService;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class AdminController extends Controller
{
    public $layout = 'admin';

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['admin'],
                    ],
                ],
            ],
            'verbs' => [
                'class'   => VerbFilter::class,
                'actions' => [
                    'reset-queue'          => ['post'],
                    'reset-integration'    => ['post'],
                    'enable-feed'          => ['post'],
                    'prepare-queue'        => ['post'],
                    'save-queues-autorefresh' => ['post'],
                    'save-queues-collapsed'   => ['post'],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Index — lista użytkowników
    // -------------------------------------------------------------------------

    public function actionIndex()
    {
        $users = User::find()->orderBy(['id' => SORT_ASC])->all();

        // One query for everyone, rather than three per user in the loop below.
        $disabledByUser = [];
        foreach (DisabledFeeds::find()->all() as $disabled) {
            $disabledByUser[$disabled->user_id][$disabled->integration_type] = $disabled;
        }

        $summary = [];
        foreach ($users as $user) {
            $lastQueue = Queue::find()
                ->where(['current_integrate_user' => $user->id, 'integrated' => Queue::EXECUTED])
                ->orderBy(['finished_at' => SORT_DESC])
                ->one();

            $summary[$user->id] = [
                'lastFinished' => $lastQueue ? $lastQueue->finished_at : null,
                'counts'       => [
                    'product'  => $user->countDatabaseElements('products'),
                    'customer' => $user->countDatabaseElements('customers'),
                    'order'    => $user->countDatabaseElements('orders'),
                ],
                'errors' => Queue::find()
                    ->where(['current_integrate_user' => $user->id, 'integrated' => Queue::ERROR])
                    ->count(),
                'lastError' => $this->lastErrorFor($user->id),
                'disabled'  => $disabledByUser[$user->id] ?? [],
            ];
        }

        return $this->render('index', [
            'users'   => $users,
            'summary' => $summary,
        ]);
    }

    // -------------------------------------------------------------------------
    // Dashboard — ustawienia użytkownika
    // -------------------------------------------------------------------------

    public function actionDashboard(int $id)
    {
        $user = $this->findUser($id);

        if (Yii::$app->request->isPost) {
            $export_type = (int) Yii::$app->request->post('export_type', 0);

            if ((int) $user->getConfig()->get('export_type') !== $export_type) {
                if ($export_type === 0) {
                    $lastDate = date('Y-m-d', strtotime('-5 years'));
                    IntegrationData::setLastOrdersIntegrationDate($lastDate, $user->id);
                    $lastDate = date('Y-m-d', strtotime('-20 years'));
                    IntegrationData::setLastCustomerIntegrationDate($lastDate, $user->id);
                }
                $user->getConfig()->set('export_type', $export_type);
            }

            $user->getConfig()->set('feed_enabled', (int) Yii::$app->request->post('feed_enabled', 1));

            $this->saveCategorySourceSettings($user);

            Yii::$app->session->addFlash('success', 'Ustawienia zapisane');
            return $this->redirect(Url::toRoute(['admin/dashboard', 'id' => $user->id]));
        }

        $feedUrls = $this->buildFeedUrls($user);

        $xmlCounts = [];
        foreach (array_keys($feedUrls) as $type) {
            $xmlCounts[$type] = XmlFeed::countFeedRecords($user->uuid, $type);
        }

        return $this->render('update', [
            'user'      => $user,
            'feedUrls'  => $feedUrls,
            'xmlCounts' => $xmlCounts,
        ]);
    }

    /**
     * Read-only sample of what each category source would yield for this shop,
     * so the source can be judged before paying for a full re-fetch.
     */
    public function actionPreviewCategories(int $id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $user = $this->findUser($id);

        $session = $user->getSession();
        if (!$session) {
            return ['error' => 'Brak aktywnej sesji Shopify dla tego sklepu.'];
        }

        $graphQL = <<<Query
            query {
                products(first: 10) {
                    nodes {
                        id
                        title
                        productType
                        category {
                            id
                            name
                        }
                    }
                }
            }
        Query;

        // phpclassic/php-shopify 1.2.15 still calls curl_close(), deprecated in
        // PHP 8.5. Yii turns any notice covered by error_reporting() into an
        // exception, so on a php.ini that reports deprecations the SDK throws
        // after a perfectly successful request. Muted only around the SDK call.
        $previousReporting = error_reporting();
        error_reporting($previousReporting & ~E_DEPRECATED);

        try {
            $result = ApiClient::getClient($session)->GraphQL->post($graphQL);
        } catch (\Throwable $e) {
            return ['error' => 'Błąd zapytania do Shopify: ' . $e->getMessage()];
        } finally {
            error_reporting($previousReporting);
        }

        $nodes = $result['data']['products']['nodes'] ?? [];

        $rows = [];
        foreach ($nodes as $node) {
            $preview = new ShopifyProduct($node, $user);

            $rows[] = [
                'title'       => (string) ($node['title'] ?? ''),
                'taxonomy'    => $preview->getCategoryFromTaxonomy(),
                'productType' => $preview->getCategoryFromProductType(),
            ];
        }

        return [
            'source'   => (string) ($user->getConfig()->get('product_category_source') ?: ShopifyProduct::CATEGORY_SOURCE_TAXONOMY),
            'fallback' => (int) $user->getConfig()->get('product_category_fallback_taxonomy'),
            'rows'     => $rows,
        ];
    }

    // -------------------------------------------------------------------------
    // View — monitor kolejek użytkownika
    // -------------------------------------------------------------------------

    public function actionView(int $id)
    {
        $user = $this->findUser($id);

        $typeFilter   = Yii::$app->request->get('type', '');
        $statusFilter = Yii::$app->request->get('status', '');

        $query = Queue::find()
            ->where(['current_integrate_user' => $user->id])
            ->orderBy(['id' => SORT_DESC])
            ->limit(200);

        if ($typeFilter !== '') {
            $query->andWhere(['integration_type' => $typeFilter]);
        }
        if ($statusFilter !== '') {
            $query->andWhere(['integrated' => (int) $statusFilter]);
        }

        $queues = $query->all();

        $statusCounts = [];
        foreach ([Queue::PENDING, Queue::RUNNING, Queue::EXECUTED, Queue::ERROR, Queue::MISSED] as $s) {
            $statusCounts[$s] = Queue::find()
                ->where(['current_integrate_user' => $user->id, 'integrated' => $s])
                ->count();
        }

        $lastResets = [];
        foreach ([XmlFeed::PRODUCT, XmlFeed::CUSTOMER, XmlFeed::ORDER] as $t) {
            $lastResets[$t] = IntegrationData::getLastResetDate($t, $user->id);
        }

        $disabledFeeds = DisabledFeeds::find()
            ->where(['user_id' => $user->id])
            ->indexBy('integration_type')
            ->all();

        return $this->render('view', [
            'user'          => $user,
            'queues'        => $queues,
            'statusCounts'  => $statusCounts,
            'lastResets'    => $lastResets,
            'disabledFeeds' => $disabledFeeds,
            'typeFilter'    => $typeFilter,
            'statusFilter'  => $statusFilter,
        ]);
    }

    // -------------------------------------------------------------------------
    // Queues — globalny monitor kolejek
    // -------------------------------------------------------------------------

    public function actionQueues()
    {
        $raw = Yii::$app->session->get('queues_autorefresh');
        $initialStates = $raw ? json_decode($raw, true) : null;

        $rawC = Yii::$app->session->get('queues_collapsed');
        $collapsedSections = $rawC ? json_decode($rawC, true) : null;

        return $this->render('queues', [
            'initialStates'     => $initialStates     ?? new \stdClass(),
            'collapsedSections' => $collapsedSections ?? new \stdClass(),
        ]);
    }

    public function actionQueuesSections()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $allSections  = ['health', 'running', 'recent_hour', 'recent_started', 'errors', 'overdue', 'users'];
        $sectionsParam = Yii::$app->request->get('sections', 'all');
        $requested = $sectionsParam === 'all'
            ? $allSections
            : array_values(array_intersect(explode(',', $sectionsParam), $allSections));

        $now = date('Y-m-d H:i:s');

        $running = Queue::find()
            ->where(['integrated' => Queue::RUNNING])
            ->orderBy(['executed_at' => SORT_ASC])
            ->all();

        $errors = Queue::find()
            ->where(['integrated' => Queue::ERROR])
            ->orderBy(['finished_at' => SORT_DESC])
            ->limit(100)
            ->all();

        $overdue = Queue::find()
            ->where(['integrated' => Queue::PENDING])
            ->andWhere(['<', 'next_integration_date', $now])
            ->orderBy(['next_integration_date' => SORT_ASC])
            ->all();

        $recentDone = Queue::find()
            ->where(['integrated' => Queue::EXECUTED])
            ->andWhere(['>=', 'finished_at', date('Y-m-d H:i:s', strtotime('-24 hours'))])
            ->orderBy(['finished_at' => SORT_DESC])
            ->all();

        $recentStarted = Queue::find()
            ->andWhere(['>=', 'executed_at', date('Y-m-d H:i:s', strtotime('-20 minutes'))])
            ->orderBy(['executed_at' => SORT_DESC])
            ->all();

        $users = User::find()->indexBy('id')->all();

        $shared = compact('running', 'errors', 'overdue', 'recentDone', 'recentStarted', 'users', 'now');

        $sections = [];
        foreach ($requested as $section) {
            $sections[$section] = $this->renderPartial('_queues_content', array_merge($shared, ['section' => $section]));
        }

        return ['sections' => $sections];
    }

    public function actionSaveQueuesAutorefresh()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->session->set('queues_autorefresh', Yii::$app->request->post('states'));
        return ['ok' => true];
    }

    public function actionSaveQueuesCollapsed()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->session->set('queues_collapsed', Yii::$app->request->post('collapsed'));
        return ['ok' => true];
    }

    // -------------------------------------------------------------------------
    // Reset / uruchomienie kolejek
    // -------------------------------------------------------------------------

    public function actionResetQueue()
    {
        $queueId = (int) Yii::$app->request->post('queueId');
        $queue = Queue::findOne($queueId);
        if (!$queue) {
            throw new NotFoundHttpException("Queue #$queueId not found");
        }

        $queue->setPendingStatus();
        $queue->page     = 0;
        $queue->max_page = 0;
        $queue->save();

        Yii::$app->session->addFlash('success', "Kolejka #{$queueId} zresetowana do PENDING");

        $returnUrl = Yii::$app->request->referrer ?: Url::toRoute(['admin/queues']);
        return $this->redirect($returnUrl);
    }

    /**
     * Resets a single integration type for a user: clears the incremental-fetch
     * flags (so the next run pulls everything, like for a brand new customer)
     * and, if nothing of this type is already queued, schedules a fresh
     * fetch+XML pair for today at 01:00/01:10 so it runs right away.
     */
    public function actionResetIntegration()
    {
        $id = (int) Yii::$app->request->post('id');
        $type = (string) Yii::$app->request->post('type');

        $user = $this->findUser($id);

        if (!in_array($type, [XmlFeed::PRODUCT, XmlFeed::CUSTOMER, XmlFeed::ORDER], true)) {
            throw new NotFoundHttpException("Nieznany typ integracji: {$type}");
        }

        IntegrationData::resetIntegrationFlags($type, $user->id);
        $cleared = Queue::clearFetchStateForType($type, $user->id);
        $queued  = Queue::ensureQueuedForType($type, $user->id);

        Yii::$app->session->addFlash('success', $queued
            ? "Reset integracji „{$type}” wykonany — dodano nowe zadania do kolejki (start dziś 01:00)."
            : "Reset integracji „{$type}” wykonany — w kolejce są już zadania tego typu ({$cleared} wyczyszczono ze stanu pobierania), nowych nie dodano."
        );

        return $this->redirect(Url::toRoute(['admin/view', 'id' => $user->id]));
    }

    /**
     * Turns a feed back on after it was switched off - by the automatic
     * permanent-failure guard, or by hand. Also clears the failure streak, so
     * the shop starts from a clean slate rather than one strike from being
     * disabled again.
     */
    public function actionEnableFeed()
    {
        $id = (int) Yii::$app->request->post('id');
        $type = (string) Yii::$app->request->post('type');

        $user = $this->findUser($id);

        if (!in_array($type, [XmlFeed::PRODUCT, XmlFeed::CUSTOMER, XmlFeed::ORDER], true)) {
            throw new NotFoundHttpException("Nieznany typ integracji: {$type}");
        }

        if (DisabledFeeds::enable($user->id, $type)) {
            IntegrationData::removeData(XmlGeneratorService::permanentFailureKey($type), $user->id);
            Yii::$app->session->addFlash('success', "Feed „{$type}” włączony ponownie.");
        } else {
            Yii::$app->session->addFlash('error', "Feed „{$type}” nie był wyłączony.");
        }

        return $this->redirect(Url::toRoute(['admin/view', 'id' => $user->id]));
    }

    public function actionPrepareQueue()
    {
        Queue::prepareQueue(XmlFeed::PRODUCT);
        Queue::prepareQueue(XmlFeed::CUSTOMER);
        Queue::prepareQueue(XmlFeed::ORDER);

        Yii::$app->session->addFlash('success', 'Kolejki przygotowane dla wszystkich użytkowników');
        return $this->redirect(Url::toRoute(['admin/queues']));
    }

    // -------------------------------------------------------------------------
    // AJAX — odświeżanie liczników feedów
    // -------------------------------------------------------------------------

    public function actionRefreshFeedCounts(int $id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $user = $this->findUser($id);

        return [
            'product'  => $user->countDatabaseElements('products'),
            'customer' => $user->countDatabaseElements('customers'),
            'order'    => $user->countDatabaseElements('orders'),
        ];
    }

    // -------------------------------------------------------------------------
    // Admins — zarządzanie administratorami
    // -------------------------------------------------------------------------

    public function actionAdmins()
    {
        $auth    = Yii::$app->authManager;
        $adminRole = $auth->getRole('admin');
        $adminIds = $adminRole ? $auth->getUserIdsByRole('admin') : [];
        $admins  = $adminIds ? User::find()->where(['id' => $adminIds])->all() : [];

        $error = null;

        if (Yii::$app->request->isPost) {
            $action = Yii::$app->request->post('action');

            if ($action === 'add') {
                $username = trim(Yii::$app->request->post('username', ''));
                $email    = trim(Yii::$app->request->post('email', ''));
                $password = Yii::$app->request->post('password', '');

                $user = new User();

                try {
                    $user->register($username, $email, $password);
                    $user->user_type = 'admin';
                    $user->save(false);

                    $auth->assign($auth->getRole('admin'), $user->id);
                    Yii::$app->session->addFlash('success', "Administrator {$username} dodany");
                } catch (\Exception $e) {
                    $error = 'Błąd zapisu: ' . $e->getMessage();
                }
            }

            if ($action === 'change-password') {
                $userId      = (int) Yii::$app->request->post('user_id');
                $newPassword = Yii::$app->request->post('new_password', '');
                $target      = User::findOne($userId);

                if ($target && $newPassword) {
                    $target->password = Yii::$app->security->generatePasswordHash($newPassword);
                    $target->save(false);
                    Yii::$app->session->addFlash('success', 'Hasło zmienione');
                }
            }

            if ($action === 'delete') {
                $userId = (int) Yii::$app->request->post('user_id');
                if ($userId !== Yii::$app->user->id) {
                    $auth->revokeAll($userId);
                    Yii::$app->session->addFlash('success', "Admin #{$userId} usunięty");
                } else {
                    Yii::$app->session->addFlash('error', 'Nie możesz usunąć własnego konta');
                }
            }

            return $this->redirect(Url::toRoute(['admin/admins']));
        }

        return $this->render('admins', [
            'admins' => $admins,
            'error'  => $error,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Saves the per-shop category source. Changing either setting invalidates
     * every CATEGORYTEXT already stored, so it forces a full product re-fetch:
     * Phase 1 is incremental, and without this the existing products would keep
     * their old category until someone touched them in Shopify.
     */
    private function saveCategorySourceSettings(User $user): void
    {
        $allowedSources = [
            ShopifyProduct::CATEGORY_SOURCE_TAXONOMY,
            ShopifyProduct::CATEGORY_SOURCE_PRODUCT_TYPE,
        ];

        $source = (string) Yii::$app->request->post('product_category_source', ShopifyProduct::CATEGORY_SOURCE_TAXONOMY);
        if (!in_array($source, $allowedSources, true)) {
            $source = ShopifyProduct::CATEGORY_SOURCE_TAXONOMY;
        }

        // The fallback only means anything when the chosen source can come up empty.
        $fallback = $source === ShopifyProduct::CATEGORY_SOURCE_PRODUCT_TYPE
            ? (int) Yii::$app->request->post('product_category_fallback_taxonomy', 0)
            : 0;

        $config          = $user->getConfig();
        $currentSource   = $config->get('product_category_source') ?: ShopifyProduct::CATEGORY_SOURCE_TAXONOMY;
        $currentFallback = (int) $config->get('product_category_fallback_taxonomy');

        if ($currentSource === $source && $currentFallback === $fallback) {
            return;
        }

        $user->getConfig()->set('product_category_source', $source);
        $user->getConfig()->set('product_category_fallback_taxonomy', $fallback);

        IntegrationData::resetIntegrationFlags(XmlFeed::PRODUCT, $user->id);
        Queue::clearFetchStateForType(XmlFeed::PRODUCT, $user->id);
        $queued = Queue::ensureQueuedForType(XmlFeed::PRODUCT, $user->id);

        Yii::$app->session->addFlash('success', $queued
            ? 'Zmieniono źródło kategorii - zakolejkowano pełne ponowne pobranie produktów (start dziś 01:00).'
            : 'Zmieniono źródło kategorii - pełne pobranie wykona najbliższe zadanie produktowe, które już czeka w kolejce.'
        );
    }

    /**
     * Most recent failure reason for a user, so the user list can say what is
     * wrong instead of only how many queues are broken.
     *
     * @return array{msg:string,kind:?string}|null
     */
    private function lastErrorFor(int $userId)
    {
        $queue = Queue::find()
            ->where(['current_integrate_user' => $userId, 'integrated' => Queue::ERROR])
            ->orderBy(['finished_at' => SORT_DESC, 'id' => SORT_DESC])
            ->one();

        if (!$queue) {
            return null;
        }

        $params = $queue->getAdditionalParameters();

        if (!is_array($params) || empty($params['error_msg'])) {
            return null;
        }

        return [
            'msg'  => (string) $params['error_msg'],
            'kind' => $params['error_kind'] ?? null,
        ];
    }

    private function findUser(int $id): User
    {
        $user = User::findOne($id);
        if (!$user) {
            throw new NotFoundHttpException("User #$id not found");
        }
        return $user;
    }

    private function buildFeedUrls(User $user): array
    {
        $base = Url::home(true);
        return [
            'products'  => $base . 'xml/' . $user->uuid . '/products.xml',
            'customers' => $base . 'xml/' . $user->uuid . '/customers.xml',
            'orders'    => $base . 'xml/' . $user->uuid . '/orders.xml',
        ];
    }
}
