<?php

namespace alcamo\input_stream;

use alcamo\exception\{Eof, SyntaxError, Underflow};

/**
 * @brief Seekable input stream made from a string
 *
 * @date Last reviewed 2021-06-15
 */
class StringInputStream implements SeekableInputStreamInterface
{
    /// White space charcaters in extractWs()
    public const WS_CHARS = " \n\r\t\v\x00";

    /**
     * @brief Regexp identifying white space in extractWsAndComments()
     *
     * Here: Optional whitespace, followed by zero or more occurences of: a
     * semicolon which introduces a comment that extends up to a linefeed,
     * optionally followed by whitespace.
     */
    public const WS_AND_COMMENTS_REGEXP = '/^\s*(;[^\n]*\n+\s*)*/';

    protected $text_;   ///< string
    protected $offset_; ///< int

    /**
     * @param $text Input stream data.
     *
     * @param $offset Offset to start at, defaults to beginning.
     */
    public function __construct(string $text, ?int $offset = null)
    {
        $this->text_ = $text;
        $this->offset_ = (int)$offset;
    }

    /// Return entire stream data
    public function __toString(): string
    {
        return $this->text_;
    }

    public function isGood(): bool
    {
        return isset($this->text_[$this->offset_]);
    }

    public function peek(): ?string
    {
        return $this->text_[$this->offset_] ?? null;
    }

    public function extract(int $count = 1): ?string
    {
        if (!isset($this->text_[$this->offset_])) {
            return null;
        }

        if (!isset($this->text_[$this->offset_ + $count - 1])) {
            /* throw already documented in InputStreamInterface::extract() */
            throw (new Eof())->setMessageContext(
                [
                    'objectType' => 'stream',
                    'requestedUnits' => $count,
                    'availableUnits' => strlen($this->text_) - $this->offset_
                ]
            );
        }

        $result = substr($this->text_, $this->offset_, $count);

        $this->offset_ += $count;

        return $result;
    }

    public function putback(): void
    {
        if ($this->offset_) {
            $this->offset_--;
        } else {
            /* throw already documented in InputStreamInterface::putback() */
            throw (new Underflow())
                ->setMessageContext(['objectType' => 'stream']);
        }
    }

    public function extractUntil(
        string $sep,
        ?int $maxCount = null,
        ?bool $extractSep = null,
        ?bool $discardSep = null
    ): ?string {
        if (!isset($this->text_[$this->offset_])) {
            return null;
        }

        $sepPos = strpos($this->text_, $sep, $this->offset_);

        if ($sepPos === false) {
            /* If not found, return $maxCount or the entire remainder. */
            if (
                isset($maxCount)
                && isset($this->text_[$this->offset_ + $maxCount])
            ) {
                $result = substr($this->text_, $this->offset_, $maxCount);
                $this->offset_ += $maxCount;
            } else {
                $result = substr($this->text_, $this->offset_);
                $this->offset_ = strlen($this->text_);
            }
        } else {
            /* If found, return $maxCount or until $sep. */
            if ($extractSep) {
                $sepPos += strlen($sep);
            }

            if (isset($maxCount) && $sepPos > $this->offset_ + $maxCount) {
                $result = substr($this->text_, $this->offset_, $maxCount);

                $this->offset_ += $maxCount;
            } else {
                $result = substr(
                    $this->text_,
                    $this->offset_,
                    $discardSep
                    ? $sepPos - $this->offset_ - strlen($sep)
                    : $sepPos - $this->offset_
                );

                $this->offset_ = $sepPos;
            }
        }

        return $result;
    }

    public function getOffset(): int
    {
        return $this->offset_;
    }

    public function getSize(): int
    {
        return strlen($this->text_);
    }

    public function getContents(): string
    {
        return $this->text_;
    }

    public function getRemainder(): ?string
    {
        return isset($this->text_[$this->offset_])
            ? substr($this->text_, $this->offset_)
            : null;
    }

    public function extractRemainder(): ?string
    {
        if (isset($this->text_[$this->offset_])) {
            $result = substr($this->text_, $this->offset_);
            $this->offset_ = strlen($this->text_);
            return $result;
        } else {
            return null;
        }
    }

