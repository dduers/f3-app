<?php
declare(strict_types=1);
namespace Dduers\F3App;

/**
 * APPLICATION BASE CONTROLLER
 * global available properties
 * pre/post routing
 * http error handling
 */
final class Application extends \Prefab
{
    // the configuration
    static private $_config;
    // f3-framework 
    static private array $_f3;
    // services
    static private $_service;


    /**
     * CONSTRUCTOR
     */
    function __construct()
    {
        // store framework instance locally
        self::$_f3['Base'] = \Base::instance();
        self::$_config = \Dduers\F3App\Config::instance(self::$_f3['Base']);
        // store web instance locally
        self::$_f3['Web'] = \Web::instance();
        // store web geo interface locally
        self::$_f3['Geo'] = \Web\Geo::instance();
        // audit instance
        self::$_f3['Audit'] = \Audit::instance();
        // init the logger object
        self::$_f3['Log'] = new \Log(date('Y-m-d').'.log');
        // create database connection, store to DB f3 hive variable for usage in orm models
        self::$_f3['Base']->set('DB',
            self::$_f3['DB'] = new \DB\SQL(
                (self::$_f3['Base']->get('database.type') ?: self::$_config::DB_DEFAULT_TYPE)
                    .':host='.(self::$_f3['Base']->get('database.host') ?: self::$_config::DB_DEFAULT_HOST)
                    .';port='.(self::$_f3['Base']->get('database.port') ?: self::$_config::DB_DEFAULT_PORT)
                    .';dbname='.self::$_f3['Base']->get('database.data'),
                self::$_f3['Base']->get('database.user'),
                self::$_f3['Base']->get('database.pass')
            )
        );
        // create mail server connection
        self::$_f3['SMTP'] = new \SMTP(
            self::$_f3['Base']->get('mail.host') ?: self::$_config::SMTP_DEFAULT_HOST,
            self::$_f3['Base']->get('mail.port') ?: self::$_config::SMTP_DEFAULT_PORT,
            self::$_f3['Base']->get('mail.scheme'),
            self::$_f3['Base']->get('mail.user'),
            self::$_f3['Base']->get('mail.pass')
        );
        
        // if the user agent is not a bot, otherwise default session
        if (self::$_f3['Audit']->isbot()) {
            self::$_f3['Session'] = NULL;
        } else {
            switch (strtolower(self::$_f3['Base']->get('session.engine') ?? '')) {
                default:
                    self::$_f3['Session'] = NULL;
                    self::$_f3['Base']->set('CSRF', self::createToken());
                    break;
                case 'sql':
                    self::$_f3['Session'] = new \DB\SQL\Session(self::$_f3['DB'], 'sessions', TRUE, NULL, 'CSRF');
                    break;
            }
        }
        // authentication instance, as least, because needs DB hive variable set
        //self::$auth = \classes\service\authentication::instance();
        // parse put variables and store to f3 hive PUT variable
        parse_str(file_get_contents("php://input"), $put_vars);
        self::$_f3['Base']->set('PUT', $put_vars);
    }

    /**
     * ROUTE PRE PROCESSOR
     * set / init very important variables
     * normalize content of variables PARAMS.page and PARAMS.lang
     * normalize uri through reroute to /language/page
     * set put parameter to PUT array
     * authenticate
     * @param \Base $f3_ f3 framework instance
     */
    static function beforeroute(\Base $f3_)
    {
        // grab application instance
        $_application = \Dduers\F3App\Application::instance();
        // disable output buffering for CLI interface
        if ($f3_->get('CLI')) {
            while (ob_get_level())
                ob_end_flush();
            ob_implicit_flush(true);
        }
        // when no language files are found
        if (!count(glob($f3_->get('LOCALES').'*.ini')))
            throw new \Exception('DICTIONARY check failed');
        /**
         * CSRF check
         * for every put or post request
         */
        if ($f3_->get('VERB') === 'POST' || $f3_->get('VERB') === 'PUT') {
            $_token = $f3_->get('POST._token') ?? $f3_->get('PUT._token'); 
            if (!$_token || !$f3_->get('SESSION.csrf') || $_token !== $f3_->get('SESSION.csrf'))
                throw new \Exception('CSRF check failed');
        }
        // detect page
        if (!$f3_->get('PARAMS.page')) {
            // try to determine page id
            $f3_->set('PARAMS.page',
                // try set default page from config, otherwise 'home'
                $f3_->get('frontend.website.defaultpage') ?: self::$_config::DEFAULT_PAGE
            );
            // update full uri parameter
            $f3_->set('PARAMS.0', '/'.$f3_->get('PARAMS.page'));
        }
        // split uri segments to array
        $_t = array_filter(explode('/', $f3_->get('PARAMS.0')));
        // if more than 2 segments in path
        if (count($_t) > 2) {
            // take last segment as page id
            $f3_->set('PARAMS.page', end($_t));
        }
        // detect language, if not set or a dictionary is not present for the current language
        if (!$f3_->get('PARAMS.lang') || !file_exists($f3_->get('LOCALES').$f3_->get('PARAMS.lang').'.ini')) {
            // set fallback default language
            $f3_->set('PARAMS.lang', $f3_->get('FALLBACK'));
            // try to overwrite default with brower setting auto detection
            foreach (explode(',', strtolower($f3_->get('LANGUAGE'))) as $lang) {
                // if a language file for the language exists
                if (file_exists($f3_->get('LOCALES').$lang.'.ini')) {
                    // set the first language found to parameter
                    $f3_->set('PARAMS.lang', $lang);
                    // leave foreach loop
                    break;
                }
            }
            // reroute to seo uri
            $f3_->reroute('/'.$f3_->get('PARAMS.lang').$f3_->get('PARAMS.0').($f3_->get('QUERY') ? '?'.$f3_->get('QUERY') : ''));
        }
        // set language to the one detected above
        $f3_->set('LANGUAGE', $f3_->get('PARAMS.lang'));
        // split uri segments to array
        $_t = array_filter(explode('/', $f3_->get('PARAMS.0')));
        // remove the language parameter
        array_shift($_t);
        // store page path without language
        $f3_->set('PARAMS.1', '/'.implode('/', $_t));
        // set default response mime
        $f3_->set('RESPONSE.mime', 'text/html');
        // authenticate
        //$_application::$auth::authenticate();
        // basket instance, cannot be done earlier, because session must not be running before the session handler is saved
        //self::$basket = \classes\service\basket::instance();
    }

