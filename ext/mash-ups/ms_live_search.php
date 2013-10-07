<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

use \SoapClient as SoapClient;

class MsLiveSearch {

    const WSDL_URI = 'http://soap.search.msn.com/webservices.asmx?wsdl';

    private $params;

    public function __construct() {
        $this->params['Request'] = array(
            'AppID' => '',
            'Query' => '',
            'CultureInfo' => 'en-US',
            'SafeSearch' => 'Strict',
            'Flags' => '',
            'Location' => '',
            'Requests' => array(
                'SourceRequest' => array(
                    'Source' => 'Web',
                    'Offset' => 0,
                    'Count' => 50,?
                    'ResultFields' => 'All'
                )
            )
        );
    }

    public function __set($var, $val) {
        $var = ($var == 'app_id') ? 'AppID' : String::camelize($var)->ucfirst()->to_s;
        switch ($var) {
            case 'AppID':
            case 'Query':
            case 'CultureInfo':
            case 'SafeSearch':
            case 'Flags':
            case 'Location':
                $this->params['Request'][$var] = $val;
                break;
            case 'Source':
            case 'Offset':
            case 'Count':
            case 'ResultFields':
                $this->params['Request']['Requests']['SourceRequest'][$var] = $val;
                break;
        }
    }

    public function __get($var) {
        $var = ($var == 'app_id') ? 'AppID' : String::camelize($var)->ucfirst()->to_s;
        switch ($var) {
            case 'AppID':
            case 'Query':
            case 'CultureInfo':
            case 'SafeSearch':
            case 'Flags':
            case 'Location':
                return $this->params['Request'][$var];
            case 'Source':
            case 'Offset':
            case 'Count':
            case 'ResultFields':
                return $this->params['Request']['Requests']['SourceRequest'][$var];;
        }
    }

    public function search($trace=FALSE) {
        $soap = new SoapClient(self::WSDL_URI, array('trace' => $trace));
        return $soap->Search($this->params);
    }
}
?>