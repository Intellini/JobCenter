<?php
/**
 * Database Configuration
 * Singleton pattern for database connection
 */

// Get base path
$basePath = realpath(__DIR__ . '/../../');

// Include connection credentials
require_once($basePath . '/dbcon/conn.php');
require_once($basePath . '/dbcon/adodb.inc.php');

class Database {
    private static $instance = null;
    private $db;
    
    private function __construct() {
        try {
            $this->db = ADONewConnection("mysqli");
            $this->db->Connect(j_srv, j_usr, j_pwd, j_db);
            $this->db->SetFetchMode(ADODB_FETCH_ASSOC);
            $this->db->debug = false; // Ensure debug is off
        } catch (Exception $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->db;
    }
    
    /**
     * Execute a query with optional parameters
     */
    public function execute($sql, $params = []) {
        if (!empty($params)) {
            return $this->db->Execute($sql, $params);
        }
        return $this->db->Execute($sql);
    }
    
    /**
     * Get a single value
     */
    public function getOne($sql, $params = []) {
        if (!empty($params)) {
            return $this->db->GetOne($sql, $params);
        }
        return $this->db->GetOne($sql);
    }
    
    /**
     * Alias for getOne() for consistency
     */
    public function getValue($sql, $params = []) {
        return $this->getOne($sql, $params);
    }
    
    /**
     * Execute a query (alias for execute)
     */
    public function query($sql, $params = []) {
        return $this->execute($sql, $params);
    }
    
    /**
     * Get a single row
     */
    public function getRow($sql, $params = []) {
        if (!empty($params)) {
            return $this->db->GetRow($sql, $params);
        }
        return $this->db->GetRow($sql);
    }
    
    /**
     * Get all rows
     */
    public function getAll($sql, $params = []) {
        if (!empty($params)) {
            return $this->db->GetArray($sql, $params);
        }
        return $this->db->GetArray($sql);
    }
    
    /**
     * Insert data into table
     */
    public function insert($table, $data) {
        return $this->db->AutoExecute($table, $data, 'INSERT');
    }
    
    /**
     * Update table data
     */
    public function update($table, $data, $where) {
        return $this->db->AutoExecute($table, $data, 'UPDATE', $where);
    }
    
    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        return $this->db->Insert_ID();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->db->BeginTrans();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->db->CommitTrans();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->db->RollbackTrans();
    }
    
    /**
     * Close connection
     */
    public function close() {
        if ($this->db) {
            $this->db->Close();
        }
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Helper function for quick database access
 */
function db() {
    return Database::getInstance();
}