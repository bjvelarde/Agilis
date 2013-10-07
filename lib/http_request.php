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
 * HTTP Request
 */
class HttpRequest {
    /**#@+
     * @var string
     */
    protected $method;
    protected $httpver;
    protected $path;
    /**#@-*/
    /**
     * @var HttpRequestHeader
     */
    protected $header;
    /**
     * @var int
     */
    protected $port;
    /**
     * @var mixed
     */
    protected $content;
    /**
     * Constructor
     *
     * @param string $url Remote URL
     * @param string $method POST|GET
     * @param int $port Remote port
     * @param string $httpver Http Version
     */
    public function __construct($url, $method='POST', $port=80, $httpver='1.0') {
        $url_info = parse_url($url);
        $this->method = $method;
        $this->path   = $url_info['path'];
        $this->header = new HttpRequestHeader;
        $this->port   = $port;
        $this->header->host = $url_info['host']; //$url_info['scheme'] . '://' . $url_info['host'];
        $this->content = '';
        $this->httpver = $httpver;
    }
    /**
     * getter for header properties
     *
     * @return mixed
     */
    public function __get($var) {
        return $this->header->{$var};
    }
    /**
     * setter for content and header properties
     */
    public function __set($var, $val) {
        if ($var == 'content') {
            $content = is_array($val) ? $val : array('content' => $val);
            $this->buildContent($content);
            if ($this->method == 'POST') {
                $this->header->content_type   = 'application/x-www-form-urlencoded';
                $this->header->content_length = strlen($this->content);
            } else {
                $this->path .= '?' . $this->content;
            }
        } elseif ($var != 'content_type' && $var != 'content_length') {
            $this->header->{$var} = $val;
        }
    }
    /**
     * Send the request and return the response.
     *
     * @return mixed
     */
    public function send() {
        $headers = '';
        $data = '';
        $socket = new SocketStream($this->host, $this->port);
        $socket->write($this->createRequest());
        while ($str = trim($socket->gets())) {
            $headers .= $str . "\r\n";
        }
        while (!$socket->eof()) {
            $data .= $socket->gets();
        }
        $socket->close();
        return $data;
    }
    /**
     * Create request string
     *
     * @throw HttpRequestException
     * @return string
     */
    private function createRequest() {
        if (!$this->content) {
            throw new HttpRequestException;
        }
        return $this->method . ' ' . $this->path . ' HTTP/' . $this->httpver . "\n" .
               $this->header . "\n" . $this->content . "\n";
    }
    /**
     * Convert content into a http query string
     *
     * @return string
     */
    protected function buildContent(array $arr) {
        $this->content = http_build_query($arr);
    }

}
/**
 * Exception thrown when creating a request without a content
 */
class HttpRequestException extends \Exception {

    public function __construct() {
        parent::__construct('Attempted to create HTTP Request without content.');
    }
}
?>