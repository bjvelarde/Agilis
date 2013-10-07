<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

use \SQLite3 as SQLite3;

class Sqlite3Config extends DataSourceConfig {

    public function __construct(Params $config) {
	    $config->checkRequired('dbname'); 	  
	    $config->engine = 'sqlite3';	  
		if ($config->layer == 'pdo') {
			$config->dsn = $config->engine . ':' . $config->dbname;
			$config->_class = 'PdoConn';
        } else {
		    $config->_class = 'Sqlite3Conn';			
		}
	    parent::__construct($config);
	}

	public function create() { return new SQLite3($this->dbname); }	
}
?>