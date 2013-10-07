<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

class XmlRpc extends HttpRequest {

    public function __construct($host, $path, $method='POST', $port=80) {
        parent::__construct($host, $path, $method, $port);
        $this->header->user_agent   = 'XML-RPC Client';
        $this->header->content_type = 'text/xml';
    }

    public function __call($service, $params) {
        $params = is_array($params) ? array_shift($params) : NULL;
        $service_parts = explode('__', $service); //e.g. users__get_profile
        $service = implode('.', $service_parts);
        $this->content = xmlrpc_encode_request($service, $params);
        $this->header->content_length = strlen($this->content);
        $resp = xmlrpc_decode($this->send());
        if (is_array($resp) && xmlrpc_is_fault($resp)) {
            throw new XmlRpcException($resp);
        } else {
            return $resp;
        }
    }

    public function __set($var, $val) {
        if ($var != 'content' && $var != 'user_agent' && $var != 'content_type') {
            parent::__set($var, $val);
        }
    }

}

class XmlRpcException extends \Exception {
    
    public function __construct($resp) {
        parent::__construct("Error Code: {$resp['faultCode']}\nError Message: {$resp['faultString']}");
    }
}
?>