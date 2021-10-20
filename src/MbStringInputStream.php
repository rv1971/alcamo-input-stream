<?php

namespace alcamo\input_stream;

use alcamo\exception\Eof;

/**
 * @brief Seekable input stream made from a multibyte string
 *
 * Unlike StringInputStream, here all offsets are counted in characters rather
 * than bytes.
 *
 * @date Last reviewed 2021-06-15
 */
class MbStringInputStream extends StringInputStream
{
    protected $size_; ///< int

    public function __construct(string $text, ?int $offset = null)
    {
        parent::__construct($text, $offset);

        /** Cache result of mb_strlen() which has complexity O(n).
         *
         * @sa [Which complexity
         * mb_strlen?](http://stackoverflow.com/questions/40597394/which-complexity-mb-strlen)
         */
        $this->size_ = mb_strlen($this->text_);
    }

    /// @copydoc InputStreamInterface::isGood()
    public function isGood(): bool
    {
        return $this->offset_ < $this->size_;
    }

    /// @copydoc InputStreamInterface::peek()
    public function peek(): ?string
    {
        return $this->offset_ < $this->size_
            ? mb_substr($this->text_, $this->offset_, 1)
            : null;
    }

    /// @copydoc InputStreamInterface::extract()
    public function extract(int $count = 1): ?string
    {
        if ($this->offset_ >= $this->size_) {
            return null;
        }

        $result = mb_substr($this->text_, $this->offset_, $count);

        if ($this->offset_ + $count > $this->size_) {
            // throw already documented in InputStreamInterface::extract()
            throw (new Eof())->setMessageContext(
                [
                    'objectType' => 'stream',
                    'requestedUnits' => $count,
                    'availableUnits' => $this->size_ - $this->offset_
                ]
            );
        }

        $this->offset_ += $count;

        return $result;
    }

    /// @copydoc InputStreamInterface::extractUntil()
    public function extractUntil(
        string $sep,
        ?int $maxCount = null,
        ?bool $extractSep = null,
        ?bool $discardSep = null
    ): ?string {
        if ($this->offset_ >= $this->size_) {
            return null;
        }

        $sepPos = mb_strpos($this->text_, $sep, $this->offset_);

        if ($sepPos === false) {
            // If not found, return $maxCount or the entire remainder.
            if (
                isset($maxCount)
                && $this->offset_ + $maxCount <= mb_strlen($this->text_)
            ) {
                $result = mb_substr($this->text_, $this->offset_, $maxCount);
                $this->offset_ += $maxCount;
            } else {
                $result = mb_substr($this->text_, $this->offset_);
                $this->offset_ = $this->size_;
            }
        } else {
            // If found, return $maxCount or until $sep.
            if ($extractSep) {
                $sepPos += mb_strlen($sep);
            }

            if (isset($maxCount) && $sepPos > $this->offset_ + $maxCount) {
                $sepPos = $this->offset_ + $maxCount;

                $result = mb_substr($this->text_, $this->offset_, $maxCount);
            } else {
                $result = mb_substr(
                    $this->text_,
                    $this->offset_,
                    $discardSep
                    ? $sepPos - $this->offset_ - mb_strlen($sep)
                    : $sepPos - $this->offset_
                );
            }

            $this->offset_ = $sepPos;
        }

        return $result;
    }

    /// @copydoc SeekableInputStreamInterface::getSize()
    public function getSize(): int
    {
        return $this->size_;
    }

    /// @copydoc SeekableInputStreamInterface::getRemainder()
    public function getRemainder(): ?string
    {
        return ($this->offset_ < $this->size_)
            ? mb_substr($this->text_, $this->offset_)
            : null;
    }

    /// @copydoc SeekableInputStreamInterface::extractRemainder()
    public function extractRemainder(): ?string
    {
        if ($this->offset_ < $this->size_) {
            $result = mb_substr($this->text_, $this->offset_);
            $this->offset_ = $this->size_;
            return $result;
        } else {
            return null;
        }
    }

    /// @copydoc StringInputStream::extractRegexp()
    public function extractRegexp(string $regexp, ?int $matchNo = 0): ?string
    {
        if (
            preg_match(
                $regexp,
                mb_substr($this->text_, $this->offset_),
                $matches,
                PREG_OFFSET_CAPTURE
            )
        ) {
            $this->offset_ += mb_strlen($matches[0][0]) + $matches[0][1];

            return $matches[(int)$matchNo][0];
        } else {
            return null;
        }
    }
}
