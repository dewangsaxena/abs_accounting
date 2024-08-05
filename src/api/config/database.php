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
        $instance = new PDO("mysql:host=$host;dbname=$db_name;charset=UTF8;", DB_USERNAME, DB_PASSWORD);

        // Set PDO error mode to exception 
        $instance -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $instance;
    }
    catch (Exception|Throwable|PDOException $e) {
        die('Cannot establish connection to the Database.');
    }
}

/**
 * This method will establish connection to the Old Database.
 * @return PDO 
 */
function get_old_db_instance(): PDO {
    try {
        $host = DB_HOST;
        $db_name = SYSTEM_INIT_MODE === PARTS ? 'u356746783_parts_extract': 'u356746783_wash_extract';

        // Try to establish connection to the database.
        $instance = new PDO(
            "mysql:host=$host;dbname=$db_name;charset=UTF8;", 
            $db_name, 
            '7b$B$wkhK*R'
        );

        // Set PDO error mode to exception 
        $instance -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $instance;
    }
    catch (Exception|Throwable|PDOException $e) {
        die('Cannot establish connection to the OLD Database.');
    }
}

?>