<?php
namespace Agilis;

use \SQLite3 as SQLite3;

class Sqlite3Conn extends SqlDataSource {

    public function __construct(Sqlite3Config $config) {
        $this->conn = new SQLite3($config->dbname);
        parent::__construct($config);
    }
	public function query($query) {
	    $stmt = $this->conn->query($query);
		if (!$stmt) {
		    throw new Sqlite3Exception($query);
		}
		return $stmt;
	}

	public function prepare($query) {
	    $stmt = $this->conn->prepare($query);
		if (!$stmt) {
		    throw new Sqlite3Exception($query);
		}
		return $stmt;
	}
    
    public function escape($value) { return $this->conn->escapeString($value); }
    /**
     * Fetch a single record from Database that matched the query
     *
     * @param string $query
     * @param int $type
     * @return array
     */
    public function fetch($query, $type=SQLITE3_ASSOC) {        
        $stmt = $this->query($query);
        return $stmt->fetchArray($type);
    }
    /**
     * Fetch all records from Database that matched the query
     *
     * @param string $query
     * @param string $key if we want our dataset to have associative keys
     * @param int $type
     * @return array
     */
    public function fetchAll($query, $key='', $type=SQLITE3_ASSOC) {        
        $stmt = $this->query($query);
        $data = array();
        while ($rec = $stmt->fetchArray($type)) {            
            if ($key) {
                $data[$rec[$key]] = $rec;
            } else {
                $data[] = $rec;
            }
        }
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
        $stmt = $this->query($query);
        $data = $stmt->fetchArray(SQLITE3_NUM);
        return $data[$col];
    }

}

class Sqlite3Exception extends \Exception {

    public function __construct($sql) {
        parent::__construct("Failed to prepare or execute SQL: $sql");
    }
}
?>
