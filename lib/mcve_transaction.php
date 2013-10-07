<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

class McveTransaction {
    
    private $identifier;
    private $conn;
    
    public function __construct(McveConnection $conn) {
        $this->conn = $conn;
        $this->identifier = $this->conn->transnew();        
    }
    
    public function __destruct() { $this->conn->deletetrans($this->identifier); }
    
    public function __set($key, $value) {
        if (!$this->conn->transkeyval($this->identifier, $key, $value)) {
            throw new McveTransactionException('Failed to set transaction value of ' . $key);
        }
    }
}

class McveTransactionException extends \Exception {}
?>