    /**
     * @brief Attempt to extract extactly $text
     *
     * @return $text if successful, `null` if at end of input
     */
    public function extractFixedString(string $text): ?string
    {
        /* @throw alcamo::exception::Eof if there are characters left but less
         * than length of $text. */
        $result = $this->extract(strlen($text));

        if (isset($result) && $result != $text) {
            /* @throw alcamo::exception::SyntaxError if extracted data
             * differs from $text. */
            throw (new SyntaxError())->setMessageContext(
                [
                    'inData' => $this->text_,
                    'atOffset' => $this->offset_ - strlen($text),
                    'expectedOneOf' => $text
                ]
            );
        }

        return $result;
    }

    /**
     * @brief Extract regular expression.
     *
     * @return Match $matchNo in the array of matches returned by
     * [preg_match()](https://www.php.net/manual/en/function.preg-match).
     */
    public function extractRegexp(string $regexp, ?int $matchNo = 0): ?string
    {
        if (
            preg_match(
                $regexp,
                substr($this->text_, $this->offset_),
                $matches,
                PREG_OFFSET_CAPTURE
            )
        ) {
            $this->offset_ += strlen($matches[0][0]) + $matches[0][1];

            return $matches[(int)$matchNo][0];
        } else {
            return null;
        }
    }

    /**
     * @brief Extract whitespace characters according to
     * alcamo::input_stream::StringInputStream::WS_CHARS
     */
    public function extractWs(?bool $throwIfEmpty = null): ?string
    {
        if (!isset($this->text_[$this->offset_])) {
            return null;
        }

        $len = strspn($this->text_, static::WS_CHARS, $this->offset_);

        if (!$len) {
            /* @throw alcamo::exception::SyntaxError if $throwIfEmpty and
             * there data to extract but no whitespace. */
            throw (new SyntaxError())->setMessageContext(
                [
                    'inData' => $this->text_,
                    'atOffset' => $this->offset_,
                    'expectedOneOf' => '<whitespace>'
                ]
            );
        }

        $result = substr($this->text_, $this->offset_, $len);

        $this->offset_ += $len;

        return $result;
    }

    /**
     * @brief Extract whitespace and comments according to regexp
     * alcamo::input_stream::StringInputStream::WS_AND_COMMENTS_REGEXP
     */
    public function extractWsAndComments(): ?string
    {
        return $this->extractRegexp(static::WS_AND_COMMENTS_REGEXP);
    }

    public function extractUntilWs(
        ?int $maxCount = null,
        ?bool $extractWs = null,
        ?bool $discardWs = null
    ): ?string {
        if (!isset($this->text_[$this->offset_])) {
            return null;
        }

        $dataLen = strcspn($this->text_, static::WS_CHARS, $this->offset_);
        $totalLen = $dataLen;

        if ($extractWs) {
            $wsLen = strspn(
                $this->text_,
                static::WS_CHARS,
                $this->offset_ + $dataLen
            );

            $totalLen += $wsLen;

            if (!$discardWs) {
                $dataLen += $wsLen;
            }
        }

        if (isset($maxCount) && $totalLen > $maxCount) {
            $result = substr(
                $this->text_,
                $this->offset_,
                min($dataLen, $maxCount)
            );

            $this->offset_ += $maxCount;
        } else {
            $result = substr($this->text_, $this->offset_, $dataLen);

            $this->offset_ += $totalLen;
        }

        return $result;
    }

    /**
     * Extract the next token, which spans either from an opening quote to the
     * next quote of the same type, or up to the separator, without extracting
     * the separator itself. This allows for the separator to appear within
     * quoted strings. Quoted strings containing quotes of their own type are
     * not supported.
     *
     * @param $sep Separator string. If `null`, separator is whitespace.
     *
     * @param $extractSep Whether to extract the separator, if any. Applies to
     * all kinds of tokens, including the quoted ones.
     *
     * @return The token including quotes, if any, but not including the
     * separator, if extracted.
     */
    public function extractToken(
        ?string $sep = null,
        ?bool $extractSep = null
    ): ?string {
        $char = $this->peek();

        switch ($this->peek()) {
            case '"':
            case "'":
                $result =
                    $this->extract() . $this->extractUntil($char, null, true);

                if ($extractSep) {
                    if (isset($sep)) {
                        $this->extractFixedString($sep);
                    } else {
                        $this->extractWs(true);
                    }
                }

                return $result;

            default:
                return isset($sep)
                    ? $this->extractUntil($sep, null, $extractSep, $extractSep)
                    : $this->extractUntilWs(null, $extractSep, $extractSep);
        }
    }
}
