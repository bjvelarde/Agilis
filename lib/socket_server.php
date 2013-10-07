<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;
/**
 * Socket server stream
 */
class SocketServer extends Stream {
    /*
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        $this->stream = call_user_func_array('stream_socket_server', func_get_args());
        if ($this->stream === FALSE) {
            throw new SocketServerException($socket, $errno, $errstr);
        }
    }
    /*
     * allow native socket stream functions to be called as class methods
     */
    public function __call($method, $args) {
        $short_cuts = array('accept', 'get_name');
        if (in_array($method, $shortcuts)) {
            $method = 'socket_' . $method;
        }
        return parent::__call($method, $args);
    }
}

class SocketServerException extends \Exception {

    public function __construct($socket, $errno, $errstr) {
        parent::__construct("Failed to create socket server at: $server ($errno: $errstr)");
    }
}
?>