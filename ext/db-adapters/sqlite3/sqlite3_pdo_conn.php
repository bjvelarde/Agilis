<?php
namespace Agilis;

use \PDO as PDO;

class Sqlite3PdoConn extends PdoConn {

    public function __construct(Sqlite3Config $datasrc) {
        $this->conn = new PDO($datasrc->dsn);        
        parent::__construct($datasrc);
    }

}
?>