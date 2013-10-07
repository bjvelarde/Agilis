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
 * Wrapper class for socket stream operations.
 */
class SocketStream extends Stream {
    /**
     * Class constructor.
     *
     * @param string $url
     * @param int $port
     * @param int $timeout
     * @throws SocketStreamException
     */
    public function __construct($url, $port=80, $timeout=30) {
        parent::__construct();
        if (($this->stream = @fsockopen($url, $port, $errno, $errstr, $timeout)) === FALSE) {
            throw new SocketStreamException($url, $port, $errno, $errstr);
        }
    }
}
/**
 * Exception thrown when fsockopen failed
 */
class SocketStreamException extends \Exception {

    public function __construct($url, $port, $errno, $errstr) {
        parent::__construct("Failed to make socket connection to $url:$port\nError: $errstr [$errno]");
    }
}
?>