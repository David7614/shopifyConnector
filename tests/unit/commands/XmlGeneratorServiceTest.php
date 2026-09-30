<?php

namespace tests\unit\commands;

use app\commands\XmlGeneratorService;
use Codeception\Test\Unit;

class XmlGeneratorServiceTest extends Unit
{
    /** One broken shop must not cost every other shop the rest of the run. */
    public function testSingleFailureDoesNotStopTheLoop()
    {
        $this->assertFalse(XmlGeneratorService::shouldStopAfterFailure(1, false));
    }

    public function testDistinctFailuresKeepGoingBelowThreshold()
    {
        $belowThreshold = XmlGeneratorService::MAX_CONSECUTIVE_FAILURES - 1;

        $this->assertFalse(XmlGeneratorService::shouldStopAfterFailure($belowThreshold, false));
    }

    public function testDistinctFailuresStopAtThreshold()
    {
        $this->assertTrue(
            XmlGeneratorService::shouldStopAfterFailure(XmlGeneratorService::MAX_CONSECUTIVE_FAILURES, false)
        );
    }

    /**
     * A queue failing twice in a row means it was not taken out of the pool, so
     * the next iteration would pick the very same row again.
     */
    public function testRepeatedQueueStopsImmediately()
    {
        $this->assertTrue(XmlGeneratorService::shouldStopAfterFailure(2, true));
    }

    public function testSuccessResetsAreCallerSideButThresholdIsInclusive()
    {
        $this->assertTrue(
            XmlGeneratorService::shouldStopAfterFailure(XmlGeneratorService::MAX_CONSECUTIVE_FAILURES + 1, false)
        );
    }
}
