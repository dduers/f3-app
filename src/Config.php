<?php
declare(strict_types=1);
namespace Dduers\F3App;

/**
 * APPLICATION BASE CONTROLLER
 * global available properties
 * pre/post routing
 * http error handling
 */
final class Config extends \Prefab
{
    // default page, fallback
    public const DEFAULT_PAGE = 'home';
    // default database connection parameters
    public const DB_DEFAULT_TYPE = 'mysql';
    public const DB_DEFAULT_HOST = 'localhost';
    public const DB_DEFAULT_PORT = 3306;
    // default smtp connection parameters
    public const SMTP_DEFAULT_HOST = 'localhost';
    public const SMTP_DEFAULT_PORT = 25;

    function __construct(\Base $f3_)
    {
        // load configuration files
        foreach (glob('../app/config/*.ini') as $_inifile)
            $f3_->config($_inifile);
    }
}
