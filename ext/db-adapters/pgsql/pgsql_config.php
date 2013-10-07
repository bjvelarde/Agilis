<?php
namespace Agilis;

class PgsqlConfig extends DataSourceConfig {

    public function __construct(Params $config) {
	    $config->checkRequired('dbname');
		$config->checkRequired('rootuser');
	    $config->engine = 'pgsql'; 
		$config->ifempty_charset('UTF8');
		$config->ifempty_user($config->rootuser);
		if ($config->user == $config->rootuser) {
		    $config->pass = $config->rootpass;
		}
		if ($config->layer == 'pdo') {
			$dsn = $config->engine . ':host=' . $config->host;
			if ($config->port) { $dsn .= ' port=' . $config->port; }
			$dsn .= ' dbname=' . $config->dbname . ' user=' . $config->user . ' password=' . $config->pass;
			$config->dsn = $dsn;
			$config->_class = 'PdoConn';
        } else {
		    $config->_class = 'PgsqlConn';			
		}
	    parent::__construct($config);
	}
	
	public function __get($var) {
	    if ($var == 'connstr') { return $this->connstr; }
	    else return parent::__get($var);
	}

	public function create() {
	    $connstr = array();
		$connstr[] = 'user='     . $this->rootuser;
		$connstr[] = 'password=' . $this->rootpass; 
		if ($this->host) { $connstr[] = 'host='     . $this->host; }
		if ($this->port) { $connstr[] = 'port='     . $this->port; }
        $link = pg_connect(implode(' ', $connstr));
		$result = TRUE;
		//if ($this->user != $this->rootuser) {
		//    $result = pg_query($link, "CREATE ROLE {$this->user} LOGIN ENCRYPTED PASSWORD 'md5" . md5($this->pass . $this->user) . "' VALID UNTIL 'infinity';");
		//} 
        if ($result) {			    
            $result = pg_query($link, "CREATE DATABASE {$this->dbname} WITH ENCODING='{$this->charset}' OWNER={$this->user} CONNECTION LIMIT=-1;");
		}
		return $result;
	}	
}
?>