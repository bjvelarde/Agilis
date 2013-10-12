<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

use \MongoClient;

class MongoConn extends DataSource {

    public function __construct(MongoConfig $datasrc) {
        try {
            if (!$datasrc->host) {
                $mongo = new MongoClient();
            } else {
                $dsn = 'mongodb://' . $datasrc->host . ($datasrc->port ? ":{$datasrc->port}" : '');
                $mongo = new MongoClient($dsn);
            }
            $this->conn = $mongo->{$datasrc->dbname};            
        } catch(Exception $e) {            
            throw new Exception($e->getMessage());
        }
        parent::__construct($datasrc);
    }	
    
    public function connect() { return TRUE; }
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
    public function fetchAll($query, $key='', $type='') {
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