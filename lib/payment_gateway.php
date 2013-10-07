<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

use \Spyc;
use \Exception;

abstract class PaymentGateway extends Singleton {

    static protected $config = array();
    protected $name;
    
    protected function loadConfig($config_name) {
        $this->name = $config_name;
        if (!isset(self::$config[$config_name]) || !self::$config[$config_name] instanceof DynaStruct) {
            $yml_file = APP_ROOT . 'config/payment-gateways/' . $config_name . '.yml';
            if (!file_exists($yml_file)) {
                throw new PaymentGatewayException('Missing payment gateway configuration file: ' . $yml_file);
            }
            self::$config[$config_name] = new DynaStruct(Spyc::YAMLLoad($yml_file));            
        }
    }
    
    public function __get($var) {    
        if (isset(self::$config[$this->name]) && self::$config[$this->name] instanceof DynaStruct) {
            return self::$config[$this->name][$var];
        }
        return NULL;
    }

    public function ipInRange($ip, $range) {
        list($lower, $upper) = explode('-', $range, 2);
        $range_start = ip2long(trim($lower));
        $range_end   = ip2long(trim($upper));
        $ip          = ip2long($ip);
        return ($ip >= $range_start && $ip <= $range_end);
    }

    public function checkIp($remote_ip) {
        if ($this->check_source_ip === FALSE) {
            return TRUE;
        }
        foreach ($this->source_ips as $ip) {
            if ((FALSE === strpos($ip, '-') && ($remote_ip == $ip)) || ($this->ipInRange($remote_ip, $ip))) {
                return TRUE;
            }
        }
        return FALSE;
    }

    abstract public function buildParams(PaymentInterface $payment);
    abstract public function getPostUrl();
}

class PaymentGatewayException extends Exception {}
?>