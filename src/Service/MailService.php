<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use Prefab;
use SMTP;
use Dduers\F3App\Iface\ServiceInterface;
use Dduers\F3App\Utility\PolyfillUtility;

/**
 * mail service
 */
final class MailService extends Prefab implements ServiceInterface
{
    private const DEFAULT_OPTIONS = [
        'host' => 'localhost',
        'port' => 25,
        'user' => '',
        'pass' => '',
        'scheme' => '',
        'defaultsender' => [
            'email' => 'noreply@localhost',
            'name' => ''
        ],
        'mime' => 'text/html',
        'charset' => 'UTF-8'
    ];
    private static SMTP $_service;
    private static PolyfillUtility $_utility;
    private static array $_options = [];

    /**
     * constructor
     * @param array $options_
     */
    function __construct(array $options_)
    {
        self::$_options = array_merge(self::DEFAULT_OPTIONS, $options_);
        self::$_service = new SMTP(
            self::$_options['host'],
            self::$_options['port'],
            self::$_options['scheme'],
            self::$_options['user'],
            self::$_options['pass']
        );
        self::$_utility = PolyfillUtility::instance();
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
     * @return SMTP|null
     */
    public static function getService(): SMTP|NULL
    {
        return self::$_service;
    }

    /**
     * send an email message through smtp
     * @param array $to_ array of receiver ['email' => 'name']
     * @param string $subject_
     * @param string $message_
     * @param array $from_ (optional) array of sender (one item) ['email' => 'name']
     * @param array $reply_to_ (optional) array of reply to ['email' => 'name']
     * @param array $attach_ (optional) array of filenames
     * @return bool true on success, false on error
     */
    public static function sendMail(array $to_, string $subject_, string $message_, array $from_ = [], array $reply_to_ = [], array $attach_ = [], string $charset_ = ''): bool
    {
        $_charset = $charset_ ?: self::$_options['charset'];
        $_to = self::parseIndividuals($to_);
        $_from = self::parseIndividuals(
            count($from_) ? $from_ : [
                self::$_options['defaultsender']['email'] ?? '' =>
                self::$_options['defaultsender']['name'] ?? NULL
            ]
        );
        $_reply_to = self::parseIndividuals($reply_to_);
        foreach ($attach_ as $_attachment)
            self::$_service->attach($_attachment);
        self::setHeader('Content-type', self::$_options['mime'] . '; charset=' . $_charset);
        self::setHeader('To', $_to);
        self::setHeader('From', $_from);
        if ($_reply_to !== '')
            self::setHeader('Reply-To', $_reply_to);
        self::setHeader('Subject', $subject_);
        return self::$_service->send($message_);
    }

    /**
     * set a smtp header
     * @param string $header_
     * @param string $content_
     * @return void
     */
    public static function setHeader(string $header_, string $content_): void
    {
        self::$_service->set($header_, $content_);
    }

    /**
     * validate and transform an individuals array
     * @param array $individuals_
     * @return string email header conform cleanedup and parsed individuals
     */
    private static function parseIndividuals(array $individuals_): string
    {
        $_result = [];
        foreach ($individuals_ as $email_ => $name_) {
            $_email = is_numeric($email_) ? $name_ : $email_;
            $_name = is_numeric($email_) ? '' : $name_;
            if (!($_i = self::createIndividual($_email, $_name)))
                continue;
            $_result[] = $_i;
        }
        return implode(', ', $_result);
    }

    /**
     * create an email individual for content headers
     * email is validated prior to creation
     * @param string $email_ email address of person
     * @param string $name_ full name of person
     */
    private static function createIndividual(string $email_, ?string $name_ = NULL): string
    {
        if (!self::validateEmailAddress($email_))
            return '';
        $_name = (($name_ ?? '') ? '"' . $name_ . '" ' : '');
        $_email = '<' . $email_ . '>';
        return ($_name ?: '') . $_email;
    }

    /**
     * validate an email address
     * @param string $email_
     * @return bool
     */
    private static function validateEmailAddress(string $email_): bool
    {
        return filter_var($email_, FILTER_VALIDATE_EMAIL) ? true : false;
    }
}
