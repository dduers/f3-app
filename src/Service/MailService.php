<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use Base;
use Prefab;
use SMTP;
use Template;

final class MailService extends Prefab
{
    static private $_service;
    static private array $_options = [];
    static private Base $_f3;

    function __construct(array $options_)
    {
        self::$_options = $options_;
        self::$_f3 = Base::instance();
        self::init();
    }

    /**
     * init service instance
     * @return void
     */
    static private function init(): void
    {
        if ((int)self::$_options['enable'] === 1) {
            if (!self::$_service)
                self::$_service = new SMTP(
                    self::$_options['host'],
                    self::$_options['port'],
                    self::$_options['scheme'],
                    self::$_options['user'],
                    self::$_options['pass']
                );
        } else self::$_service = NULL;
    }

    /**
     * get service instance
     * @return SMTP|null
     */
    static public function getService()
    {
        return self::$_service;
    }

    /**
     * get service options
     * @return array
     */
    static public function getOptions(): array
    {
        return self::$_options;
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
    static public function sendMail($to_, string $subject_, string $message_, string $from_addr_ = NULL, string $from_name_ = NULL, array $attach_ = []): bool
    {
        if (!((int)self::$_options['enable'] === 1))
            return false;

        self::$_service->set('Content-type', self::$_options['mime'] . '; charset=' . self::$_f3->get('ENCODING'));

        if (is_string($to_))
            $to_ = explode(',', $to_);

        $_toaddr = [];
        foreach ($to_ as $_x)
            $_toaddr[] = '<' . trim($_x) . '>';

        $_toaddr = implode(', ', $_toaddr);
        $from_addr_ = $from_addr_ ?: self::$_options['defaultsender.email'];
        $from_name_ = $from_name_ ?: (self::$_options['defaultsender.name'] ?: self::$_options['defaultsender.email']);

        self::$_service->set('To', $_toaddr);
        self::$_service->set('From', '"' . $from_name_ . '" ' . '<' . $from_addr_ . '>');
        self::$_service->set('Subject', $subject_);

        foreach ($attach_ as $_x)
            self::$_service->attach($_x);

        if (file_exists(self::$_f3->get('UI') . 'mail/' . $message_ . '.html'))
            return self::$_service->send(Template::instance()->render('mail/' . $message_ . '.html', self::$_options['mime']));
        else return self::$_service->send($message_);
    }
}
