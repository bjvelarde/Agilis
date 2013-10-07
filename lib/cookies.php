<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;
/**
 * A wrapper class for $_COOKIE
 *
 * @internal Do not implement a __destruct() function for this class because it will make the cookie expiration useless.
 */
class Cookies extends Singleton {
    /*
     * Retrieves the value of a cookie variable.
     */
    public function __get($var) {
        return isset($_COOKIE[$var])? $_COOKIE[$var]: NULL;
    }
    /*
     * Sets a value for a cookie variable.
     */
    public function __set($var, $val) {
        if (is_array($val)  && isset($val['value']) && isset($val['expire'])) {
            $value  = $val['value'];
            $expire = $val['expire'];
        } else {
            $value  = $val;
            $expire = '';
        }
        return setcookie($var, $value, $expire);
    }
}
?>