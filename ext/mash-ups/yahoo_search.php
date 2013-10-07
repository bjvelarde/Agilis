<?php
/**
 * @package ArtOO
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

class YahooSearch {

    const SVC_URI = 'search.yahooapis.com';
    const SVC_PATH = '/WebSearchService/V1/webSearch'

    private $params;

    public function __construct($appid) {
        $this->params = new FixedStruct(
            'appid',
            'query',
            'region',
            'type',
            'results',
            'start',
            'format',
            'adult_ok',
            'similiar_ok',
            'output'
        );
        $this->appid  = $appid;
        $thid->output = 'php';
    }

    public function __set($var, $val) {
        if ($var != 'output' && $var != 'appid') {
            $this->params->{$var} = $val;
        }
    }

    public function __get($var) { return $this->params->{$var}; }

    public function search($query='') {
        if ($query) { $this->query = $query; }
        $httpreq = new HttpRequest(self::SVC_URI, self::SVC_PATH, 'GET');
        $httpreq->content = $this->params->getElements();
        return unserialize($httpreq->send());
    }
}
?>