<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use Prefab;
use Web;
use Dduers\F3App\Iface\ServiceInterface;

final class GoogleRecaptchaService extends Prefab implements ServiceInterface
{
    /**
     * default options
     */
    private const DEFAULT_OPTIONS = [
        'websitekey' => '',
        'secretkey' => '',
        'scorethreshold' => 0.5,
        'apiurl' => 'https://www.google.com/recaptcha/api/siteverify',
    ];

    /**
     * service config options
     */
    private static array $_options;

    /**
     * recaptch api response
     */
    private static object $_response;

    private static Web $_f3_web;

    /**
     * service contructor
     */
    function __construct(array $options_)
    {
        self::$_f3_web = Web::instance();
        self::$_options = array_merge(self::DEFAULT_OPTIONS, $options_);
    }

    /**
     * make request to google api to verify recaptcha
     * @param string $token_ the token from the frontend
     * @return object|NULL object on success, NULL when failed
     */
    public static function verify(string $token_): bool
    {
        self::$_response = json_decode(self::$_f3_web->request(self::$_options['apiurl'], [
            'method'  => 'POST',
            'content' => http_build_query([
                'secret' => self::$_options['secretkey'],
                'response' => $token_,
                'remoteip' => $_SERVER['REMOTE_ADDR'],
            ]),
        ])['body']);
        return self::$_response->score >= (self::$_options['scorethreshold'] ?? 0);
    }

    /**
     * get frontend website key
     * @return string
     */
    public static function getWebsiteKey(): string
    {
        return self::$_options['websitekey'] ?? '';
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
     * get response
     * @return object
     */
    public static function getResponse(): object
    {
        return self::$_response;
    }
}
