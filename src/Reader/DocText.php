<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

/**
 * The text of a `/** *\/` docblock as written, its lines and its ``` fences, positions counted in bytes
 * from the docblock's start.
 */
final class DocText
{
    /** @var list<array{int, string}> the offset and the text of each line, without its line break */
    public array $lines = [];

    /** @var list<array{int, int}> the start and the end of each fence, an unclosed one running to the end */
    private array $fences = [];

    public function __construct(
        public string $text,
        private int $startLine,
    ) {
        $lines = preg_split('/\r\n|\r|\n/', $text, -1, PREG_SPLIT_OFFSET_CAPTURE);
        if ($lines === false) {
            $lines = [];
        }
        foreach ($lines as [$line, $offset]) {
            $this->lines[] = [$offset, $line];
        }
        preg_match_all('/```/', $text, $marks, PREG_OFFSET_CAPTURE);
        $starts = array_column($marks[0], 1);
        for ($index = 0; $index < count($starts); $index += 2) {
            $this->fences[] = [$starts[$index], $starts[$index + 1] ?? strlen($text)];
        }
    }

    /**
     * @param list<array{int, int}> $spans
     */
    public static function isInside(array $spans, int $position): bool
    {
        foreach ($spans as [$start, $end]) {
            if ($start <= $position && $position < $end) {
                return true;
            }
        }

        return false;
    }

    public function isInFence(int $position): bool
    {
        return self::isInside($this->fences, $position);
    }

    public function positionToLine(int $position): int
    {
        return $this->startLine + $this->positionToLineIndex($position);
    }

    public function positionToLineStart(int $position): int
    {
        return $this->lines[$this->positionToLineIndex($position)][0] ?? 0;
    }

    private function positionToLineIndex(int $position): int
    {
        $found = 0;
        foreach ($this->lines as $index => [$offset]) {
            if ($offset > $position) {
                break;
            }
            $found = $index;
        }

        return $found;
    }
}
