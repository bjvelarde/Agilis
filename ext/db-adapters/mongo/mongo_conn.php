<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

use \Mongo as Mongo;

class MongoConn extends DataSource {

    public function __construct(MongoConfig $datasrc) {
        if (!isset($datasrc->host)) {
            $this->conn = new Mongo();
        } else {
            $dsn = $datasrc->host . (isset($datasrc->port) ? ":{$datasrc->port}" : '');
            $this->conn = new Mongo($dsn);
        }        
		if (!$this->conn) {
		    throw new Exception('Failed to connect to Mongo DB');
		}
        parent::__construct($datasrc);
    }	
    /**
     * Fetch a single record from Database that matched the query
     *
     * @param array $query
     * @param int $type
     * @return array
     */
    public function fetch($query, $type='') {
        list($collection, $criteria) = each($query);
        return $this->conn->{$collection}->findOne($criteria);
    }
    /**
     * Fetch all records from Database that matched the query
     *
     * @param string $query
     * @param string $key if we want our dataset to have associative keys
     * @param int $type
     * @return array
     */
    public function fetchAll($query, $key='', '') {
        list($collection, $criteria) = each($query);
        return $this->conn->{$collection}->find($criteria);
    }
    /**
     * Fetch a single record's column from Database that matched the query
     *
     * @param string $query
     * @param int $col column number
     * @return array
     */
    public function fetchColumn($query, $col=0) {return;}

}
?>