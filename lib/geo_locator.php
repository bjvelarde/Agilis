<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

use \Exception as Exception;
/**
 * A wrapper class for GeoIP pecl functions
 */
class GeoLocator {
    /**
     * @var string The URL (domain or IP)
     */
    private $host;
    /**
     * Constructor.
     *
     * @param string $host The Domain or IP Address
     */
    public function __construct($host='') {
        if (function_exists('geoip_country_code_by_name')) {
            $this->host = Common::ifEmpty($host, self::clientHost());
        } else {
            throw new Exception('GeoIP pecl extension is not installed.');
        }
    }
    /*
     * allow geoip functions to be called as methods
     */
    public function __call($method, $args) {
        $method = 'geoip_' . $method;
        $needs_host = array(
            'geoip_country_code_by_name',
            'geoip_country_code3_by_name',
            'geoip_country_name_by_name',
            'geoip_id_by_name',
            'geoip_isp_by_name',
            'geoip_org_by_name',
            'geoip_record_by_name',
            'geoip_region_by_name'
        );
        if (function_exists($method)) {
            if (in_array($method, $needs_host)) {
                array_unshift($args, $this->host);
            }
            return call_user_func_array($method, $args);
        }
        return NULL;
    }
    /**
     * Get the two letter country code
     *
     * @return string
     */
    public function countryCode() { return geoip_country_code_by_name($this->host); }
    /**
     * Get the three letter country code
     *
     * @return string
     */
    public function countryCode3() { return geoip_country_code3_by_name($this->host); }
    /**
     * Get the full country name
     *
     * @return string
     */
    public function countryName() { return geoip_country_name_by_name($this->host); }
    /**
     * Get GeoIP Database information
     *
     * @param int The database type defined in the PHP GeoIP PECL extension
     * @link http://www.php.net/manual/en/ref.geoip.php
     * @return string
     */
    public static function dbInfo($database) { return geoip_database_info($database); }
    /**
     * Determine if GeoIP Database is available
     *
     * @param int The database type defined in the PHP GeoIP PECL extension
     * @link http://www.php.net/manual/en/ref.geoip.php
     * @return bool
     */
    public static function isDbAvailable($database) { return geoip_db_avail($database); }
    /**
     * Returns the filename of the corresponding GeoIP Database
     *
     * @param int The database type defined in the PHP GeoIP PECL extension
     * @link http://www.php.net/manual/en/ref.geoip.php
     * @return string
     */
    public static function dbFilename($database) { return geoip_db_filename($database); }
    /**
     * Returns detailed informations about all GeoIP database types
     *
     * @return array
     */
    public static function dbGetAllInfo() { return geoip_db_get_all_info(); }
    /**
     * Get the Internet connection speed
     *
     * @link http://www.php.net/manual/en/function.geoip-id-by-name.php
     * @return int
     */
    public function connectionSpeed() { return geoip_id_by_name($this->host); }
    /**
     * Get the Internet Service Provider (ISP) name
     *
     * @return string
     */
    public function isp() { return geoip_isp_by_name($this->host); }
    /**
     * Get the organization name
     *
     * @return string
     */
    public static function orgName() { return geoip_org_by_name($this->host); }
    /**
     * Returns the detailed City information found in the GeoIP Database
     *
     * @return array
     */
    public function recordDetails() { return geoip_record_by_name($this->host); }
    /**
     * Get the country code and region
     *
     * @return array
     */
    public function countryCodeAndRegion() { return geoip_region_by_name($this->host); }
    /**
     * Get the client's host IP
     *
     * @return string
     */
    public static function clientHost() {
        $host = getenv('HTTP_CLIENT_IP');
        $host = $host ? $host : getenv('HTTP_X_FORWARDED_FOR');
        $host = $host ? $host : getenv('HTTP_X_FORWARDED');
        $host = $host ? $host : getenv('HTTP_FORWARDED_FOR');
        $host = $host ? $host : getenv('HTTP_FORWARDED');
        $host = $host ? $host : $_SERVER['REMOTE_ADDR'];
        $commapos = strpos($host, ',');
        if ($commapos !== FALSE) {
            $host = substr($host, 0, $commapos);
        }
        return $host;
    }
}
?>
