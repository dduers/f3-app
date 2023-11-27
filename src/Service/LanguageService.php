<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use Base;
use Prefab;
use Dduers\F3App\Iface\ServiceInterface;
use Dduers\F3App\Utility\FilesystemUtility;

/**
 * language service for f3 framework
 * @author Wildtier Schweiz
 */
final class LanguageService extends Prefab implements ServiceInterface
{
    private const FILE_LINE_BREAK = "\r\n";
    private const DEFAULT_OPTIONS = [
        'dictionaryfilefilter' => '/(?i:^.*\.(ini)$)/m',
        'sourcefilefilter' => '/(?i:^.*\.(php|htm|html)$)/m',
        'dictionarysoftdelete' => 1,
        'language_routing_param_name' => 'PARAMS.lang',
        'page_routing_param_name' => 'PARAMS.page',
        'page_default' => 'home',
    ];

    private static Base $_f3;
    private static FilesystemUtility $_filesystem;
    private static array $_options = [];
    private static array $_dictionary_parsed = [];

    /**
     * constructor
     * @param array $options_
     */
    function __construct(array $options_)
    {
        self::$_f3 = Base::instance();
        self::$_filesystem = FilesystemUtility::instance();
        self::$_options = array_merge(
            self::DEFAULT_OPTIONS,
            [
                'dictionarypath' => self::$_f3->get('LOCALES'),
                'dictionaryprefix' => self::$_f3->get('PREFIX'),
                'sourcepaths' => [
                    self::$_f3->get('UI'),
                    self::$_f3->get('application.sourcedir'),
                ],
            ],
            $options_
        );
        // adjust some options
        self::$_options['dictionaryprefix'] = str_replace('.', '', self::$_options['dictionaryprefix']);
        // populating service property initially
        self::loadDictionaryData(self::getCurrentLanguage(true));
    }

    /**
     * get service options
     * @return array
     */
    public static function getOptions(): array
    {
        return self::$_options;
    }

    /**
     * get service instance
     * @return null
     */
    public static function getService(): NULL
    {
        return NULL;
    }

    /**
     * get the current framework language code
     * @param $fallback_on_unavailable_
     * @return string 
     */
    public static function getCurrentLanguage(bool $fallback_on_unavailable_ = false): string
    {
        $_language = (self::getClientLanguages()[0] ?? '');
        if ($fallback_on_unavailable_ === true && !in_array($_language, self::getAvailableLanguages()))
            $_language = self::getDefaultLanguage();
        return $_language;
    }

    /**
     * get available languages 
     * based on existing language dictionary files
     * @return array
     */
    public static function getAvailableLanguages(): array
    {
        $_result = [];
        $_filenames = self::$_filesystem::recursiveDirectorySearch(self::$_options['dictionarypath'], self::$_options['dictionaryfilefilter']);
        foreach ($_filenames as $file_)
            $_result[] = explode('.', array_pop(explode(DIRECTORY_SEPARATOR, $file_)))[0];
        return $_result;
    }

    /**
     * get languages requested by the client
     * @return array
     */
    public static function getClientLanguages(): array
    {
        $_result = [];
        foreach (explode(',', self::frameworkLanguage()) as $lang_)
            $_result[] = $lang_;
        return $_result;
    }

    /**
     * check if a language is available 
     * based on existing language dictionary files
     * @param string $language_
     * @return bool
     */
    public static function isAvailableLanguage(string $language_): bool
    {
        return in_array($language_, self::getAvailableLanguages());
    }

    /**
     * redirect to language with current path and querystring
     * @param string $language_
     * @param string $controller_
     * @return null|false
     */
    public static function redirectLanguage(string $language_, ?string $controller_ = NULL): NULL|false
    {
        if (!self::isAvailableLanguage($language_))
            return false;
        $_query = self::$_f3->get('QUERY');
        $_controller = $controller_ ?? self::pageRoutingParam();
        $_language = self::isAvailableLanguage($language_) ? $language_ : self::getDefaultLanguage();
        return self::$_f3->reroute('/' . $_language . '/' . $_controller . ($_query ? '?' . $_query : ''));
    }

    /**
     * reroute to the complete /language/controller url or simply
     * reduce client languages to available project language, including default fallback
     * @return void
     */
    public static function frameworkLanguagePreparation(): void
    {
        // default controller
        self::pageRoutingParam(self::pageRoutingParam() ?: self::$_options['page_default']);
        // if routing language parameter is not set or the routing language is not available in the project
        if (!self::languageRoutingParam() || !self::isAvailableLanguage(self::languageRoutingParam())) {
            // set default language to the routing parameter
            self::languageRoutingParam(self::getDefaultLanguage());
            // look for the closest client language that is available in the project
            foreach (self::getClientLanguages() as $lang_) {
                if (!self::isAvailableLanguage($lang_))
                    continue;
                self::languageRoutingParam($lang_);
                break;
            }
            // redirect to /language/controller route
            self::redirectLanguage(self::languageRoutingParam(), self::pageRoutingParam());
            return;
        }
        // set the framework language
        self::frameworkLanguage(self::languageRoutingParam());
    }

