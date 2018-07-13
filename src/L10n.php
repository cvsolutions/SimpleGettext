<?php

namespace SimpleGettext {

    /**
     * Class L10n
     * @package SimpleGettext
     */
    class L10n
    {
        /**
         * @var
         */
        public static $lang;

        /**
         * @var
         */
        public static $localeFile;

        /**
         * @var FileReader
         */
        public static $localeFileReader;

        /**
         * @var GettextReader
         */
        public static $localeReader;

        /**
         * @param $lang
         * @param $filename
         * @throws \Exception
         */
        public static function init($lang, $filename)
        {
            self::$lang             = $lang;
            self::$localeFile       = $filename;
            self::$localeFileReader = new FileReader($filename);
            self::$localeReader     = new GettextReader(self::$localeFileReader);
        }

        /**
         * @param $text
         * @return mixed
         */
        public static function gettext($text)
        {
            if (is_null(self::$localeReader)) {
                return $text;
            }
            return self::$localeReader->translate($text);
        }

        /**
         * @param $single
         * @param $pluar
         * @param $number
         * @return mixed
         */
        public static function ngettext($single, $pluar, $number)
        {
            if (is_null(self::$localeReader)) {
                return $single;
            }
            return self::$localeReader->ngettext($single, $pluar, $number);
        }
    }
}

namespace {

    /**
     * @param $text
     * @return mixed
     */
    function __($text)
    {
        return SimpleGettext\L10n::gettext($text);
    }

    /**
     * @param $text
     * @return mixed
     */
    function _gettext($text)
    {
        return SimpleGettext\L10n::gettext($text);
    }
}
