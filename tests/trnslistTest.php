<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Mocks.php';

class TrnslistTest extends TestCase
{
    public function testMergeOrderAndDedup()
    {
        $session = session([
            'status = 0' => page([
                tx('0xhash1', 1000),
                tx('0xhash1', 950), // duplicate, older
                tx('0xhash2', 900),
            ]),
            'status = 1' => page([]),
        ]);

        $result = get_transactions($session, '0xaddr1', 10, 0);

        $this->assertCount(2, $result);
        $hashes = array_map(function ($r) { return json_decode($r, true)['hash']; }, $result);
        $this->assertSame(['0xhash1', '0xhash2'], $hashes);
    }

    public function testPendingCutoff()
    {
        $now = time();
        $session = session([
            'status = 0' => page([]),
            'status = 1' => page([
                // Only include rows that would pass the real query time >= cutoff
                tx('0xpending1', $now - 1800, 1), // within 1h
            ]),
        ]);

        $result = get_transactions($session, '0xaddr1', 10, 0);

        $this->assertCount(1, $result);
        $tx = json_decode($result[0], true);
        $this->assertSame('0xpending1', $tx['hash']);
    }

    public function testDuplicateHashWithDifferentStatusShouldDedup()
    {
        // Status=1 (pending) arrives first, followed by status=0 (confirmed) of same hash.
        $session = session([
            'status = 1' => page([
                tx('0xdup', 1000, 1),
            ]),
            'status = 0' => page([
                tx('0xdup', 900, 0),
            ]),
        ]);

        $result = get_transactions($session, '0xaddr1', 10, 0);

        // Expected behavior: only one tx per hash.
        $this->assertCount(1, $result, 'Should not emit the same hash twice even if statuses differ');
    }

    public function testPaginationOffsetAndLimit()
    {
        // Four transactions, request 2 with offset 1 -> expect hash2, hash3
        $session = session([
            'status = 0' => page([
                tx('0xhash1', 1000),
                tx('0xhash2', 900),
                tx('0xhash3', 800),
                tx('0xhash4', 700),
            ]),
            'status = 1' => page([]),
        ]);

        $result = get_transactions($session, '0xaddr1', 2, 1);

        $hashes = array_map(function ($r) { return json_decode($r, true)['hash']; }, $result);
        $this->assertSame(['0xhash2', '0xhash3'], $hashes);
    }

    public function testPagingAcrossPagesMaintainsOrder()
    {
        // Simulate Cassandra paging: page1 then page2
        $page2 = page([
            tx('0xhash3', 800),
            tx('0xhash4', 700),
        ]);
        $page1 = page([
            tx('0xhash1', 1000),
            tx('0xhash2', 900),
        ], $page2);

        $session = session([
            'status = 0' => $page1,
            'status = 1' => page([]),
        ]);

        $result = get_transactions($session, '0xaddr1', 10, 0);
        $hashes = array_map(function ($r) { return json_decode($r, true)['hash']; }, $result);

        $this->assertSame(['0xhash1', '0xhash2', '0xhash3', '0xhash4'], $hashes);
    }

    public function testDirectionAndReceivedAtFormatting()
    {
        // direction=0 should flip add1/add2, and null receivedat should fallback to time
        $session = session([
            'status = 0' => page([
                [
                    'hash' => '0xdir',
                    'time' => 1234,
                    'status' => 0,
                    'direction' => 0,
                    'add1' => 'A',
                    'add2' => 'B',
                    'receivedat' => null,
                ],
            ]),
            'status = 1' => page([]),
        ]);

        $result = get_transactions($session, '0xaddr1', 1, 0);
        $tx = json_decode($result[0], true);

        $this->assertSame('B', $tx['addr_from']);
        $this->assertSame('A', $tx['addr_to']);
        $this->assertSame(1234, $tx['receivedat'], 'receivedat should default to time when null');
    }
}
