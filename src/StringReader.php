<?php

namespace SimpleGettext;

/**
 * Class StringReader
 * @package SimpleGettext
 */
class StringReader
{
    /**
     * @var int
     */
    protected $pos;

    /**
     * @var string
     */
    protected $str;

    /**
     * StringReader constructor.
     * @param string $str
     */
    public function __construct($str = '')
    {
        $this->str = $str;
        $this->pos = 0;
    }

    /**
     * @param $bytes
     * @return bool|string
     */
    public function read($bytes)
    {
        $data      = substr($this->str, $this->pos, $bytes);
        $this->pos += $bytes;
        if (strlen($this->str) < $this->pos) {
            $this->pos = strlen($this->str);
        }
        return $data;
    }

    /**
     * @param $pos
     * @return int
     */
    public function seekto($pos)
    {
        $this->pos = $pos;
        if (strlen($this->str) < $this->pos) {
            $this->pos = strlen($this->str);
        }
        return $this->pos;
    }

    /**
     * @return int
     */
    public function currentpos()
    {
        return $this->pos;
    }

    /**
     * @return int
     */
    public function length()
    {
        return strlen($this->str);
    }
}
