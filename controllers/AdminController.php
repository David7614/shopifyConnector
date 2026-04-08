<?php
declare(strict_types=1);

namespace app\controllers;

use app\models\IntegrationData;
use app\models\Queue;
use app\models\User;
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
                    'customer' => $user->countDatabaseElements('customer'),
                    'order'    => $user->countDatabaseElements('order'),
                ],
                'errors' => Queue::find()
                    ->where(['current_integrate_user' => $user->id, 'integrated' => Queue::ERROR])
                    ->count(),
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

            Yii::$app->session->addFlash('success', 'Ustawienia zapisane');
            return $this->redirect(Url::toRoute(['admin/dashboard', 'id' => $user->id]));
        }

        $feedUrls = $this->buildFeedUrls($user);

        return $this->render('update', [
            'user'     => $user,
            'feedUrls' => $feedUrls,
        ]);
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

        return $this->render('view', [
            'user'         => $user,
            'queues'       => $queues,
            'statusCounts' => $statusCounts,
            'typeFilter'   => $typeFilter,
            'statusFilter' => $statusFilter,
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

    public function actionResetQueue(int $queueId)
    {
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
            'customer' => $user->countDatabaseElements('customer'),
            'order'    => $user->countDatabaseElements('order'),
        ];
    }

    // -------------------------------------------------------------------------
    // Admins — zarządzanie administratorami
    // -------------------------------------------------------------------------

    public function actionAdmins()
    {
        $auth    = Yii::$app->authManager;
        $adminRole = $auth->getRole('admin');
        $adminIds = $adminRole ? array_keys($auth->getUserIdsByRole('admin')) : [];
        $admins  = $adminIds ? User::find()->where(['id' => $adminIds])->all() : [];

        $error = null;

        if (Yii::$app->request->isPost) {
            $action = Yii::$app->request->post('action');

            if ($action === 'add') {
                $username = trim(Yii::$app->request->post('username', ''));
                $email    = trim(Yii::$app->request->post('email', ''));
                $password = Yii::$app->request->post('password', '');

                $user = new User();
                $user->register($username, $email, $password);

                if ($user->save()) {
                    $auth->assign($auth->getRole('admin'), $user->id);
                    Yii::$app->session->addFlash('success', "Administrator {$username} dodany");
                } else {
                    $error = 'Błąd zapisu: ' . json_encode($user->errors);
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
