<?php
/**
 * Database class for handling database operations
 */
class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    
    private $conn;
    private $stmt;
    private $error;
    
    /**
     * Constructor - Creates database connection
     */
    public function __construct() {
        // Set DSN
        $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->dbname;
        
        // Set options
        $options = array(
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
        );
        
        // Create PDO instance
        try {
            $this->conn = new PDO($dsn, $this->user, $this->pass, $options);
        } catch(PDOException $e) {
            $this->error = $e->getMessage();
            error_log('Database Connection Error: ' . $this->error);
            die('Database connection failed');
        }
    }
    
    /**
     * Prepare statement with query
     * 
     * @param string $sql SQL query
     * @return void
     */
    public function query($sql) {
        $this->stmt = $this->conn->prepare($sql);
    }
    
    /**
     * Bind values to prepared statement using named parameters
     * 
     * @param string $param Parameter name
     * @param mixed $value Parameter value
     * @param mixed $type Parameter type
     * @return void
     */
    public function bind($param, $value, $type = null) {
        if(is_null($type)) {
            switch(true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        
        $this->stmt->bindValue($param, $value, $type);
    }
    
    /**
     * Execute the prepared statement
     * 
     * @return bool True on success
     */
    public function execute() {
        return $this->stmt->execute();
    }
    
    /**
     * Get result set as array of objects
     * 
     * @return array Result set
     */
    public function resultSet() {
        $this->execute();
        return $this->stmt->fetchAll();
    }
    
    /**
     * Get result set as array of associative arrays
     * 
     * @return array Result set
     */
    public function resultSetArray() {
        $this->execute();
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get single record as object
     * 
     * @return object Single record
     */
    public function single() {
        $this->execute();
        return $this->stmt->fetch();
    }
    
    /**
     * Get single record as associative array
     * 
     * @return array Single record
     */
    public function singleArray() {
        $this->execute();
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get row count
     * 
     * @return int Row count
     */
    public function rowCount() {
        return $this->stmt->rowCount();
    }
    
    /**
     * Get last inserted ID
     * 
     * @return int Last inserted ID
     */
    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }
    
    /**
     * Begin a transaction
     * 
     * @return bool True on success
     */
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }
    
    /**
     * Commit a transaction
     * 
     * @return bool True on success
     */
    public function commit() {
        return $this->conn->commit();
    }
    
    /**
     * Roll back a transaction
     * 
     * @return bool True on success
     */
    public function rollBack() {
        return $this->conn->rollBack();
    }
    
    /**
     * Returns the error info
     * 
     * @return string Error info
     */
    public function getError() {
        return $this->error;
    }
}