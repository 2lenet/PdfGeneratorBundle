<?php

namespace Lle\PdfGeneratorBundle\Lib;

use setasign\Fpdi\TcpdfFpdi;
use \PDFMerger as Base;

class PdfIterable implements \Iterator, \Countable
{

    private $data;
    private $position;

    public function __construct(iterable $data){
        $this->data = $data;
        $this->position = 0;
    }

    public function rewind() {
        $this->position = 0;
    }

    public function current() {
        return $this->data[$this->position];
    }

    public function key() {
        return $this->position;
    }

    public function next() {
        ++$this->position;
    }

    public function valid() {
        return isset($this->data[$this->position]);
    }

    public function count()
    {
        return count($this->data);
    }

}