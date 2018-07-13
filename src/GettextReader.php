<?php

namespace SimpleGettext;

/**
 * Class GettextReader
 * @package SimpleGettext
 */
class GettextReader
{
    /**
     * @var int
     * @example 0: low endian, 1: big endian
     */
    protected $byteOrder = 0;

    /**
     * @var null|FileReader
     */
    protected $fileReader = null;

    /**
     * @var bool
     */
    protected $shortCircuit = false;

    /**
     * @var bool
     */
    protected $enableCache = false;

    /**
     * @var mixed|null
     * Offset of original table
     */
    protected $originals = null;

    /**
     * @var mixed|null
     * Offset of translation table
     */
    protected $translations = null;

    /**
     * @var null
     * Cache header field for plural forms
     */
    protected $pluralheader = null;

    /**
     * @var int|mixed
     * Total string count
     */
    protected $total = 0;

    /**
     * @var null
     * Table for original strings (offsets)
     */
    protected $tableOriginals = null;

    /**
     * @var null
     * Table for translated strings (offsets)
     */
    protected $tableTranslations = null;

    /**
     * @var null
     * Original -> translation mapping
     */
    protected $cacheTranslations = null;

    /**
     * GettextReader constructor.
     * @param FileReader $fileReader
     * @param bool $enableCache
     */
    public function __construct(FileReader $fileReader, $enableCache = true)
    {
        // If there isn't a StreamReader, turn on short circuit mode.
        if (!$fileReader || isset($fileReader->error)) {
            $this->shortCircuit = true;
            return true;
        }

        // Caching can be turned off
        $this->enableCache = $enableCache;

        $magic1 = "\x95\x04\x12\xde";
        $magic2 = "\xde\x12\x04\x95";

        $this->fileReader = $fileReader;
        $magic            = $this->read(4);

        if ($magic == $magic1) {
            $this->byteOrder = 1;
        } elseif ($magic == $magic2) {
            $this->byteOrder = 0;
        } else {
            //$this->error = 1; // not MO file
            return false;
        }

        $this->readint();
        $this->total        = $this->readint();
        $this->originals    = $this->readint();
        $this->translations = $this->readint();
        return true;
    }

    /**
     * @return mixed
     */
    public function readint()
    {
        if ($this->byteOrder == 0) {
            // low endian
            $input = unpack('V', $this->fileReader->read(4));
            return array_shift($input);
        } else {
            // big endian
            $input = unpack('N', $this->fileReader->read(4));
            return array_shift($input);
        }
    }

    /**
     * @param $bytes
     * @return mixed
     */
    public function read($bytes)
    {
        return $this->fileReader->read($bytes);
    }

    /**
     * @param $count
     * @return array
     */
    public function readintarray($count)
    {
        if ($this->byteOrder == 0) {
            // low endian
            return unpack('V' . $count, $this->fileReader->read(4 * $count));
        } else {
            // big endian
            return unpack('N' . $count, $this->fileReader->read(4 * $count));
        }
    }

    /**
     *
     */
    public function loadTables()
    {
        if (is_array($this->cacheTranslations)
            && is_array($this->tableOriginals)
            && is_array($this->tableTranslations)
        ) {
            return;
        }

        /* get original and translations tables */
        if (!is_array($this->tableOriginals)) {
            $this->fileReader->seekto($this->originals);
            $this->tableOriginals = $this->readintarray($this->total * 2);
        }
        if (!is_array($this->tableTranslations)) {
            $this->fileReader->seekto($this->translations);
            $this->tableTranslations = $this->readintarray($this->total * 2);
        }

        if ($this->enableCache) {
            $this->cacheTranslations = [];
            /* read all strings in the cache */
            for ($i = 0; $i < $this->total; $i++) {
                $this->fileReader->seekto($this->tableOriginals[$i * 2 + 2]);
                $original = $this->fileReader->read($this->tableOriginals[$i * 2 + 1]);
                $this->fileReader->seekto($this->tableTranslations[$i * 2 + 2]);
                $translation                        = $this->fileReader->read($this->tableTranslations[$i * 2 + 1]);
                $this->cacheTranslations[$original] = $translation;
            }
        }
    }

    /**
     * @param $num
     * @return string
     */
    public function getOriginalString($num)
    {
        $length = $this->tableOriginals[$num * 2 + 1];
        $offset = $this->tableOriginals[$num * 2 + 2];
        if (!$length) {
            return '';
        }
        $this->fileReader->seekto($offset);
        $data = $this->fileReader->read($length);
        return (string)$data;
    }

    /**
     * @param $num
     * @return string
     */
    public function getTranslationString($num)
    {
        $length = $this->tableTranslations[$num * 2 + 1];
        $offset = $this->tableTranslations[$num * 2 + 2];
        if (!$length) {
            return '';
        }
        $this->fileReader->seekto($offset);
        $data = $this->fileReader->read($length);
        return (string)$data;
    }

