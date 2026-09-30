<?php

namespace tests\unit\models;

use app\models\Queue;
use Codeception\Test\Unit;

class QueueFetchStateTest extends Unit
{
    public function testDropsCursorState()
    {
        $stripped = Queue::stripFetchState([
            'endCursor'   => 'eyJsYXN0X2lkIjoxNTM3Nzc2MDU1MTI5OH0=',
            'hasNextPage' => false,
            'tokenerrors' => 0,
        ]);

        $this->assertArrayNotHasKey('endCursor', $stripped);
        $this->assertArrayNotHasKey('hasNextPage', $stripped);
        $this->assertSame(['tokenerrors' => 0], $stripped);
    }

    /** objects_done is what tells Phase 1 from Phase 2, so it must survive. */
    public function testKeepsPhaseMarker()
    {
        $stripped = Queue::stripFetchState([
            'objects_done' => 1,
            'endCursor'    => 'abc',
        ]);

        $this->assertSame(['objects_done' => 1], $stripped);
    }

    public function testEmptyParametersStayEmpty()
    {
        $this->assertSame([], Queue::stripFetchState([]));
    }

    /** unserialize() of an empty column returns false, not an array. */
    public function testNonArrayParametersBecomeEmptyArray()
    {
        $this->assertSame([], Queue::stripFetchState(false));
        $this->assertSame([], Queue::stripFetchState(null));
    }
}
