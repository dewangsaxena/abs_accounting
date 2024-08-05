<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";

/**
 * This class implements CSRF Token.
 */
class CSRF {

    // Key for indexing
    public const KEY = 'csrf_token';

    // Random byte length
    private const BYTES = 32;

    /**
     * This method will generate a Random CSRF token.
     * @return string
     */
    public static function generate_token(): string {
        return Utils::generate_token(self::BYTES);
    }

    /**
     * This method will verify CSRF Token.
     * @param token
     * @return bool
     */
    public static function is_token_valid(string $token) : bool {
        if(session_status() === PHP_SESSION_ACTIVE) return $token === ($_SESSION[self::KEY] ?? null);
        else return false;
    }
}
?>