    /**
     * get or set the language routing param
     * @param string $value_
     * @return string
     */
    private static function languageRoutingParam(?string $value_ = NULL): string
    {
        if ($value_ !== NULL)
            self::$_f3->set(self::$_options['language_routing_param_name'], $value_);
        return self::$_f3->get(self::$_options['language_routing_param_name']) ?? '';
    }

    /**
     * get or set the page routing param
     * @param string $value_
     * @return string
     */
    private static function pageRoutingParam(?string $value_ = NULL): string
    {
        if ($value_ !== NULL)
            self::$_f3->set(self::$_options['page_routing_param_name'], $value_);
        return self::$_f3->get(self::$_options['page_routing_param_name']) ?? '';
    }

    /**
     * get the framework default language
     * @return string
     */
    public static function getDefaultLanguage(): string
    {
        return explode(',', self::$_f3->get('FALLBACK'))[0] ?? '';
    }

    /**
     * switch language without redirect
     * used for inline applications
     * @param string $language_
     * @return void
     */
    public static function switchLanguage(string $language_): void
    {
        $_language = $language_;
        if (!self::isAvailableLanguage($_language))
            $_language = self::getDefaultLanguage();
        self::$_f3->set(self::$_options['language_routing_param_name'], $_language);
        self::frameworkLanguage($_language);
    }

    /**
     * get or set f3 framework language, originally loaded from config
     * @param string $language_
     * @return string
     */
    public static function frameworkLanguage(?string $language_ = NULL): string
    {
        if ($language_ !== NULL)
            self::$_f3->set('LANGUAGE', $language_);
        return self::$_f3->get('LANGUAGE');
    }

    /**
     * get language base urls
     * @param string $language_
     * @return string
     */
    public static function getLanguageUrl(string $language_ = ''): string
    {
        $_language = $language_ ?: self::getCurrentLanguage(true);
        if (!self::isAvailableLanguage($_language))
            return '';
        $_query = self::$_f3->get('QUERY');
        return '/' . $_language . '/' . self::pageRoutingParam() . ($_query ? '?' . $_query : '');
    }

    /**
     * load dictionary data of a language
     * @param string $language_
     * @return void
     */
    public static function loadDictionaryData(string $language_ = ''): void
    {
        $_language = $language_ ?: self::getCurrentLanguage(false);
        self::$_dictionary_parsed = self::parseDictionary(self::getDictionaryData($_language));
        return;
    }

    /**
     * get the parsed dictionary as one dimensional key value pairs
     * @return array
     */
    public static function getDictionaryParsed(): array
    {
        return self::$_dictionary_parsed;
    }

    /**
     * delete the entire dictionary
     * @return bool
     */
    public static function createDictionary(string $language_): bool
    {
        if (self::isAvailableLanguage($language_))
            return false;
        self::writeDictionaryFile($language_);
        return true;
    }

    /**
     * delete the entire dictionary
     * @param string $language_
     * @return bool
     */
    public static function removeDictionary(string $language_ = ''): bool
    {
        $_result = false;
        $_language = $language_ ?: self::getCurrentLanguage(false);
        if ((int)self::$_options['dictionarysoftdelete'] === 1)
            $_result = rename(self::getDictionaryFilename($_language), self::getDictionaryFilename($_language) . '.' . date('Y-m-d_H-i-s'));
        else $_result = unlink(self::getDictionaryFilename($_language));
        return $_result;
    }

    /**
     * get entry from current dictionary
     * @param $key_
     * @return string|null
     */
    public static function getEntry(string $key_): string|NULL
    {
        $_key = $key_;
        if (self::$_options['dictionaryprefix'] && !str_starts_with($_key, self::$_options['dictionaryprefix']))
            $_key = implode('.', [self::$_options['dictionaryprefix'], $_key]);
        return self::$_dictionary_parsed[$_key] ?? NULL;
    }

    /**
     * create or edit entry of current dictionary
     * @param string $key_
     * @param string $value_
     * @param bool $write_to_file_
     * @return void
     */
    public static function setEntry(string $key_, string $value_ = '', bool $write_to_file_ = true): void
    {
        $_key = $key_;
        if (self::$_options['dictionaryprefix'] && !str_starts_with($_key, self::$_options['dictionaryprefix']))
            $_key = implode('.', [self::$_options['dictionaryprefix'], $_key]);
        self::$_dictionary_parsed[$_key] = $value_;
        self::$_f3->mset(self::$_dictionary_parsed);
        if ($write_to_file_ === true)
            self::writeDictionaryFile();
        return;
    }

    /**
     * remove entry from current dictionary
     * @param string $key_
     * @param bool $write_to_file_
     * @return void
     */
    public static function removeEntry(string $key_, bool $write_to_file_ = true): void
    {
        $_key = $key_;
        if (self::$_options['dictionaryprefix'] && !str_starts_with($_key, self::$_options['dictionaryprefix']))
            $_key = implode('.', [self::$_options['dictionaryprefix'], $_key]);
        unset(self::$_dictionary_parsed[$_key]);
        self::$_f3->mset(self::$_dictionary_parsed);
        if ($write_to_file_ === true)
            self::writeDictionaryFile();
        return;
    }

