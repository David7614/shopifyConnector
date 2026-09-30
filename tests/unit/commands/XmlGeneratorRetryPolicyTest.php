<?php

namespace tests\unit\commands;

use app\commands\XmlGeneratorService;
use Codeception\Test\Unit;

class XmlGeneratorRetryPolicyTest extends Unit
{
    /** The message a dead or uninstalled shop actually produces. */
    public function testUnavailableShopIsPermanent()
    {
        $this->assertSame(
            XmlGeneratorService::ERROR_PERMANENT,
            XmlGeneratorService::classifyError('Unavailable Shop')
        );
    }

    public function testClassificationIgnoresCase()
    {
        $this->assertSame(
            XmlGeneratorService::ERROR_PERMANENT,
            XmlGeneratorService::classifyError('HTTP 401 Unauthorized')
        );
    }

    public function testThrottlingIsTransient()
    {
        $this->assertSame(
            XmlGeneratorService::ERROR_TRANSIENT,
            XmlGeneratorService::classifyError('Throttled: too many requests')
        );
    }

    public function testTimeoutIsTransient()
    {
        $this->assertSame(
            XmlGeneratorService::ERROR_TRANSIENT,
            XmlGeneratorService::classifyError('Operation timed out after 30000 ms')
        );
    }

    public function testUnrecognisedMessageIsUnknown()
    {
        $this->assertSame(
            XmlGeneratorService::ERROR_UNKNOWN,
            XmlGeneratorService::classifyError('Cannot generate product feed. Cannot save file')
        );
    }

    public function testPermanentErrorsGiveUpEarly()
    {
        $this->assertSame(
            XmlGeneratorService::MAX_ATTEMPTS_PERMANENT,
            XmlGeneratorService::maxAttemptsFor(XmlGeneratorService::ERROR_PERMANENT)
        );
    }

    public function testTransientAndUnknownKeepTheFullBudget()
    {
        $this->assertSame(
            XmlGeneratorService::MAX_ATTEMPTS_DEFAULT,
            XmlGeneratorService::maxAttemptsFor(XmlGeneratorService::ERROR_TRANSIENT)
        );
        $this->assertSame(
            XmlGeneratorService::MAX_ATTEMPTS_DEFAULT,
            XmlGeneratorService::maxAttemptsFor(XmlGeneratorService::ERROR_UNKNOWN)
        );
    }

    public function testBackoffStartsAtBaseAndDoubles()
    {
        $this->assertSame(XmlGeneratorService::BACKOFF_BASE_SECONDS, XmlGeneratorService::backoffSeconds(1));
        $this->assertSame(XmlGeneratorService::BACKOFF_BASE_SECONDS * 2, XmlGeneratorService::backoffSeconds(2));
        $this->assertSame(XmlGeneratorService::BACKOFF_BASE_SECONDS * 4, XmlGeneratorService::backoffSeconds(3));
    }

    public function testBackoffIsCapped()
    {
        $this->assertSame(XmlGeneratorService::BACKOFF_MAX_SECONDS, XmlGeneratorService::backoffSeconds(30));
    }

    /** A long-failing queue must never wrap back round to a short delay. */
    public function testBackoffStaysCappedForVeryHighAttemptCounts()
    {
        $this->assertSame(XmlGeneratorService::BACKOFF_MAX_SECONDS, XmlGeneratorService::backoffSeconds(1000));
    }

    public function testBackoffTreatsZeroAsFirstAttempt()
    {
        $this->assertSame(XmlGeneratorService::BACKOFF_BASE_SECONDS, XmlGeneratorService::backoffSeconds(0));
    }

    /** Streaks are counted per feed type, so the keys must not collide. */
    public function testPermanentFailureKeysAreDistinctPerType()
    {
        $keys = [
            XmlGeneratorService::permanentFailureKey('product'),
            XmlGeneratorService::permanentFailureKey('customer'),
            XmlGeneratorService::permanentFailureKey('order'),
        ];

        $this->assertSame($keys, array_unique($keys));
        $this->assertSame('permanent_failures_product', $keys[0]);
    }
}