    /**
     * @param $string
     * @param int $start
     * @param int $end
     * @return int
     */
    public function findString($string, $start = -1, $end = -1)
    {
        if (($start == -1) or ($end == -1)) {
            // find_string is called with only one parameter, set start end end
            $start = 0;
            $end   = $this->total;
        }
        if (abs($start - $end) <= 1) {
            // We're done, now we either found the string, or it doesn't exist
            $txt = $this->getOriginalString($start);
            if ($string == $txt) {
                return $start;
            } else {
                return -1;
            }
        } elseif ($start > $end) {
            // start > end -> turn around and start over
            return $this->findString($string, $end, $start);
        } else {
            // Divide table in two parts
            $half = (int)(($start + $end) / 2);
            $cmp  = strcmp($string, $this->getOriginalString($half));
            if ($cmp == 0) {
                // string is exactly in the middle => return it
                return $half;
            } elseif ($cmp < 0) {
                // The string is in the upper half
                return $this->findString($string, $start, $half);
            } else {
                // The string is in the lower half
                return $this->findString($string, $half, $end);
            }
        }
    }

    /**
     * @param $string
     * @return string
     */
    public function translate($string)
    {
        if ($this->shortCircuit) {
            return $string;
        }
        $this->loadTables();

        if ($this->enableCache) {
            // Caching enabled, get translated string from cache
            if (array_key_exists($string, $this->cacheTranslations)) {
                return $this->cacheTranslations[$string];
            } else {
                return $string;
            }
        } else {
            // Caching not enabled, try to find string
            $num = $this->findString($string);
            if ($num == -1) {
                return $string;
            } else {
                return $this->getTranslationString($num);
            }
        }
    }

    /**
     * @param $expr
     * @return string
     */
    public function sanitizePluralExpression($expr)
    {
        // Get rid of disallowed characters.
        $expr = preg_replace('@[^a-zA-Z0-9_:;\(\)\?\|\&=!<>+*/\%-]@', '', $expr);

        // Add parenthesis for tertiary '?' operator.
        $expr .= ';';
        $res  = '';
        $p    = 0;
        for ($i = 0; $i < strlen($expr); $i++) {
            $ch = $expr[$i];
            switch ($ch) {
                case '?':
                    $res .= ' ? (';
                    $p++;
                    break;
                case ':':
                    $res .= ') : (';
                    break;
                case ';':
                    $res .= str_repeat(')', $p) . ';';
                    $p   = 0;
                    break;
                default:
                    $res .= $ch;
            }
        }
        return $res;
    }

    /**
     * @param $header
     * @return string
     */
    public function extractPluralFormsHeaderFromPoHeader($header)
    {
        if (preg_match("/(^|\n)plural-forms: ([^\n]*)\n/i", $header, $regs)) {
            $expr = $regs[2];
        } else {
            $expr = "nplurals=2; plural=n == 1 ? 0 : 1;";
        }
        return $expr;
    }

    /**
     * @return null|string
     */
    public function getPluralForms()
    {
        // lets assume message number 0 is header
        // this is true, right?
        $this->loadTables();

        // cache header field for plural forms
        if (!is_string($this->pluralheader)) {
            if ($this->enableCache) {
                $header = $this->cacheTranslations[""];
            } else {
                $header = $this->getTranslationString(0);
            }
            $expr               = $this->extractPluralFormsHeaderFromPoHeader($header);
            $this->pluralheader = $this->sanitizePluralExpression($expr);
        }
        return $this->pluralheader;
    }

    /**
     * @param $n
     * @return int
     */
    public function selectString($n)
    {
        $string = $this->getPluralForms();
        $string = str_replace('nplurals', "\$total", $string);
        $string = str_replace("n", (int)$n, $string);
        $string = str_replace('plural', "\$plural", $string);

        $total  = 0;
        $plural = 0;

        eval("$string");
        if ($plural >= $total) {
            $plural = $total - 1;
        }
        return $plural;
    }

    /**
     * @param $single
     * @param $plural
     * @param $number
     * @return mixed
     */
    public function ngettext($single, $plural, $number)
    {
        if ($this->shortCircuit) {
            if ($number != 1) {
                return $plural;
            } else {
                return $single;
            }
        }

        // find out the appropriate form
        $select = $this->selectString($number);

        // this should contains all strings separated by NULLs
        $key = $single . chr(0) . $plural;


        if ($this->enableCache) {
            if (!array_key_exists($key, $this->cacheTranslations)) {
                return ($number != 1) ? $plural : $single;
            } else {
                $result = $this->cacheTranslations[$key];
                $list   = explode(chr(0), $result);
                return $list[$select];
            }
        } else {
            $num = $this->findString($key);
            if ($num == -1) {
                return ($number != 1) ? $plural : $single;
            } else {
                $result = $this->getTranslationString($num);
                $list   = explode(chr(0), $result);
                return $list[$select];
            }
        }
    }

    /**
     * @param $context
     * @param $msgid
     * @return string
     */
    public function pgettext($context, $msgid)
    {
        $key = $context . chr(4) . $msgid;
        $ret = $this->translate($key);
        if (strpos($ret, "\004") !== false) {
            return $msgid;
        } else {
            return $ret;
        }
    }

    /**
     * @param $context
     * @param $singular
     * @param $plural
     * @param $number
     * @return mixed
     */
    public function npgettext($context, $singular, $plural, $number)
    {
        $key = $context . chr(4) . $singular;
        $ret = $this->ngettext($key, $plural, $number);
        if (strpos($ret, "\004") !== false) {
            return $singular;
        } else {
            return $ret;
        }
    }
}
