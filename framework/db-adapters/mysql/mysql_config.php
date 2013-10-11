<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

use \mysqli;

class MysqlConfig extends DataSourceConfig {

    public function __construct(Params $config) {
	    $config->checkRequired('dbname');
	    $config->engine = 'mysql'; 
		$config->if_empty_storage('InnoDB');
		$config->if_empty_charset('UTF8');	
		$config->if_empty_user(ini_get('mysqli.default_user'));
		$config->if_empty_pass(ini_get('mysqli.default_pw'));
		if ($config->layer == 'pdo') {		    
			if ($config->sock) {
				$config->dsn = 'mysql:unix_socket=' . $config->sock . ';dbname=' . $config->dbname;
			} else {
				$dsn = 'mysql:dbname=' . $config->dbname . ';host=' . $config->host;
				if ($config->port) { $dsn .= ';port=' . $config->port; }
				$config->dsn = $dsn;
			}
			$config->_class = 'MysqlPdoConn';
        } else {
		    $config->_class = 'MysqlConn';
			$config->if_empty_host(ini_get('mysqli.default_host'));			
			$config->if_empty_port(ini_get('mysqli.default_port'));
			$config->if_empty_sock(ini_get('mysqli.default_socket'));				
		}
	    parent::__construct($config);
	}

	public function create() {
        $mysqli = new mysqli($this->host, $this->user, $this->pass);
        return $mysqli->query("CREATE DATABASE IF NOT EXISTS `{$this->dbname}`;");
	}	
}
?>