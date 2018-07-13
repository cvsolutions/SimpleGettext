<?php

namespace SimpleGettext;

/**
 * Class CachedFileReader
 * @package SimpleGettext
 */
class CachedFileReader extends StringReader
{
    /**
     * CachedFileReader constructor.
     * @param $filename
     * @throws \Exception
     */
    public function __construct($filename)
    {
        parent::__construct('');
        if (file_exists($filename)) {
            $length = filesize($filename);
            $fd     = fopen($filename, 'rb');
            if (!$fd) {
                throw new \Exception('Cannot read file, probably permissions');
            }
            $this->str = fread($fd, $length);
            fclose($fd);
        } else {
            throw new \Exception('File doesn\'t exist');
        }
    }
}
