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

    public static function executeQueue(string $type, array $config = [])
    {
        if (!isset($config['forceId'])) {
            $config['forceId'] = 0;
        }

        $queue = self::getLastestQueue($type, $config);

        if ($queue == null) {
            return ExitCode::OK;
        }

        echo '>--- executeQueue - T1' . PHP_EOL;

        if ($queue->integrated === Queue::RUNNING && $config['forceId'] === 0) {
            // prevent double run

            $currentDate = new DateTime(date('Y-m-d H:i:s'));
            $excutedDate = new DateTime($queue->executed_at);

            $diffInSeconds = $currentDate->getTimestamp() - $excutedDate->getTimestamp();

            if ($diffInSeconds > 3600) {
                $queue->setPendingStatus();
                return ExitCode::OK;
            }

            return ExitCode::OK;
        }

        if (!$queue->checkQueueConstraints()) {
            $queue->setErrorStatus('job disabled');
            return ExitCode::OK;
        }

        $queue->setRunningStatus();

        $user = $queue->getCurrentUser();

        if (!$user) {
            $queue->delete();
            return ExitCode::ERR;
        }

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
            $generated = $xmlGenerator->generate($processType);

            if (!$generated) {
                $queue->setErrorStatus();
                throw new Exception('Cannot generate ' . $type . ' feed. Cannot save file');
            }

            if (isset($config['forcePage'])) {
                $queue->setErrorStatus();
                die();
            }

            // if($xml_generator->isFinished()) {
            // zmiast 10 to podmienic na ENUM
            if ($generated === 10) {
                // czyli skończone
                $queue->setExecutedStatus();
                $queue->setCountErrors(0);
                return ExitCode::OK;
                // return true;
            }

            $queue->setPendingStatus();
            $queue->setCountErrors(0);

            return ExitCode::OK;
        } catch (Exception $e) {
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