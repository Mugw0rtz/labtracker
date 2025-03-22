<?php
/**
 * Database Connection Class
 * Handles database connections and queries
 */

class Database {
    private $host;
    private $username;
    private $password;
    private $database;
    private $conn;
    
    /**
     * Constructor - Initialize database connection parameters
     */
    public function __construct() {
        require_once 'config.php';
        
        $this->host = DB_HOST;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        $this->database = DB_NAME;
    }
    
    /**
     * Create and return a database connection
     * @return mysqli Database connection object
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new mysqli($this->host, $this->username, $this->password, $this->database);
            $this->conn->set_charset("utf8");
            
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
        } catch (Exception $e) {
            echo "Database Error: " . $e->getMessage();
        }
        
        return $this->conn;
    }
    
    /**
     * Execute a prepared SQL query
     * @param string $query SQL query with placeholders
     * @param string $types Parameter types ('s' for string, 'i' for integer, etc.)
     * @param array $params Array of parameters to bind
     * @return mysqli_stmt|false Returns statement object or false on failure
     */
    public function executeQuery($query, $types = "", $params = []) {
        $stmt = $this->conn->prepare($query);
        
        if ($stmt === false) {
            return false;
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Fetch a single row from a query result
     * @param string $query SQL query with placeholders
     * @param string $types Parameter types
     * @param array $params Array of parameters to bind
     * @return array|null Returns associative array of row data or null
     */
    public function fetchSingle($query, $types = "", $params = []) {
        $stmt = $this->executeQuery($query, $types, $params);
        
        if ($stmt === false) {
            return null;
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $stmt->close();
        
        return $row;
    }
    
    /**
     * Fetch multiple rows from a query result
     * @param string $query SQL query with placeholders
     * @param string $types Parameter types
     * @param array $params Array of parameters to bind
     * @return array Returns associative array of all rows
     */
    public function fetchAll($query, $types = "", $params = []) {
        $stmt = $this->executeQuery($query, $types, $params);
        
        if ($stmt === false) {
            return [];
        }
        
        $result = $stmt->get_result();
        $rows = [];
        
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        
        $stmt->close();
        
        return $rows;
    }
    
    /**
     * Insert data into a table
     * @param string $query SQL query with placeholders
     * @param string $types Parameter types
     * @param array $params Array of parameters to bind
     * @return int|false Returns last inserted ID or false on failure
     */
    public function insert($query, $types = "", $params = []) {
        $stmt = $this->executeQuery($query, $types, $params);
        
        if ($stmt === false) {
            return false;
        }
        
        $insertId = $this->conn->insert_id;
        $stmt->close();
        
        return $insertId;
    }
    
    /**
     * Update data in a table
     * @param string $query SQL query with placeholders
     * @param string $types Parameter types
     * @param array $params Array of parameters to bind
     * @return int|false Returns number of affected rows or false on failure
     */
    public function update($query, $types = "", $params = []) {
        $stmt = $this->executeQuery($query, $types, $params);
        
        if ($stmt === false) {
            return false;
        }
        
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows;
    }
    
    /**
     * Delete data from a table
     * @param string $query SQL query with placeholders
     * @param string $types Parameter types
     * @param array $params Array of parameters to bind
     * @return int|false Returns number of affected rows or false on failure
     */
    public function delete($query, $types = "", $params = []) {
        return $this->update($query, $types, $params);
    }
    
    /**
     * Check if a record exists in a table
     * @param string $query SQL query with placeholders
     * @param string $types Parameter types
     * @param array $params Array of parameters to bind
     * @return bool Returns true if record exists, false otherwise
     */
    public function recordExists($query, $types = "", $params = []) {
        $result = $this->fetchSingle($query, $types, $params);
        return $result !== null;
    }
    
    /**
     * Close the database connection
     */
    public function closeConnection() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>
