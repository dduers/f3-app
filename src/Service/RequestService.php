<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use Base;
use Prefab;
use Dduers\F3App\Iface\ServiceInterface;

final class RequestService extends Prefab implements ServiceInterface
{
    private const DEFAULT_OPTIONS = [
        'sanitizer' => [
            'enable' => 0,
            'method' => '',
            'request_methods' => [],
            'exclude' => []
        ]
    ];
    private static Base $_f3;
    private static array $_options = [];
    private static array $_request_headers = [];

    function __construct(array $options_)
    {
        self::$_f3 = Base::instance();
        self::$_options = array_merge(self::DEFAULT_OPTIONS, $options_);
        foreach (getallheaders() as $header_ => $value_)
            self::$_request_headers[$header_] = $value_;
        self::parseRequestData();
        self::sanitizeRequestData();
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
     * get request headers
     * @return array
     */
    public static function getRequestHeaders(): array
    {
        return self::$_request_headers;
    }

    /**
     * get bearer token from authorization header
     * @return string
     */
    public static function getBearerToken(): string
    {
        $_auth_header_prefix = 'Bearer ';
        $_auth_header = self::$_request_headers['Authorization'] ?? '';
        if (strpos($_auth_header, $_auth_header_prefix) === 0)
            return substr($_auth_header, strlen($_auth_header_prefix));
        return '';
    }

    /**
     * sanitize request body data from configured request methods
     * @return void
     */
    public static function sanitizeRequestData(): void
    {
        if ((int)self::$_options['sanitizer']['enable'] !== 1)
            return;
        $_request_method = self::$_f3->get('VERB');
        $_request_methods = is_string(self::$_options['sanitizer']['request_methods'])
            ? [self::$_options['sanitizer']['request_methods']]
            : (is_array(self::$_options['sanitizer']['request_methods'])
                ? self::$_options['sanitizer']['request_methods']
                : []);
        if (in_array($_request_method, $_request_methods)) {
            $_method = self::$_options['sanitizer']['method'];
            $_exclude = is_string(self::$_options['sanitizer']['exclude'])
                ? [self::$_options['sanitizer']['exclude']]
                : (is_array(self::$_options['sanitizer']['exclude'])
                    ? self::$_options['sanitizer']['exclude']
                    : []);
            $_request_data = self::$_f3->get($_request_method);
            foreach ($_request_data as $key_ => $value_) {
                if (in_array($key_, $_exclude))
                    continue;
                $_request_data[$key_] =
                    $_method === 'clean'
                    ? self::$_f3->clean($value_)
                    : ($_method === 'encode'
                        ? self::$_f3->encode($value_)
                        : $_request_data['key']);
            }
            self::$_f3->set($_request_method, $_request_data);
        }
        return;
    }

    /**
     * parse input data from various content types and formats to assoc arrays
     * @return void
     */
    public static function parseRequestData(): void
    {
        $_request_method = self::$_f3->get('VERB');
        switch ($_request_method) {
            default:
                break;
            case 'POST':
                switch (explode(';', self::$_request_headers['Content-Type'])[0] ?? '') {
                    default:
                    case 'application/json':
                        self::$_f3->set($_request_method, json_decode(file_get_contents("php://input"), true));
                        break;
                    case 'application/x-www-form-urlencoded':
                        // normal operation, nothing todo
                        break;
                    case 'multipart/form-data':
                        // TODO::
                        break;
                    case 'text/plain':
                        // TODO::
                        break;
                }
                break;
            case 'PUT':
                switch (explode(';', self::$_request_headers['Content-Type'])[0] ?? '') {
                    default:
                    case 'application/json':
                        self::$_f3->set($_request_method, json_decode(file_get_contents("php://input"), true));
                        break;
                    case 'application/x-www-form-urlencoded':
                        parse_str(file_get_contents("php://input"), $_vars);
                        self::$_f3->set($_request_method, $_vars);
                        break;
                    case 'multipart/form-data':
                        // TODO::
                        break;
                    case 'text/plain':
                        // TODO::
                        break;
                }
            case 'DELETE':
                switch (explode(';', self::$_request_headers['Content-Type'])[0] ?? '') {
                    default:
                    case 'application/json':
                        self::$_f3->set($_request_method, json_decode(file_get_contents("php://input"), true));
                        break;
                    case 'application/x-www-form-urlencoded':
                        parse_str(file_get_contents("php://input"), $_vars);
                        self::$_f3->set($_request_method, $_vars);
                        break;
                    case 'multipart/form-data':
                        // nothing todo, delete requests have no multiparts
                        break;
                    case 'text/plain':
                        // TODO::
                        break;
                }
        }
    }
}
