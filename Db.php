<?php
class Db {
    // The database connection
    protected static $connection = false;

    private $username;
    private $password;
    private $dbname;
    private $host;

    public function __construct($config) {
        $this->username = $config["username"];
        $this->password = $config["password"];
        $this->dbname = $config["dbname"];
        $this->host = $config["host"];
    }

    /**
     * Connect to the database
     * 
     * @return bool false on failure / mysqli MySQLi object instance on success
     */
    public function connect($first=true) {
        // Try and connect to the database
        if (self::$connection === false) {
            // Load configuration as an array. Use the actual location of your configuration file
            self::$connection = new mysqli($this->host, $this->username, $this->password, $this->dbname);
        }

        // If connection was not successful, handle the error
        if (self::$connection === false) {
            debug("Error connecting to database...");
            // Handle error - notify administrator, log to a file, show an error screen, etc.
            return false;
        }

        if (!self::$connection->ping()) {
            debug("Connection to DB is broken...");
            self::$connection->close();
            self::$connection = false;

            if ($first) {
                debug("Trying to reconnect...");
                return self::connect(false);
            }
        }

        return self::$connection;
    }

    /**
     * Query the database
     *
     * @param $query The query string
     * @return mixed The result of the mysqli::query() function
     */
    public function query($query) {
        // Connect to the database
        $connection = $this -> connect();

        // Query the database
        $result = $connection -> query($query);

        return $result;
    }

    /**
     * Fetch rows from the database (SELECT query)
     *
     * @param $query The query string
     * @return bool False on failure / array Database rows on success
     */
    public function select($query) {
        $rows = array();
        $result = $this -> query($query);
        if($result === false) {
            return false;
        }
        while ($row = $result -> fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Fetch the last error from the database
     * 
     * @return string Database error message
     */
    public function error() {
        $connection = $this -> connect();
        return $connection -> error;
    }

    /**
     * Quote and escape value for use in a database query
     *
     * @param string $value The value to be quoted and escaped
     * @return string The quoted and escaped string
     */
    public function quote($value) {
        $connection = $this -> connect();
        return "'" . $connection -> real_escape_string($value) . "'";
    }
}

