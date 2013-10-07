<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

/**
 * A wrapper class for the $_SESSION var
 *
 * @internal Do not implement a __destruct() function for this class because we don't want a
 * session object to auto-destruct when getting out of scope. If you're done using the session object,
 * call the {@link Session::destroy()} explicitly.
 *
 * @author Benjie Velarde bvelarde@gmail.com
 * @copyright 2012 BV 
 */
class Session extends Singleton {
    /**
     * Fetch a session variable
     *
     * @return mixed
     */
    public function __get($var) { return self::get($var); }
    /**
     * Set a session variable
     */
    public function __set($var, $val) { self::set($var, $val); }
    /**
     * Fetch a session variable
     *
     * @return mixed
     */
    public static function get($var) {
        return isset($_SESSION[$var]) ? $_SESSION[$var] : NULL;
    }
    /**
     * Set a session variable
     */
    public static function set($var, $val) { $_SESSION[$var] = $val; }    
    /**
     * Get and/or set the current session id
     *
     * @param string $id
     * @return string
     */
    public function id($id='') { return ($id) ? session_id($id) : session_id(); }
    /**
     * Check if session is empty
     *
     * @return bool
     */
    public function is_empty() { return !count($_SESSION); }     
    /**
     * Update the current session id with a newly generated one
     *
     * @param bool $delete_old_session
     * @return bool
     */
    public function regenerateId($delete_old_session=FALSE) { return session_regenerate_id($delete_old_session); }
    /**
     * Singleton instance accessor
     *
     * @return Session
     */
    public static function start() {
        session_start(); 
        return self::getInstance();
    }
    /**
     * Clears (empty-out) $_SESSION array
     */
    public static function clear() { $_SESSION = array(); }
    /**
     * Frees all session variables currently registered and destroys all of the data associated with
     * the current session.
     *
     * @return bool
     */
    public static function destroy() {
        $_SESSION = array();
        return session_destroy();
    }
    /**
     * Registers Session variables.
     *
     * Accepts a variable number of arguments, any of which can be either a string holding the name of a
     * variable or an array consisting of variable names or other arrays.
     *
     * @param string $sessionvar,...
     * @return bool
     */
    public static function register() {
        $vars = func_get_args();
        foreach ($vars as $var) {
            if (empty($_SESSION[$var])) {
                $_SESSION[$var] = '';
            }
        }
    }
    /**
     * Unregisters a session variable.
     *
     */
    public static function unregister() {
        $args = func_get_args();
        if ($args) {
            foreach ($args as $var) {
                unset($_SESSION[$var]);
            }
        }
    }
}
?>