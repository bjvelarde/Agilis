<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

class MongoConfig extends DataSourceConfig {

    public function __construct(Params $config) {
	    $config->_class = 'MongoConn';
	    parent::__construct($config);
	}

	public function create() { return TRUE;	}	
}
?>