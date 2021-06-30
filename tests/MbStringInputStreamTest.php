<?php

namespace alcamo\input_stream;

use PHPUnit\Framework\TestCase;
use alcamo\exception\{Eof, Underflow};

class MbStringInputStreamTest extends TestCase
{
    public function testBasics()
    {
        $text = 'Äabcäöü É12ô345ß++Ð+Ùģ';

        $stream = new MbStringInputStream($text);

        $this->assertTrue($stream->isGood());

        $this->assertSame('Ä', $stream->peek());

        $this->assertSame('Äabcä', $stream->extract(5));

        $this->assertSame('ö', $stream->extract());

        $this->assertSame('ü', $stream->extractUntil(' '));

        $this->assertSame(' É', $stream->extractUntil('Ö', 2));

        $this->assertSame('12ô', $stream->extractUntil('5', 3));

        $this->assertSame('345ß', $stream->extractUntil('ß', null, true));

        $this->assertSame('++Ð+Ùģ', $stream->getRemainder());

        $this->assertSame('++Ð+', $stream->extractUntil('Ù', null, true, true));

        $this->assertTrue($stream->isGood());

        $this->assertSame('ģ', $stream->extractRemainder());

        $this->assertFalse($stream->isGood());

        $this->assertSame(22, $stream->getSize());
    }

    public function testEof()
    {
        $stream = new MbStringInputStream('Löræm ipšum');

        $this->assertSame('Löræm ', $stream->extractUntil(' ', null, true));

        $this->expectException(Eof::class);
        $this->expectExceptionMessage(
            'Eof in ' . MbStringInputStream::class
            . ': requested 6 units, available 5'
        );

        $stream->extract(6);
    }

    public function testUnderflow()
    {
        $stream = new MbStringInputStream('ďőő');

        $stream->extract(2);

        $stream->putback();
        $stream->putback();

        $this->expectException(Underflow::class);
        $this->expectExceptionMessage(
            'Underflow in ' . MbStringInputStream::class
        );

        $stream->putback();
    }
}
