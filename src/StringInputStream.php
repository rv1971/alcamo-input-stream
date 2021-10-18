<?php

namespace alcamo\input_stream;

use alcamo\exception\{Eof, Underflow};

/**
 * @brief Seekable input stream made from a string
 *
 * @date Last reviewed 2021-06-15
 */
class StringInputStream implements SeekableInputStreamInterface
{
    /**
     * @brief Regexp identifying white space in extractWs()
     *
     * May be redefined in derived classes to treat comments as whitespace.
     */
    public const WS_REGEXP = '/^\s+/';

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

    /// @copydoc InputStreamInterface::isGood()
    public function isGood(): bool
    {
        return isset($this->text_[$this->offset_]);
    }

    /// @copydoc InputStreamInterface::peek()
    public function peek(): ?string
    {
        return $this->text_[$this->offset_] ?? null;
    }

    /// @copydoc InputStreamInterface::extract()
    public function extract(int $count = 1): ?string
    {
        if (!isset($this->text_[$this->offset_])) {
            return null;
        }

        if (!isset($this->text_[$this->offset_ + $count - 1])) {
            // throw already documented in InputStreamInterface::extract()
            throw (new Eof())->setMessageContext(
                [
                    'objectType' => 'stream',
                    'object' => $this,
                    'requestedUnits' => $count,
                    'availableUnits' => strlen($this->text_) - $this->offset_
                ]
            );
        }

        $result = substr($this->text_, $this->offset_, $count);

        $this->offset_ += $count;

        return $result;
    }

    /// @copydoc InputStreamInterface::putback()
    public function putback(): void
    {
        if ($this->offset_) {
            $this->offset_--;
        } else {
            // throw already documented in InputStreamInterface::extract()
            throw (new Underflow())->setMessageContext(
                [
                    'objectType' => 'stream',
                    'object' => $this
                ]
            );
        }
    }

    /// @copydoc InputStreamInterface::extractUntil()
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
            // If not found, return $maxCount or the entire remainder.
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
            // If found, return $maxCount or until $sep.
            if ($extractSep) {
                $sepPos += strlen($sep);
            }

            if (isset($maxCount) && $sepPos > $this->offset_ + $maxCount) {
                $sepPos = $this->offset_ + $maxCount;

                $result = substr($this->text_, $this->offset_, $maxCount);
            } else {
                $result = substr(
                    $this->text_,
                    $this->offset_,
                    $discardSep
                    ? $sepPos - $this->offset_ - strlen($sep)
                    : $sepPos - $this->offset_
                );
            }

            $this->offset_ = $sepPos;
        }

        return $result;
    }

    /// @copydoc SeekableInputStreamInterface::getOffset()
    public function getOffset(): int
    {
        return $this->offset_;
    }

    /// @copydoc SeekableInputStreamInterface::getSize()
    public function getSize(): int
    {
        return strlen($this->text_);
    }

    /// @copydoc SeekableInputStreamInterface::getContents()
    public function getContents(): string
    {
        return $this->text_;
    }

    /// @copydoc SeekableInputStreamInterface::getRemainder()
    public function getRemainder(): ?string
    {
        return isset($this->text_[$this->offset_])
            ? substr($this->text_, $this->offset_)
            : null;
    }

    /// @copydoc SeekableInputStreamInterface::extractRemainder()
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

    public function extractWs(): ?string
    {
        return $this->extractRegexp(static::WS_REGEXP);
    }
}