    /**
     * ROUTE POST PROCESSOR
     * output RESPONSE.data with content header RESPONSE.mime
     * reset session based flash messages
     * @param \Base $f3_ f3 framework instance
     */
    static function afterroute(\Base $f3_)
    {
        // don't include after route methods for CLI execution
        if ($f3_->get('CLI'))
            return;
        // set content type header
        header('Content-Type: '.strtolower($f3_->get('RESPONSE.mime')));
        // if a response filename is set
        if ($f3_->get('RESPONSE.filename')) {
            // add content disposition header for downloading file
            header('Content-Disposition: attachment; filename="'.$f3_->get('RESPONSE.filename').'"');
        }
        // Render template depending on result mime type
        switch (strtolower($f3_->get('RESPONSE.mime'))) {
            case 'text/html':
                echo \Template::instance()->render('template.htm');
                break;
            case 'application/json':
                echo json_encode($f3_->get('RESPONSE.data'), ($f3_->get('DEBUG') ? JSON_PRETTY_PRINT : 0));
                break;
            default:
                echo $f3_->get('RESPONSE.data');
                break;
        }
        // reset session based flash messages
        $f3_->set('SESSION.message', []);
        // copy new csrf token to session
        $f3_->copy('CSRF','SESSION.csrf');
    }

    /**
     * custom f3 framework error handler
     * @param \Base $f3_ singleton of the f3 framework
     * @return bool true for error handled, false for fallback to default error handler
     */
    static function onerror(\Base $f3_)
    {
        // switch to default error handler, when it's a cli call or debugging is enabled
        if ($f3_->get('CLI') || $f3_->get('DEBUG') > 0)
            return false;
        // http error codes
        switch ($f3_->get('ERROR.code')) {
            case 400:
            case 403:
            case 404:
            case 405:
                $f3_->set('RESPONSE.data.content.carousel.items', [
                    [
                        'image' => 'images/carousel-error-1.jpg',
                        'title' => $f3_->get('DICT.error.'.$f3_->get('ERROR.code').'_title'),
                        'subtitle' => $f3_->get('DICT.error.'.$f3_->get('ERROR.code').'_subtitle'),
                        'button' => $f3_->get('DICT.navigation.1'),
                        'href' => '/'.$f3_->get('PARAMS.lang').'/home',
                    ],
                ]);
                if ($f3_->get('DEBUG') > 0)
                    $f3_->push('SESSION.message', [
                        'type' => 'danger',
                        'text' => $f3_->get('ERROR.text'),
                    ]);
                $f3_->set('PARAMS.page', $f3_->get('ERROR.code'));
                break;
            case 500:
                if ($f3_->get('DEBUG') > 0)
                    $f3_->push('SESSION.message', ['type' => 'danger', 'text' => $f3_->get('ERROR.text')]);
                else $f3_->push('SESSION.message', ['type' => 'danger', 'text' => 'Internal Server Error (500)']);
                break;
        }
        return true;
    }

    /**
     * create a random token
     */
    protected static function createToken()
    {
        return bin2hex(random_bytes(16));
    }
}
