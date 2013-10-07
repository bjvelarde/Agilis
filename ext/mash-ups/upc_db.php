<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

class UpcDb extends XmlRpc {

    public function __construct() {
        parent::__construct('www.upcdatabase.com', '/rpc');
    }

    public function __call($method, $args) {
        $methods = array(
            'help',
            'lookupEAN',
            'lookupUPC',
            'writeEntry',
            'calculateCheckDigit',
            'convertUPCE',
            'decodeCueCat',
            'latestDownloadURL'
        );
        if (in_array($method, $methods)) {
            return parent::__call($method, $args);
        }
    }
}
?>