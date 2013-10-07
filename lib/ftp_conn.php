<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;
/**
 * A wrapper class for most common Ftp operations.
 */
class FtpConn extends FixedObject {
    /**
     * class constructor.
     *
     * @param array $config
     * @throws FtpConnectionException
     * @throws FtpLoginException
     */
    public function __construct(Params $config) {
        parent::__construct('conn_id', 'host', 'user', 'pass', 'secure', 'timeout');
        $config->checkRequired('host');
        $config->checkRequired('user');
        $config->checkRequired('pass');
        $this->host    = $config->host;
        $this->user    = $config->user;
        $this->pass    = $config->pass;
        $this->port    = Common::ifEmpty($config->port, 21);
        $this->timeout = Common::ifEmpty($config->timeout, 90);
        $this->secure  = Common::ifEmpty($config->secure, FALSE);
        $conn_func = ($this->secure)? 'ftp_ssl_connect': 'ftp_connect';
        if (($this->conn_id = $conn_func($this->host, $this->port, $this->timeout)) === FALSE) {
            throw new FtpConnectionException($this->host, $this->port);
        } else {
            if (ftp_login($this->conn_id, $this->user, $this->pass) === FALSE) {
                throw new FtpLoginException($this->host, $this->user);
            }
        }
    }
    /*
     * class destructor - closes Ftp connection.
     */
    public function __destruct() { if (is_resource($this->conn_id)) { ftp_close($this->conn_id); } }
    /**
     * copy constructor.
     *
     * @throws FtpConnectionException
     * @throws FtpLoginException
     * @return object FtpConn object with a unique Ftp stream resource handle.
     */
    public function __clone() {
       $conn_func = ($this->secure)? 'ftp_ssl_connect': 'ftp_connect';
       if (($this->conn_id = $conn_func($this->host, $this->port, $this->timeout)) === FALSE) {
          throw new FtpConnectionException($this->host, $this->port);
       } else {
          if (ftp_login($this->conn_id, $this->user, $this->pass) === FALSE) {
             throw new FtpLoginException($this->host, $this->user);
          }
       }
    }
    /*
     * Overload built-in ftp functions.
     */
    public function __call($method, $args) {
        $not_allowed = array('ssl_connect', 'connect', 'close', 'quit', 'set_option', 'get_option', 'login');
        if (!in_array($method, $not_allowed)) {
            $function = 'ftp_' . $method;
            if (function_exists($function)) {
                array_unshift($args, $this->conn_id);
                return call_user_func_array($function, $args);
            }
        }
    }
    /*
     * getter of ftp options
     */
    public function __get($option) {
        $const = constant('Ftp_' . strtoupper($option));
        return $const ? ftp_get_option($this->conn_id, $const) : parent::__get($option);
    }
    /*
     * setter of ftp options
     */
    public function __set($option, $value) {
        $const = constant('Ftp_' . strtoupper($option));
        if ($const) {
            ftp_set_option($this->conn_id, $const, $value);
        } else {
            parent::__set($option, $value);
        }
    }
    /**
     * Returns the last modified time of the given file on the server
     *
     * @param string $remote_file
     * @return bool
     */
    public function lastModified($remote_file) { return ftp_mdtm($this->conn_id, $remote_file); }
    /**
     * Turns passive mode on or off
     *
     * @param bool $mode
     * @return bool
     */
    public function passiveMode($mode=True) { return ftp_pasv($this->conn_id, $mode); }
}

class FtpLoginException extends \Exception {
    
    public function __construct($host, $user) {
        parent::__construct("Failed to login user: $user on Ftp host: $host");
    }
}

class FtpConnectionException extends \Exception {
    
    public function __construct($host, $port) {
        parent::__construct("Failed to connect to Ftp: $host:$port");
    }
}
?>