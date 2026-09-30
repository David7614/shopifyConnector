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
    /**
     * How many failing queues in a row end the loop. A failing queue is normally
     * marked ERROR and drops out of the pool, so reaching this many in a row
     * means something systemic is wrong (storage, API) and grinding through the
     * rest of the window would only pile up errors.
     */
    const MAX_CONSECUTIVE_FAILURES = 10;

    /** No point retrying: the shop or the credentials are gone. */
    const ERROR_PERMANENT = 'permanent';
    /** Worth retrying: network, throttling, upstream hiccup. */
    const ERROR_TRANSIENT = 'transient';
    /** Anything we have not learned to recognize yet. */
    const ERROR_UNKNOWN = 'unknown';

    /** Attempts before a queue is parked in ERROR, per error kind. */
    const MAX_ATTEMPTS_PERMANENT = 3;
    const MAX_ATTEMPTS_DEFAULT = 30;

    /** Backoff bounds, in seconds. */
    const BACKOFF_BASE_SECONDS = 300;
    const BACKOFF_MAX_SECONDS = 21600;

    /**
     * Sorts a failure message into something the retry policy can act on.
     *
     * Matching is on substrings of what Shopify and the HTTP layer actually
     * say, because that is all we get - there are no error codes to key on.
     */
    public static function classifyError(string $message): string
    {
        $haystack = mb_strtolower($message);

        $permanent = [
            'unavailable shop',
            'shop is unavailable',
            'invalid api key',
            'unauthorized',
            'access denied',
            'forbidden',
            'no session',
            '401',
            '403',
            '404',
        ];

        foreach ($permanent as $needle) {
            if (mb_strpos($haystack, $needle) !== false) {
                return self::ERROR_PERMANENT;
            }
        }

        $transient = [
            'timed out',
            'timeout',
            'could not resolve',
            'connection',
            'throttled',
            'too many requests',
            'service unavailable',
            'bad gateway',
            'gateway timeout',
            'internal server error',
            '429',
            '500',
            '502',
            '503',
            '504',
        ];

        foreach ($transient as $needle) {
            if (mb_strpos($haystack, $needle) !== false) {
                return self::ERROR_TRANSIENT;
            }
        }

        return self::ERROR_UNKNOWN;
    }

    /** How many attempts a queue of this error kind gets before it is parked. */
    public static function maxAttemptsFor(string $kind): int
    {
        return $kind === self::ERROR_PERMANENT
            ? self::MAX_ATTEMPTS_PERMANENT
            : self::MAX_ATTEMPTS_DEFAULT;
    }

    /**
     * Exponential backoff, capped. Without it a failing queue is retried on
     * every single iteration, so one broken shop burns the whole run and races
     * through its 30 attempts in seconds.
     */
    public static function backoffSeconds(int $attempts): int
    {
        if ($attempts < 1) {
            $attempts = 1;
        }

        // Cap the exponent before shifting, so a long-failing queue cannot
        // overflow its way back to a tiny delay.
        $exponent = min($attempts - 1, 12);
        $delay = self::BACKOFF_BASE_SECONDS * (2 ** $exponent);

        return (int) min($delay, self::BACKOFF_MAX_SECONDS);
    }

    /**
     * Decides whether a failed iteration should end the loop.
     *
     * Hitting the same queue twice in a row means it failed without being taken
     * out of the pool, so the next iteration would pick it again - that is a
     * spin, and it stops immediately. Distinct failures only stop the loop once
     * they pile up, so one broken shop no longer costs every other shop the
     * remainder of the run.
     */
    public static function shouldStopAfterFailure(int $consecutiveFailures, bool $sameQueueAsPrevious): bool
    {
        if ($sameQueueAsPrevious) {
            return true;
        }

        return $consecutiveFailures >= self::MAX_CONSECUTIVE_FAILURES;
    }

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
        $consecutiveFailures = 0;
        $lastFailedQueueId = null;

        echo "[{$type}] Loop started, will run for up to {$maxSeconds}s" . PHP_EOL;

        while ((time() - $start) < $maxSeconds) {
            $elapsed = time() - $start;
            echo "[{$type}] --- iteration #{$iterations} at {$elapsed}s ---" . PHP_EOL;

            $queue = self::getLastestQueue($type, array_merge(['forceId' => 0], $config));

            if ($queue === null) {
                echo "[{$type}] No queue found — stopping loop" . PHP_EOL;
                break;
            }

            $queueId = $queue->id;

            $result = self::executeQueue($type, $config);

            $iterations++;

            if ($result === ExitCode::UNSPECIFIED_ERROR) {
                $sameQueueAsPrevious = $queueId === $lastFailedQueueId;
                $consecutiveFailures++;
                $lastFailedQueueId = $queueId;

                if (self::shouldStopAfterFailure($consecutiveFailures, $sameQueueAsPrevious)) {
                    echo $sameQueueAsPrevious
                        ? "[{$type}] Queue #{$queueId} failed twice in a row without leaving the pool — stopping loop" . PHP_EOL
                        : "[{$type}] {$consecutiveFailures} queues failed in a row — stopping loop" . PHP_EOL;
                    break;
                }

                echo "[{$type}] Queue #{$queueId} failed ({$consecutiveFailures} in a row) — moving on to the next queue" . PHP_EOL;
            } else {
                $consecutiveFailures = 0;
                $lastFailedQueueId = null;
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
            return ExitCode::UNSPECIFIED_ERROR;
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
                // The feed signals failure with a bare status code, so prefer
                // whatever it recorded as the actual reason.
                throw new Exception(
                    $xmlGenerator->getLastError() ?: 'Cannot generate ' . $type . ' feed. Cannot save file'
                );
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
            $message = $e->getMessage();
            $kind = self::classifyError($message);

            echo "[{$type}] EXCEPTION ({$kind}): {$message}" . PHP_EOL;

            $queue->raiseCountErrors();
            $attempts = $queue->getCountErrors();
            $maxAttempts = self::maxAttemptsFor($kind);

            $queue->recordFailure($message, $kind);

            if ($attempts < $maxAttempts) {
                $delay = self::backoffSeconds($attempts);
                $queue->setPendingStatus();
                $queue->deferBy($delay);

                echo "[{$type}] Attempt {$attempts}/{$maxAttempts} — retrying in " . round($delay / 60) . " min" . PHP_EOL;
            } else {
                $queue->setErrorStatus($message);

                echo "[{$type}] Gave up after {$attempts} attempts ({$kind}) — queue parked in ERROR" . PHP_EOL;
            }

            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}