<?php
namespace Agilis;

use \PDO as PDO;

class PgsqlPdoConn extends PdoConn {

    public function __construct(PgsqlConfig $datasrc) {
        $this->conn = new PDO($datasrc->dsn);        
        parent::__construct($datasrc);
    }

}
?>