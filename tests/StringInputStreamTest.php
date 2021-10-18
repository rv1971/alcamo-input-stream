<?php

namespace alcamo\input_stream;

use PHPUnit\Framework\TestCase;
use alcamo\exception\{Eof, Underflow};

class StringInputStreamTest extends TestCase
{
    public function testBasics()
    {
        $text = <<<EOT
Lorem ipsum dolor sit amet, consetetur     sadipscing elitr,
sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat,
sed diam voluptua.
EOT;

        $stream = new StringInputStream($text);

        $this->assertSame($text, (string)$stream);

        $this->assertTrue($stream->isGood());

        $this->assertSame($text[0], $stream->peek());

        $this->assertSame($text[0], $stream->extract());

        $this->assertTrue($stream->isGood());

        $this->assertSame(substr($text, 1, 10), $stream->extract(10));

        $stream->putback();

        $this->assertSame($text[10], $stream->peek());

        $this->assertSame('m dolor ', $stream->extractUntil('sit'));

        $this->assertSame('si', $stream->extractUntil('#', 2));

        $this->assertSame('t amet,', $stream->extractUntil(',', null, true));

        $this->assertSame(
            ' consetetu',
            $stream->extractUntil('r', null, true, true)
        );

        $this->assertSame(' ', $stream->peek());

        $this->assertSame('     ', $stream->extractWs());

        $this->assertSame('sci', $stream->extractRegexp('/p(sci)ng/', 1));

        $this->assertNull($stream->extractRegexp('/foobarbaz/'));

        $stream->extractUntil('voluptua');

        $this->assertSame('voluptua.', $stream->getRemainder());

        $this->assertSame('voluptua.', $stream->extractRemainder());

        $this->assertFalse($stream->isGood());

        $this->assertSame(strlen($text), $stream->getSize());

        $this->assertSame($text, $stream->getContents());

        $stream = new StringInputStream('Lorem ipsum dolor sit amet.');

        $this->assertSame('Lorem', $stream->extractUntil('.', 5));

        $this->assertTrue($stream->isGood());

        $this->assertSame(5, $stream->getOffset());

        $this->assertSame(
            ' ipsum dolor sit amet',
            $stream->extractUntil('.', null, true, true)
        );

        $this->assertFalse($stream->isGood());
    }

    public function testEof()
    {
        $stream = new StringInputStream('Lorem ipsum');

        $stream->extractUntil(' ', null, true);

        $this->expectException(Eof::class);
        $this->expectExceptionMessage(
            'Failed to read 6 unit(s) from stream "Lorem ipsum", only 5 units available'
        );

        $stream->extract(6);
    }

    public function testUnderflow()
    {
        $stream = new StringInputStream('Foo');

        $stream->extract(2);

        $stream->putback();
        $stream->putback();

        $this->expectException(Underflow::class);
        $this->expectExceptionMessage('Underflow in stream "Foo"');

        $stream->putback();
    }
}
