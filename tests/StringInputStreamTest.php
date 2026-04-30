<?php

namespace alcamo\input_stream;

use PHPUnit\Framework\TestCase;
use alcamo\exception\{Eof, SyntaxError, Underflow};

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

    public function testExtractFixedString(): void
    {
        $stream = new StringInputStream('foo');

        $this->assertSame('foo', $stream->extractFixedString('foo'));

        $this->assertNull($stream->extractFixedString('bar'));

        $stream = new StringInputStream('foo');

        $this->expectException(SyntaxError::class);

        $this->expectExceptionMessage(
            'Syntax error, expected one of "bar" in "foo" at offset 0 ("foo")'
        );

        $stream->extractFixedString('bar');
    }

    public function testExtracWsException(): void
    {
        $stream = new StringInputStream('foo');

        $this->expectException(SyntaxError::class);

        $this->expectExceptionMessage(
            'Syntax error, expected one of "<whitespace>" in "foo" '
                . 'at offset 0 ("foo")'
        );

        $stream->extractWs(true);
    }

    public function testExtractWsAndComments(): void
    {
        $text = <<<EOT
Lorem ipsum dolor sit amet, ; first line
  consetetur sadipscing elitr;second line
    ; further comment
sed diam nonumy eirmod tempor invidunt
EOT;

        $stream = new StringInputStream($text);

        $this->assertSame(
            'Lorem ipsum dolor sit amet,',
            $stream->extractRegexp('/[^,]*,/')
        );

        $this->assertSame(
            " ; first line\n  ",
            $stream->extractWsAndComments()
        );

        $this->assertSame(
            'consetetur sadipscing elitr',
            $stream->extractRegexp('/[^;]*/')
        );

        $this->assertSame(
            ";second line\n    ; further comment\n",
            $stream->extractWsAndComments()
        );

        $this->assertSame(
            "sed diam nonumy eirmod tempor invidunt",
            $stream->extractRemainder()
        );
    }

    public function testExtractUntilWs(): void
    {
        $text = "Lorem ipsum   dolor \t sit\x00 amet,\r\n"
            . "consetetur  sadip\t\tscing";

        $stream = new StringInputStream($text);

        $this->assertSame('Lorem', $stream->extractUntilWs());
        $this->assertSame(' ', $stream->extract());
        $this->assertSame('ipsum   ', $stream->extractUntilWs(null, true));
        $this->assertSame("dolor \t ", $stream->extractUntilWs(null, true));
        $this->assertSame("sit", $stream->extractUntilWs(null, true, true));
        $this->assertSame("amet,", $stream->extractUntilWs(null, true, true));
        $this->assertSame("cons", $stream->extractUntilWs(4));
        $this->assertSame("et", $stream->extractUntilWs(2, true));
        $this->assertSame("etur ", $stream->extractUntilWs(5, true));
        $this->assertSame(' ', $stream->extract());
        $this->assertSame("sadip", $stream->extractUntilWs(6, true, true));
        $this->assertSame("\t", $stream->extract());
        $this->assertSame("scing", $stream->extractUntilWs());
    }

    public function testExtractToken(): void
    {
        $stream = new StringInputStream("foo \"bar' baz\"\t\r\n 'qux \"quux';");

        $this->assertSame('foo', $stream->extractToken(' '));
        $this->assertSame(' ', $stream->extractWs());
        $this->assertSame('"bar\' baz"', $stream->extractToken());
        $this->assertSame('', $stream->extractToken(null, true));
        $this->assertSame('\'qux "quux\'', $stream->extractToken(';', true));
        $this->assertNull($stream->extractToken(' '));

        $stream = new StringInputStream('foo');
        $this->assertSame('foo', $stream->extractToken(null, true));

        $stream = new StringInputStream('foo');
        $this->assertSame('foo', $stream->extractToken(',', true));
    }

    public function testExtractTokenException1(): void
    {
        $stream = new StringInputStream('"foo"bar');

        $this->expectException(SyntaxError::class);

        $this->expectExceptionMessage(
            'Syntax error, expected one of "<whitespace>" in ""foo"bar" '
                . 'at offset 5 ("bar")'
        );

        $stream->extractToken(null, true);
    }

    public function testExtractTokenException2(): void
    {
        $stream = new StringInputStream('"foo"bar');

        $this->expectException(SyntaxError::class);

        $this->expectExceptionMessage(
            'Syntax error, expected one of ";" in ""foo"bar" at offset 5 ("bar")'
        );

        $stream->extractToken(';', true);
    }

    public function testEof()
    {
        $stream = new StringInputStream('Lorem ipsum');

        $stream->extractUntil(' ', null, true);

        $this->expectException(Eof::class);
        $this->expectExceptionMessage(
            'Failed to read 6 unit(s) from stream '
                . '<alcamo\input_stream\StringInputStream>"Lorem ipsum", only 5 unit(s) available'
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
        $this->expectExceptionMessage(
            'Underflow in stream <alcamo\input_stream\StringInputStream>"Foo"'
        );

        $stream->putback();
    }
}
