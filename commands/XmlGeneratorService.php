<?php
declare(strict_types=1);

namespace app\commands;

use DateTime;
use Exception;
use app\models\Queue;
use app\modules\xml_generator\src\XmlFeed;
use yii\console\ExitCode;

class XmlGeneratorService
{
    public static function getLastestQueue(string $type, array $config = [])
    {
        if ($config['forceId'] !== 0) {
            return Queue::findOne($config['forceId']);
        }

        if (isset($config['pararel_processing']) && $config['pararel_processing']) {
            return Queue::findPararelForType($type, $config['offset']); // 2 - offset
        }


        $queue = Queue::findLastForType($type);
        

        return $queue;
    }

    public static function loopQueue(string $type, array $config = [], int $maxSeconds = 540): int
    {
        $start = time();
        $iterations = 0;

        echo "[{$type}] Loop started, will run for up to {$maxSeconds}s" . PHP_EOL;

        while ((time() - $start) < $maxSeconds) {
            $elapsed = time() - $start;
            echo "[{$type}] --- iteration #{$iterations} at {$elapsed}s ---" . PHP_EOL;

            $result = self::executeQueue($type, $config);

            $iterations++;

            if ($result === ExitCode::ERR) {
                echo "[{$type}] Queue error — stopping loop" . PHP_EOL;
                break;
            }

            // brief pause to avoid hammering DB between iterations
            sleep(1);
        }

        $total = time() - $start;
        echo "[{$type}] Loop finished after {$total}s, {$iterations} iteration(s)" . PHP_EOL;

        return ExitCode::OK;
    }

    public static function executeQueue(string $type, array $config = [])
    {
        if (!isset($config['forceId'])) {
            $config['forceId'] = 0;
        }

        $queue = self::getLastestQueue($type, $config);

        if ($queue == null) {
            echo "[{$type}] No queue found" . PHP_EOL;
            return ExitCode::OK;
        }

        echo "[{$type}] Queue #{$queue->id} status={$queue->integrated} page={$queue->page}/{$queue->max_page}" . PHP_EOL;

        if ($queue->integrated === Queue::RUNNING && $config['forceId'] === 0) {
            $currentDate = new DateTime(date('Y-m-d H:i:s'));
            $excutedDate = new DateTime($queue->executed_at);
            $diffInSeconds = $currentDate->getTimestamp() - $excutedDate->getTimestamp();

            echo "[{$type}] Already RUNNING for {$diffInSeconds}s — skipping" . PHP_EOL;

            if ($diffInSeconds > 3600) {
                echo "[{$type}] Stale RUNNING (>1h), resetting to PENDING" . PHP_EOL;
                $queue->setPendingStatus();
            }

            return ExitCode::OK;
        }

        if (!$queue->checkQueueConstraints()) {
            echo "[{$type}] checkQueueConstraints failed — job disabled" . PHP_EOL;
            $queue->setErrorStatus('job disabled');
            return ExitCode::OK;
        }

        $queue->setRunningStatus();

        $user = $queue->getCurrentUser();

        if (!$user) {
            echo "[{$type}] User not found for queue #{$queue->id} — deleting queue" . PHP_EOL;
            $queue->delete();
            return ExitCode::ERR;
        }

        echo "[{$type}] User: {$user->username} (shop_type={$user->shop_type})" . PHP_EOL;

        $xmlGenerator = new XmlFeed();
        $xmlGenerator->setType($type);
        $xmlGenerator->setUser($user);

        if (isset($config['forcePage'])) {
            $queue->page = $config['forcePage'];
        }

        $xmlGenerator->setQueue($queue);

        try {
            $parameters = $queue->additionalParameters;
            $processType = isset($parameters['objects_done']) ? null : 'objects';

            echo "[{$type}] processType={$processType}" . PHP_EOL;

            $generated = $xmlGenerator->generate($processType);

            echo "[{$type}] generate() returned: {$generated}" . PHP_EOL;

            if (!$generated) {
                $queue->setErrorStatus();
                throw new Exception('Cannot generate ' . $type . ' feed. Cannot save file');
            }

            if (isset($config['forcePage'])) {
                $queue->setErrorStatus();
                die();
            }

            if ($generated === 10) {
                echo "[{$type}] FINISHED — setting executed status" . PHP_EOL;
                $queue->setExecutedStatus();
                $queue->setCountErrors(0);
                return ExitCode::OK;
            }

            echo "[{$type}] Partial — setting pending for next run" . PHP_EOL;
            $queue->setPendingStatus();
            $queue->setCountErrors(0);

            return ExitCode::OK;
        } catch (Exception $e) {
            echo "[{$type}] EXCEPTION: " . $e->getMessage() . PHP_EOL;
            $queue->raiseCountErrors();

            if ($queue->getCountErrors() < 30) {
                $queue->setPendingStatus();
            } else {
                $queue->setErrorStatus($e->getMessage());
            }

            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}