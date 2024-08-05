<?php 
/**
 * This file implements methods for validating user input. 
 * 
 * @author Dewang Saxena, <dewang2610@gmail.com>
 * @date 24 Mar, 2022
*/
class Validate {

    // RegEx expression for validating name.
    private const REGEX_NAME_EXPR = '/^[0-9a-zA-Z\s\.\/\(\)\&\[\]\,\-\'\"\:\@]+$/';

    // RegEx expression for Validating Email
    private const REGEX_EMAIL_EXPR = '/^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,})+$/i';

    public static function is_float(string $var): bool {
        return filter_var($var, FILTER_VALIDATE_FLOAT);
    }

    public static function is_numeric(?string $var): bool {
        return is_numeric($var);
    }

    public static function is_email_id(string $email) : bool {
        return preg_match(self::REGEX_EMAIL_EXPR, trim($email)); 
    }

    public static function is_url(string $url) : bool {
        return filter_var($url, FILTER_VALIDATE_URL);
    }

    public static function is_alpha(string $text): bool {
        return ctype_alpha($text);
    }

    public static function is_alnum(string $text) : bool {
        return ctype_alnum($text);
    }

    public static function is_name(string $name): bool {
        return preg_match(self::REGEX_NAME_EXPR, $name);
    }

    public static function is_address(string $address): bool {
        // For now just check for presence of few characters
        // Maybe improvise this express later
        // ^[a-zA-Z\d\s\\\-\,\.\#]+$
        return isset($address[0]);
    }

    /**
     * This method will validate the date.
     * @param date 
     * @return bool 
     */
    public static function is_date(?string $date) : bool {
        try {
            // Check for Valid Argument
            if(!isset($date[0])) return false;

            if(count(explode('-', $date)) === 3) $format = 'Y-m-d';
            else if(count(explode('/', $date)) === 3) $format = 'm/d/Y';
            else return false;

            // Create Date
            $new_date = DateTime::createFromFormat($format, $date);

            // Validate Date
            return $new_date && $new_date -> format($format) === $date;
        }
        catch(Throwable $th) {
            return false;
        }
    }
}   

?>