    /**
     * parse a dictionary key to parts
     * @param string $key_
     */
    public static function parseKey(string $key_): array
    {
        $_t = explode('.', (string)$key_, 3);
        $_parts_count = count($_t);
        $_has_prefix = self::$_options['dictionaryprefix'] === $_t[0];
        $_has_section = ($_has_prefix && $_parts_count > 2) || (!$_has_prefix && $_parts_count === 2);
        $_result = [
            'prefix' => $_has_prefix ? $_t[0] : '',
            'section' => $_t[$_has_prefix ? 1 : 0],
            'key' => $_t[$_has_prefix && $_has_section ? 2 : ($_has_section && !$_has_prefix ? 1 : 0)],
        ];
        return $_result;
    }

    /**
     * check if a dictionary var is used in backend of frontend code
     * @param string $key_
     * @param string $filename_filter_
     * @param array &$filenames_ (optional) retrieve filenames containing the keys
     * @return int
     */
    public static function checkDictionaryUsage(string $key_ = '', array &$filenames_ = NULL): int
    {
        $_i = 0;
        $_files_names = [];
        foreach (self::$_options['sourcepaths'] ?? [] as $dir_)
            $_files_names = array_merge($_files_names, self::$_filesystem::recursiveDirectorySearch($dir_, self::$_options['sourcefilefilter']));
        $_t = '(' . ($key_ !== '' ? $key_ : implode('|', array_keys(self::$_dictionary_parsed))) . ')';
        foreach ($_files_names as $file_) {
            $_contents = file_get_contents($file_);
            if (preg_match($_t, $_contents) === 1) {
                if ($filenames_ !== NULL)
                    $filenames_[] = $file_;
                $_i++;
            }
        }
        return $_i;
    }

    /**
     * get dictionary data for a language
     * also contains fallback values, not present in the language requested (f3 standard)
     * @param string $language_
     * @return array
     */
    private static function getDictionaryData(string $language_ = ''): array
    {
        $_language = $language_ ?: self::getCurrentLanguage(false);
        $_t = self::frameworkLanguage();
        self::frameworkLanguage($_language);
        $_result = self::$_f3->get(self::$_options['dictionaryprefix']);
        self::frameworkLanguage($_t);
        return $_result;
    }

    /**
     * parse dictionary to single level
     * @param array $dictionary_node_
     * @return array
     */
    private static function parseDictionary(array $dictionary_node_ = []): array
    {
        static $_result = [];
        static $_stack_keys = [];
        // add the prefix to the keys, one time
        if (!in_array(self::$_options['dictionaryprefix'], $_stack_keys))
            $_stack_keys[] = self::$_options['dictionaryprefix'];
        // add dictionary node to the result
        foreach ($dictionary_node_ as $k_ => $v_) {
            // add new key to stack
            $_stack_keys[] = $k_;
            // if child nodes present, reparse
            if (is_array($v_))
                self::parseDictionary($v_);
            // when no further childs, store value to result with generated key
            else $_result[implode('.', $_stack_keys)] = $v_;
            // cleanup key
            while (($_t = array_pop($_stack_keys)) && $_t !== $k_);
        }
        return $_result;
    }

    /**
     * get filename of a language dictionary
     * @param string $language_
     * @return string
     */
    private static function getDictionaryFilename(string $language_): string
    {
        $_language = $language_ ?: self::getCurrentLanguage(false);
        return self::$_options['dictionarypath'] . $_language . '.ini';
    }

    /**
     * write data array to a language dictionary file
     * @author https://stackoverflow.com/questions/5695145/how-to-read-and-write-to-an-ini-file-with-php
     * @param ?string $file_name_ path and file name to write to
     * @param ?array $dictionary_parsed_ flat dimensional key value pairs of the dictionary
     * @param bool $quote_strings_
     * @return int|false
     */
    private static function writeDictionaryFile(?string $language_ = NULL, ?array $dictionary_parsed_ = NULL, bool $quote_strings_ = false): int|false
    {
        $_result = [];
        $_language = $language_ ?? self::getCurrentLanguage(false);
        $_dictionary_parsed = $dictionary_parsed_ ?? self::$_dictionary_parsed;
        ksort($_dictionary_parsed, SORT_STRING);
        $_filename = self::getDictionaryFilename($_language);
        $_section = '';
        foreach ($_dictionary_parsed as $k_ => $v_) {
            $_t = self::parseKey((string)$k_);
            // if first section or next section
            if ($_section === '' || $_t['section'] !== $_section) {
                // if previous section exists
                if ($_section !== '')
                    $_result[] = '';
                $_section = $_t['section'];
                $_result[] = '[' . $_section . ']';
            }
            $_key = $_t['key'];
            $_result[] = $_key . ' = ' . ($quote_strings_ === true ? (is_numeric($v_) ? $v_ : '"' . $v_ . '"') : $v_);
        }
        return file_put_contents($_filename, implode(self::FILE_LINE_BREAK, $_result));
    }
}
