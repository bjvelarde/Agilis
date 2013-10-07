<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;
/**
 * Client URL Handle class
 */
class Curly {
    /**
     * @var resource Holds the handle resource
     */
    private $resource;
    /**
     * @var string The URL
     */
    private $url;
    /**
     * Constructor
     *
     * @param string $url The URL
     * @param Params $options cURL options
     * @return Curly
     */
    public function __construct($url='', Params $options=NULL) {
        $this->resource = curl_init($url);
        $this->url      = $url;
        if ($options) {
            $this->options = $options;
        }
    }
    /*
     * Destructor. Closes the connection and deletes the resource.
     */
    public function __destruct() {
        if (is_resource($this->resource)) {
            curl_close($this->resource);
        }
    }
    /**
     * Clone the object with a copy of the original resource
     *
     * @return Curly
     */
    public function __clone() {
        if (is_resource($this->resource)) {
            $this->resource = curl_copy_handle($this->resource);
        }
    }
    /**
     * Retrieve options
     *
     * @param string $opt The option name
     * @return mixed
     */
    public function __get($opt) {
        if ($opt == 'resource') {
            return $this->{$opt};
        } elseif ($opt == 'content') {
            return curl_multi_getcontent($this->resource);
        } elseif ($opt == 'error' || $opt == 'errno') {
            $func = 'curl_' . $opt;
            return $func($this->resource);
        } else {
            $opt = 'CURLINFO_' . strtoupper($opt);
            return curl_getinfo($this->resource, constant($opt));
        }
    }
    /**
     * Set option
     *
     * @param string $opt The option name
     * @param string $value The option value
     */
    public function __set($opt, $value) {
        if ($opt == 'options' && is_array($value)) {
            $options = array();
            foreach ($value as $k => $v) {
                $key = constant('CURLOPT_' . strtoupper($k));
                $options[$key] = $v;
            }
            curl_setopt_array($this->resource, $options);
        } else {
            $opt = 'CURLOPT_' . strtoupper($opt);
            curl_setopt($this->resource, constant($opt), $value);
        }
    }
    /**
     * Perform a cURL session
     *
     * @return mixed
     */
    public function exec() { return curl_exec($this->resource); }
    /**
     * Gets cURL version information
     *
     * @return array
     */
    public static function version() { return curl_version(); }
}
?>