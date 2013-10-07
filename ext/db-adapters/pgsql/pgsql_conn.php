<?php
namespace Agilis;

use \Exception as Exception;

class PgsqlConn extends SqlDataSource {

    private $stmtname;

    public function __construct(PgsqlConfig $datasrc) {
	    $connstr = array();
		$connstr[] = 'user='     . $datasrc->rootuser;
		$connstr[] = 'password=' . $datasrc->rootpass;
		if ($datasrc->host) {   $connstr[] = 'host='   . $datasrc->host; }
		if ($datasrc->port) {   $connstr[] = 'port='   . $datasrc->port; }
		if ($datasrc->dbname) { $connstr[] = 'dbname=' . $datasrc->dbname; }
        $this->conn = new Pgsql(implode(' ', $connstr), $datasrc->persistent);
        $this->conn->set_client_encoding($datasrc->charset);
        $this->stmtname = '';
        parent::__construct($datasrc);
    }

	public function prepare($query) {
        $this->stmtname = uniqid('PGSTMT');
	    $stmt = $this->conn->prepare($this->stmtname, $query);
		if (!$stmt) {
		    throw new Exception("Failed preparing sql: ". $query);
		}
		return $stmt;
	}

	public function execute($data) {
	    return $this->conn->execute($this->stmtname, $data);
	}

    public function escape($value) { return $this->conn->escape_string($value); }

    /**
     * Fetch a single record from Database that matched the query
     *
     * @param string $query
     * @param int $type
     * @return array
     */
    public function fetch($query, $type=PGSQL_ASSOC) {
        $stmt = $this->query($query);
        return $stmt->fetch_array(NULL, $type);
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
        $stmt = $this->query($query);
		$data = array();
		$data = $stmt->fetch_all();
		if ($data && $key) {
			$newdata = array();
			foreach ($data as $rec) {
				$newdata[$rec[$key]] = $rec;
			}
			$data = $newdata;
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
        $data = $stmt->fetch_all_columns($col);
        return $data[0];
    }

}
?>