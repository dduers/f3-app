<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use Prefab;
use SMTP;
use Dduers\F3App\Iface\ServiceInterface;

final class MailService extends Prefab implements ServiceInterface
{
    private const DEFAULT_OPTIONS = [
        'enable' => 0,
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
    static private $_service;
    static private array $_options = [];

    function __construct(array $options_)
    {
        self::$_options = array_merge(self::DEFAULT_OPTIONS, $options_);
        if ((int)self::$_options['enable'] === 1)
            self::$_service = new SMTP(
                self::$_options['host'],
                self::$_options['port'],
                self::$_options['scheme'],
                self::$_options['user'],
                self::$_options['pass']
            );
    }

    /**
     * get service options
     * @return array
     */
    static function getOptions(): array
    {
        return self::$_options;
    }

    /**
     * get service instance
     * @return SMTP|null
     */
    static function getService()
    {
        return self::$_service;
    }

    /**
     * send an email message through smtp
     * @param array $to_ array of receiver ['email' => 'name']
     * @param string $subject_
     * @param string $message_
     * @param array $from_ (optional) array of sender (one item) ['email' => 'name']
     * @param array $attach_ (optional) array of filenames
     * @return bool true on success, false on error
     */
    static function sendMail(array $to_, string $subject_, string $message_, array $from_ = [], array $attach_ = [], string $charset_ = ''): bool
    {
        if ((int)self::$_options['enable'] !== 1)
            return false;

        $_charset = $charset_ ?: self::$_options['charset'];

        $_toaddr = [];
        foreach ($to_ as $email_ => $name_)
            $_toaddr[] = ($name_ ? '"' . $name_ . '"' : '') . ' <' . $email_ . '>';
        $_toaddr = implode(', ', $_toaddr);

        $_fromaddr = [];
        foreach ($from_ as $email_ => $name_)
            $_fromaddr[] = ($name_ ? '"' . $name_ . '"' : '') . ' <' . $email_ . '>';

        if (!count($_fromaddr))
            $_fromaddr[] = ((self::$_options['defaultsender']['name'] ?? '') ? '"' . self::$_options['defaultsender']['name'] . '"' : '') . ' <' . self::$_options['defaultsender']['email'] . '>';
        $_fromaddr = implode(', ', $_fromaddr);

        foreach ($attach_ as $_attachment)
            self::$_service->attach($_attachment);

        self::$_service->set('Content-type', self::$_options['mime'] . '; charset=' . $_charset);
        self::$_service->set('To', $_toaddr);
        self::$_service->set('From', $_fromaddr);
        self::$_service->set('Subject', $subject_);
        return self::$_service->send($message_);
    }
}
