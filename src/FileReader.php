<?php

namespace SimpleGettext;

/**
 * Class FileReader
 * @package SimpleGettext
 */
class FileReader
{
    /**
     * @var int
     */
    protected $pos;

    /**
     * @var bool|resource
     */
    protected $fopen;

    /**
     * @var int
     */
    protected $length;

    /**
     * FileReader constructor.
     * @param $filename
     * @throws \Exception
     */
    public function __construct($filename)
    {
        if (file_exists($filename)) {
            $this->length = filesize($filename);
            $this->pos    = 0;
            $this->fopen  = fopen($filename, 'rb');
            if (!$this->fopen) {
                throw new \Exception('Cannot read file, probably permissions');
            }
        } else {
            throw new \Exception('File doesn\'t exist');
        }
        return true;
    }

    /**
     * @param $bytes
     * @return string
     */
    public function read($bytes)
    {
        if ($bytes) {
            fseek($this->fopen, $this->pos);
            // PHP 5.1.1 does not read more than 8192 bytes in one fread()
            // the discussions at PHP Bugs suggest it's the intended behaviour
            $data = '';
            while ($bytes > 0) {
                $chunk = fread($this->fopen, $bytes);
                $data  .= $chunk;
                $bytes -= strlen($chunk);
            }
            $this->pos = ftell($this->fopen);

            return $data;
        } else {
            return '';
        }
    }

    /**
     * @param $pos
     * @return bool|int
     */
    public function seekto($pos)
    {
        fseek($this->fopen, $pos);
        $this->pos = ftell($this->fopen);
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
        return $this->length;
    }

    /**
     *
     */
    public function close()
    {
        fclose($this->fopen);
    }
}
