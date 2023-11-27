<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use Base;
use Prefab;
use Audit;
use Web\Geo;
use Dduers\F3App\Iface\ServiceInterface;

/**
 * this class provides location services
 */
final class LocationService extends Prefab implements ServiceInterface
{
    private const DEFAULT_OPTIONS = [
        'defaultlocation' => 'CH',
        'storage' => 'location'
    ];

    private static array $_options;

    private static Geo $_service;
    private static Base $_f3;
    private static Audit $_f3_audit;

    /**
     * service constructor
     * @param array $options_
     */
    function __construct(array $options_)
    {
        self::$_options = array_merge(self::DEFAULT_OPTIONS, $options_);
        self::$_f3 = Base::instance();
        self::$_f3_audit = Audit::instance();
        self::$_service = Geo::instance();
    }

    /**
     * get country code
     * try geo location service, then, if user is loggen in, country from delivery address
     * @return string location country code like 'CH' or 'DE'
     */
    public static function getCountryCode(): string
    {
        // return default location for all kind of bots
        if (self::$_f3_audit->isbot())
            return self::$_options['defaultlocation'];

        // get location information, if not already cached
        if (!self::getLocation())
            self::setLocation();

        // default to CH, when not localizeable
        return self::getLocation();
    }

    /**
     * get location from session cache
     * @return string
     */
    private static function getLocation(): string
    {
        return self::$_f3->get(self::$_options['storage']) ?: '';
    }

    /**
     * set location to session cache
     * @return void
     */
    private static function setLocation(): void
    {
        self::$_f3->set(
            self::$_options['storage'],
            self::$_service->location()['country_code']
                ?? self::$_options['defaultlocation']
        );
    }

    /**
     * returns instance of location service
     * @return Geo|null
     */
    public static function getService(): Geo|NULL
    {
        return self::$_service;
    }

    /**
     * returns current configuation
     * @return array
     */
    public static function getOptions(): array
    {
        return self::$_options;
    }
}
