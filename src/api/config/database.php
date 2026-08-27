<?php 
/*
This file defines class to establish connection to the Database.

@author Dewang Saxena, <dewang2610@gmail.com>
@date Feb 24, 2022
*/
require_once 'configurations.php';

/**
 * This method will establish connection to the Database.
 * @return PDO 
 */
function get_db_instance(): PDO {
    try {
        $host = DB_HOST;
        $db_name = DB_NAME;

        // Try to establish connection to the database.
        $args = [
            // PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ];
        $instance = new PDO("mysql:host=$host;dbname=$db_name;charset=UTF8;", DB_USERNAME, DB_PASSWORD, $args);

        return $instance;
    }
    catch (Exception|Throwable|PDOException $e) {
        die('Cannot establish connection to the Database: '. $e -> getMessage());
    }
}

?>