<?php
namespace Agilis;

use \PDO as PDO;

class MysqlPdoConn extends PdoConn {

    public function __construct(MysqlConfig $datasrc) {
        parent::__construct($datasrc);
    }

    public function connect() {
        $this->conn = new PDO($this->dsn, $this->user, $this->pass);
		$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
}
?>