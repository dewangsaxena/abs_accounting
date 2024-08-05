<?php 
/**
 * This file contains the credentials for the application.
 */
/* Database Credentials */
define('DB_HOST', 'localhost');
define('DB_USERNAME', [
    /* Localhost */
    __LOCALHOST__ => 'root', 

    /* partsv2.abs.company */ 
    __PARTS_V2__ => 'u356746783_partsv2_abs',

    /* washv2.abs.company */
    __WASH_V2__ => 'u356746783_washv2_abs',
    ][$offset]
);

define('DB_PASSWORD', [
    /* Localhost */
    __LOCALHOST__ => '',

    /* partsv2.abs.company */ 
    __PARTS_V2__ => 'h+w_W#^SfF[cb7XEo,7t#|g-4ca=k".`',

    /* washv2.abs.company */
    __WASH_V2__ => 'R~%CWR3+p],N_sSN_@D"YPKSo-}"ay,=',
    ][$offset]
);

define('DB_NAME', [
    /* Localhost */ 
    __LOCALHOST__ => 'new_abs', 

    /* partsv2.abs.company */ 
    __PARTS_V2__ => 'u356746783_partsv2_abs',

    /* washv2.abs.company */
    __WASH_V2__ => 'u356746783_washv2_abs',
    ][$offset]
);
?>