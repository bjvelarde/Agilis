<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;
/**
 * XML-RPC Server
 */
class XmlRpcServer {

    private $resource;

    public function __construct() { $this->resource = xmlrpc_server_create(); }

    public function __destruct() { xmlrpc_server_destroy($this->resource); }

    public function __call($method, $args) {
        if ($method != 'destroy' && $method != 'create') {
            $method = 'xmlrpc_server_' . $method;
            if (function_exists($method)) {
                array_unshift($args, $this->resource);
                return call_user_func_array($method, $args);
            }
        }
        return NULL;
    }
}
?>
