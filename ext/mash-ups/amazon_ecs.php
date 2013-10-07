<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

class AmazonEcs {

    const SVC_URI = 'webservices.amazon';
    const SVC_PATH = '/onca/xml';

    private $httprequest;
    private $params;

    private static $keymap = array(
        'service'           => 'Service',
        'aws_access_key_id' => 'AWSAccessKeyId',
        'operation'         => 'Operation',
        'xml_escaping'      => 'XMLEscaping',
        'id_type'           => 'IdType',
        'response_group'    => 'ResponseGroup',
        'item_id'           => 'ItemId',
        'search_index'      => 'SearchIndex'
    );

    public function __construct($locale) {
        switch ($locale) {
            case 'uk': $tld = 'co.uk'; break;
            case 'de': $tld = 'de'; break;
            case 'jp': $tld = 'co.jp'; break;
            case 'fr': $tld = 'fr'; break;
            case 'ca': $tld = 'ca'; break;
            default: $tld = 'com'; break;
        }
        $this->httprequest = new HttpRequest(self::SVC_URI . ".$tld", self::SVC_PATH, 'GET');
        $this->params = new DynaStruct;
    }

    public function __set($var, $val) { $this->params->{$var} = $val; }

    public function __get($var) { return $this->params->{$var}; }

    private function getEcsKey($key) {
        if (in_array($key, array_keys(self::$keymap))) {
            return self::$keymap[$key];
        } elseif (preg_match('/^item_(\d+)_(asin|quantity)$/', $key, $matches)) {
            $end = ($matches[2] == 'asin') ? 'ASIN' : 'Quantity';
            return "Item.{$matches[1]}.$end";
        }
        return NULL;
    }

    private function buildParams() {
        $params = array();
        foreach ($this->params as $key => $val) {
            if (($ecskey = $this->getEcsKey($key)) !== NULL) {
                $params[$ecskey] = $val;
            } else {
                unset($this->params[$key]);
            }
        }
        $this->httprequest->content = $params;
    }

    public function send() {
        $this->buildParams();
        $xmlparser = new AmazonXmlParser;
        return $xmlparser->parse($this->httprequest->send());
    }
}
?>