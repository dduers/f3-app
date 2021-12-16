<?php

declare(strict_types=1);

namespace Dduers\F3App;

use Dduers\F3App\F3AppConfig;
use Base;
use Cache;
use DB\SQL;
use SMTP;
use Log;
use Prefab;
use Template;
use Exception;

/**
 * application base controller
 * - route controllers should extend this
 */
class F3App extends Prefab
{
    static private F3AppConfig $_config;
    static private Base $_f3;
    static private $_cache;
    static private $_db;
    static private $_smtp;
    static private $_log;
    static private array $_request_headers = [];

    /**
     * class constructor
     * - load application configuration
     * - initialization of class properties
     */
    function __construct()
    {
        self::$_config = F3AppConfig::instance();
        self::$_f3 = self::getFw();
        self::$_cache = self::getCache();
        self::$_db = self::getDb();
        self::$_smtp = self::getSmtp();
        self::$_log = new Log(date('Y-m-d') . '.log');
    }

    /**
     * routing pre processor
     * - csrf check for post and put requests
     * - set / init very important variables
     * - read request headers for later use
     * - parse input streams to arrays
     * @param Base $f3_ f3 framework instance
     * @return void
     */
    static public function beforeroute(Base $f3_): void
    {
        if (!count(glob($f3_->get('LOCALES') . '*.ini')))
            throw new Exception('DICTIONARY check failed');

        $f3_->set('PARAMS.vers', $f3_->get('PARAMS.vers') ?: 'v1');
        $f3_->set('PARAMS.ctrl', $f3_->get('PARAMS.ctrl') ?: 'home');
        $f3_->set('PARAMS.0', '/' . $f3_->get('PARAMS.ctrl'));

        if (!$f3_->get('PARAMS.lang') || !file_exists($f3_->get('LOCALES') . $f3_->get('PARAMS.lang') . '.ini')) {
            $f3_->set('PARAMS.lang', $f3_->get('FALLBACK'));
            foreach (explode(',', strtolower($f3_->get('LANGUAGE'))) as $lang) {
                if (file_exists($f3_->get('LOCALES') . $lang . '.ini')) {
                    $f3_->set('PARAMS.lang', $lang);
                    break;
                }
            }
        }
        $f3_->set('LANGUAGE', $f3_->get('PARAMS.lang'));

        foreach (getallheaders() as $header_ => $value_)
            self::$_request_headers[$header_] = $value_;

        self::parseInputData();

        if ((int)self::$_f3->get('CONF.security.csrf.enable') === 1) {
            if (in_array($f3_->get('VERB'), self::$_f3->get('CONF.security.csrf.methods'))) {
                $_token = $f3_->get('POST._token') ?? $f3_->get('PUT._token') ?? $f3_->get('GET._token');
                if (!$_token || !self::vars_cache('_token') || $_token !== self::vars_cache('_token')) {
                    $f3_->error(401);
                    return;
                }
            }
        }

        self::sanitizeInputData();

        return;
    }

    /**
     * routing post processor
     * - set response headers from RESPONSE.header
     * - output RESPONSE.data, recarding header RESPONSE.header.mime 
     * @param Base $f3_ f3 framework instance
     * @return void
     */
    static public function afterroute(Base $f3_): void
    {
        if ($_SERVER['HTTP_ORIGIN'] ?? '')
            if (
                is_array($f3_->get('CONF.header.alloworigin'))
                && in_array($_SERVER['HTTP_ORIGIN'], $f3_->get('CONF.header.alloworigin'))
            ) header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
            elseif (
                is_string($f3_->get('CONF.header.alloworigin'))
                && $_SERVER['HTTP_ORIGIN'] === $f3_->get('CONF.header.alloworigin')
            ) header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);

        if ($f3_->get('RESPONSE.header.methods'))
            header('Access-Control-Allow-Methods: ' . $f3_->get('RESPONSE.header.methods'));

        if ($_allow_header = strtolower(implode(',', $f3_->get('CONF.header.allowheader') ?? [])))
            header('Access-Control-Allow-Headers: ' . $_allow_header);

        header('Access-Control-Allow-Credentials: true');

