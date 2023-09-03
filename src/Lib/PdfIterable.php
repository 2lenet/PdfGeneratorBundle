<?php

namespace Lle\PdfGeneratorBundle\Lib;

use PDFMerger as Base;
use setasign\Fpdi\TcpdfFpdi;

class PdfIterable implements \Iterator, \Countable
{
    private int $position;

    public function __construct(private iterable $data)
    {
        $this->position = 0;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function current(): mixed
    {
        return $this->data[$this->position];
    }

    public function key(): mixed
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function valid(): bool
    {
        return isset($this->data[$this->position]);
    }

    public function count(): int
    {
        return count($this->data);
    }
}
