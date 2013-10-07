<?php
namespace Agilis;

use \PDO;

abstract class PdoConn extends SqlDataSource {

    //public function __construct(DataSourceConfig $config) {
    //    
    //    parent::__construct($config);
    //}
	
	public function query($query) { return $this->conn->query($query); }

	public function prepare($query, $options=array()) {
	    return $this->conn->prepare($query, $options);
	}	
    
    public function escape($value) { return $this->conn->quote($value); }
    /**
     * Fetch a single record from Database that matched the query
     *
     * @param string $query
     * @param int $type
     * @return array
     */
    public function fetch($query, $type=PDO::FETCH_ASSOC) {
        $stmt = $this->query($query);
        $data = $stmt->fetch($type);
        $stmt->closeCursor();
        return $data;
    }
    /**
     * Fetch all records from Database that matched the query
     *
     * @param string $query
     * @param string $key if we want our dataset to have associative keys
     * @param int $type
     * @return array
     */
    public function fetchAll($query, $key='', $type=PDO::FETCH_ASSOC) {
        $stmt = $this->query($query);
        $data = $stmt->fetchAll($type);
        if ($data && $key) {
            $newdata = array();
            foreach ($data as $rec) {                    
                $newdata[$rec[$key]] = $rec;
            }
            $data = $newdata;
        }        
        $stmt->closeCursor();        
        return $data;
    }
    /**
     * Fetch a single record's column from Database that matched the query
     *
     * @param string $query
     * @param int $col column number
     * @return array
     */
    public function fetchColumn($query, $col=0) {
        $stmt = $this->query($query, PDO::FETCH_NUM);
        $data = $stmt->fetchColumn($col);
        $stmt->closeCursor();
        return $data;
    }

}
?>