        if ($_content_type = strtolower($f3_->get('CONF.header.contenttype')))
            header('Content-Type: ' . $_content_type);

        if ($f3_->get('RESPONSE.filename'))
            header('Content-Disposition: attachment; filename="' . $f3_->get('RESPONSE.filename') . '"');

        switch (strtolower($_content_type ?? '')) {
            default:
            case 'application/json':
                echo json_encode($f3_->get('RESPONSE.data') ?? [], ($f3_->get('DEBUG') ? JSON_PRETTY_PRINT : 0));
                break;
            case 'text/html':
                echo Template::instance()->render('template.html');
                break;
        }

        return;
    }

    /**
     * custom f3 framework error handler
     * @param Base $f3_ f3 framework instance
     * @return bool true = error handled, false = fallback to default f3 error handler
     */
    static public function onerror(Base $f3_): bool
    {
        $f3_->set('RESPONSE.data', [
            'result' => 'error',
            'code' => $f3_->get('ERROR.code'),
            'message' => (int)$f3_->get('ERROR.code') === 500
                ? ($f3_->get('DEBUG')
                    ? $f3_->get('ERROR.text')
                    : 'Internal Server Error (500)'
                )
                : $f3_->get('ERROR.text'),
        ]);

        return true;
    }

    /**
     * reroute to version/language/page
     * @param string $vers_ version e.g. 'v1'
     * @param string $ctrl_ name of controller
     * @return void
     */
    static protected function reroute(string $vers_, string $ctrl_, string $id_ = ''): void
    {
        self::$_f3->reroute($vers_ . '/' . $ctrl_ . ($id_ ? '/' . $id_ : '')(self::$_f3->get('QUERY') ? '?' . self::$_f3->get('QUERY') : ''));
        return;
    }

    /**
     * get or set framework variables
     * @param string $name_ name of f3 hive variable
     * @param mixed $value_ (optional) if set, the var is updated with the value
     * @return mixed current value or new value of f3 hive variable
     */
    static protected function vars(string $name_, $value_ = NULL)
    {
        if (isset($value_))
            return (self::$_f3->set($name_, $value_));
        else return (self::$_f3->get($name_));
    }

    static protected function vars_cache(string $name_, $value_ = NULL)
    {
        if (isset($value_))
            return (self::$_cache->set($name_, $value_));
        else return (self::$_cache->get($name_));
    }

    /**
     * write log entries
     * @param string $text_ the text to log
     * @param string $format_ (optional) e.g. 'r' for rfc 2822 log format
     * @return void
     */
    static protected function logs($text_, string $format_ = 'r'): void
    {
        if (is_string($text_))
            self::$_log->write($text_, $format_);
        else self::$_log->write(print_r($text_, true), $format_);
    }

    /**
     * send an email message through smtp
     * @param array|string $to_ array of receiver email addresses or string with comma separated email addresses
     * @param string $subject_ subject of message
     * @param string $message_ either a string with the message text of a template filename
     * @param string $from_addr_ (optional) email address to set for sender
     * @param string $from_name_ (optional) name to set for sender
     * @param array $attach_ (optional) array of filenames
     * @return bool true on success, false on error
     */
    static protected function mail($to_, string $subject_, string $message_, string $from_addr_ = NULL, string $from_name_ = NULL, array $attach_ = []): bool
    {
        if (!((int)self::$_f3->get('CONF.mail.enable') === 1))
            return false;

        self::$_smtp->set('Content-type', self::$_f3->get('CONF.mail.mime') . '; charset=' . self::$_f3->get('ENCODING'));

        if (is_string($to_))
            $to_ = explode(',', $to_);

        $_toaddr = [];
        foreach ($to_ as $_x)
            $_toaddr[] = '<' . trim($_x) . '>';

        $_toaddr = implode(', ', $_toaddr);
        $from_addr_ = $from_addr_ ?: self::vars('CONF.mail.defaultsender.email');
        $from_name_ = $from_name_ ?: (self::vars('CONF.mail.defaultsender.name') ?: self::vars('CONF.mail.defaultsender.email'));

        self::$_smtp->set('To', $_toaddr);
        self::$_smtp->set('From', '"' . $from_name_ . '" ' . '<' . $from_addr_ . '>');
        self::$_smtp->set('Subject', $subject_);

        foreach ($attach_ as $_x)
            self::$_smtp->attach($_x);

        if (file_exists(self::$_f3->get('UI') . 'mail/' . $message_ . '.html'))
            return self::$_smtp->send(Template::instance()->render('mail/' . $message_ . '.html', self::$_f3->get('CONF.mail.mime')));
        else return self::$_smtp->send($message_);
    }

    /**
     * creates a random string token and stores it to cache
     * for the next request
     * @return string
     */
    static protected function createToken(): string
    {
        if ((int)self::$_f3->get('CONF.security.csrf.enable') !== 1)
            return '';
        $_token = bin2hex(random_bytes(4));
        self::vars_cache('_token', $_token);
        return $_token;
    }

    /**
     * parse input data
     * @return void
     */
    static private function parseInputData(): void
    {
        switch (self::vars('VERB')) {
            case 'POST':
                switch (self::$_request_headers['Content-Type'] ?? '') {
                    default:
                    case 'application/json':
                        self::vars('POST', json_decode(file_get_contents("php://input"), true));
                        break;
                }
                break;
            case 'PUT':
                switch (self::$_request_headers['Content-Type'] ?? '') {
                    default:
                    case 'application/json':
                        self::vars('PUT', json_decode(file_get_contents("php://input"), true));
                        break;
                }
                /*
                $_body = file_get_contents("php://input");
                parse_str($_body, $_parsed);
                self::vars('PUT', $_parsed);
                */
        }
    }

    /**
     * sanitize input data
     * @return void
     */
    static private function sanitizeInputData(): void
    {
        if ((int)self::vars('CONF.security.xss.enable') !== 1)
            return;

        switch (self::vars('VERB')) {
            case 'POST':
                $_data = self::vars('POST');
                foreach ($_data as $key_ => $value_) {
                    if (in_array($key_, self::vars('CONF.security.xss.exclude')))
                        continue;
                    $_data[$key_] = self::$_f3->clean($value_);
                }
                self::vars('POST', array_filter($_data));
                break;
            case 'PUT':
                $_data = self::vars('PUT');
                foreach ($_data as $key_ => $value_) {
                    if (in_array($key_, self::vars('CONF.security.xss.exclude')))
                        continue;
                    $_data[$key_] = self::$_f3->clean($value_);
                }
                self::vars('PUT', array_filter($_data));
                break;
        }
        return;
    }

    /**
     * get framework instance
     * @return Base
     */
    static public function getFw(): Base
    {
        return Base::instance();
    }

    static public function getCache()
    {
        if (!self::$_cache) {
            self::$_cache = Cache::instance();
            self::$_cache->load(TRUE);
        }
        return self::$_cache;
    }

    /**
     * get database connection, must be enabled in config
     * @return SQL|null
     */
    static public function getDb()
    {
        if ((int)self::$_f3->get('CONF.database.enable') === 1) {
            if (!self::$_db)
                self::$_db = new SQL(
                    self::$_f3->get('CONF.database.type')
                        . ':host=' . self::$_f3->get('CONF.database.host')
                        . ';port=' . self::$_f3->get('CONF.database.port')
                        . ';dbname=' . self::$_f3->get('CONF.database.data'),
                    self::$_f3->get('CONF.database.user'),
                    self::$_f3->get('CONF.database.pass')
                );
            return self::$_db;
        }
        return NULL;
    }

    /**
     * get smtp connection, must be enabled in config
     * @return SMTP|null
     */
    static public function getSmtp()
    {
        if ((int)self::$_f3->get('CONF.mail.enable') === 1) {
            if (!self::$_smtp)
                self::$_smtp = new SMTP(
                    self::$_f3->get('CONF.mail.host'),
                    self::$_f3->get('CONF.mail.port'),
                    self::$_f3->get('CONF.mail.scheme'),
                    self::$_f3->get('CONF.mail.user'),
                    self::$_f3->get('CONF.mail.pass')
                );
            return self::$_smtp;
        }
        return NULL;
    }

    /**
     * run application
     * @return void
     */
    static public function run(): void
    {
        self::$_f3->run();
    }
}
