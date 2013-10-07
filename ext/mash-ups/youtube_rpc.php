<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

class YoutubeRpc {

    const SVC_URI = 'www.youtube.com';
    const SVC_PATH = '/api2_rest'

    private $params;
    private $request;

    public function __construct($devid, $type='REST') {
        if ($type != 'REST') {
            $method = 'POST';
            $class = 'XmlRpc';
        } else {
            $method = 'GET';
            $class = 'HttpRequest';
        }
        $this->request = new $class(self::SVC_URI, self::SVC_PATH, $method);
        $this->params = new DynaStruct;
        $this->params->dev_id = $devid;
    }

    public function __set($var, $val) { $this->params->{$var} = $val; }

    public function __get($var) { return $this->params->{$var}; }

    public function __call($method, $args) {
        $method = 'youtube__' . $method;
        $data = $this->params->getElements();
        if (is_array($args[0]) && CraftyArray::isAssoc($args[0])) {
            $data = array_merge($data, $args[0]);
        }
        if ($this->request instanceof XmlRpc) {
            $args[0] = $data;
            return call_user_func_array(array($this->request, $method), $args);
        } else {
            $method_parts = explode('__', $method);
            $this->params->method = implode('.', $method_parts);

            $this->request->content = $data;
            return $this->request->send();
        }
    }
}
?>