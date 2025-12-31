<?php 

/**
 * This file implements misc. utilities functions.
 * 
 * @author Dewang Saxena, <dewang2610@gmail.com>
 * @date 24 March, 2022
 */
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/validate.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/pdf/fpdf_merge.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/store_details.php";

class Utils {

    /**
     * This represents the months index.
     */
    public const MONTHS_INDEX = [
        'Jan' => '01',
        'Feb' => '02',
        'Mar' => '03',
        'Apr' => '04',
        'May' => '05',
        'June' => '06',
        'July' => '07',
        'Aug' => '08',
        'Sept' => '09',
        'Oct' => '10',
        'Nov' => '11',
        'Dec' => '12',
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];

    /**
     * This function will read the CSV file.
     * 
     * @param path_to_file The path to the file.
     * @return ?array Records in the CSV file or null.
     */
    public static function read_csv_file(string $path_to_file) : ?array {

        // Cannot access file.
        if (!is_readable($path_to_file)) return null;

        // Establish File handle
        $file_handle = fopen($path_to_file, 'r');

        // Rows
        $rows = [];

        // Read file
        while (!feof($file_handle)) $rows []= fgetcsv($file_handle);

        // Close the file handle
        fclose($file_handle);
        
        return $rows;
    }

    /**
     * This method will extract all the numbers from the text and return them.
     * 
     * @param text The text in which to find the numbers.
     * @param do_combine Whether to combine all the elements or not.
     * @return Array of numbers.
     */
    public static function extract_numbers(string $text, bool $do_combine=false): array|string|null {
        preg_match_all('!\d+!', $text, $matches);
        if (count($matches) !== 1 || count($matches[0]) < 1) return null;
        if ($do_combine) return implode('', $matches[0]);
        return $matches[0];
    }

    /**
     * This method will format the phone number and return the same.
     * @param phone_no Unformatted phone number.
     * @return Formatted phone number
     */
    public static function format_phone_number(string $phone_no) : string {
        if(!isset($phone_no)) return '';
        if(strlen($phone_no) !== 10) return $phone_no;

        /* Number format */
        $area_code = substr($phone_no, 0, 3);
        $central_office_code = substr($phone_no, 3, 3);
        $station_code = substr($phone_no, 6, 4);

        return "($area_code) $central_office_code-$station_code";
    }

    /**
     * This method will convert date stored in Mysql into human readable date.
     * @param date
     * @param combine_with
     * @return string formatted date.
     */
    public static function convert_date_to_human_readable(string $date, string | null $combine_with = null): string {
        if($combine_with !== null) return date('d'.$combine_with.'M'.$combine_with.'Y', strtotime($date));
        else return date('d M, Y', strtotime($date));
    }

    /** 
     * This method will format the query to accept IN operator arguments.
     * @param values The values to process
     * @param query The query
     * @param placeholder_text The placeholder text
     * @return Processed_query and its values
     */
    public static function mysql_in_placeholder(string|array $values, string $query, string $placeholder_text=':placeholder'): array {
        if(is_string($values)) $values = explode(',', $values);
        $count = count($values);
        $placeholder = str_repeat('?,', $count - 1) . '?';
        $query = str_replace(
            $placeholder_text,
            $placeholder,
            $query
        );
        return [
            'values' => $values, 
            'query' => $query
        ];
    }

    /**
     * This method will perform a PDO substitute for IN Statement in Mysql.
     * @param values
     * @param query
     * @param placeholder_text
     * @param label
     * @return array
     */
    public static function mysql_in_placeholder_pdo_substitute(string|array $values, string $query, string $placeholder_text=':placeholder', string $label='__val__'): array {
        if(is_string($values)) $values = explode(',', $values);
        $new_values = [];
        $label = ":$label". '_';
        $counter = 0;
        foreach($values as $value) {
            $new_values[$label.$counter] = $value;
            ++$counter;
        }
        $placeholder_keys = implode(',', array_keys($new_values));
        $query = str_replace(
            $placeholder_text,
            $placeholder_keys,
            $query
        );
        return [
            'query' => $query,
            'values' => $new_values,
        ];
    }

    /**
     * This method will round the amount to two nearest digits.
     * @param amount The amount on which to round off.
     * @param precision
     * @return float rounded amount
     */
    public static function round(float $amount, int $precision = 2): float {
        return round($amount, $precision, PHP_ROUND_HALF_UP);
    }

    /**
     * This method will return the time after converting it from UTC ISO 8601 to Local Business Timezone.
     * @param iso_timestamp The timestamp to convert
     * @param store_id 
     * @param use_24_hour_format Whether to convert to 24-hour format or 12 hour.
     * @return string
     */
    public static function get_local_timestamp(string $iso_timestamp, int $store_id, bool $use_24_hour_format=false) : string {
        $date_time = new DateTime(date($iso_timestamp), new DateTimeZone('UTC'));
        $date_time -> setTimezone(new DateTimeZone(StoreDetails::STORE_DETAILS[$store_id]['timezone']));
        $format = $use_24_hour_format ? 'Y-m-d H:i:s T' : 'Y-m-d h:i:s A T';
        return $date_time -> format($format);
    }

    /**
     * This method will return the timestamp in YYYY-mm-dd Format.
     * @param timestamp The timestamp to convert
     * @return string
     */
    public static function get_YYYY_mm_dd(string $timestamp): string {
        return substr($timestamp, 0, 10);
    }

    /**
     * This method will return the business date after converting it from UTC time.
     * @param store_id
     * @return string 
     */
    public static function get_business_date(int $store_id): string {
        return self::get_YYYY_mm_dd(
                    self::convert_to_local_timestamp_from_utc_unix_timestamp(
                    self::get_current_utc_unix_timestamp(),
                    $store_id
                )
        );
    }

    /**
     * This method will get the difference between the transaction and current date.
     * @param txn_date The transaction date
     * @param current_date The current date
     * @param store_id
     * @return array
     */
    public static function get_difference_between_dates(string $date_1, String|DateTime $date_2, int $store_id): array {
        $txn_date = date_create(
            $date_1, 
            new DateTimeZone(StoreDetails::STORE_DETAILS[$store_id]['timezone'])
        );
        if(gettype($date_2) === 'string') $date_2 = date_create(
            $date_2, 
            new DateTimeZone(StoreDetails::STORE_DETAILS[$store_id]['timezone'])
        ); 
        $result = date_diff($date_2, $txn_date);
        return [
            'y' => $result -> y,
            'm' => $result -> m,
            'd' => $result -> d,
        ];
    }

    /**
     * This method will format the number and return the formatted string.
     * @param number 
     * @return string
     */
    public static function number_format(float $number, int $precision = 2): string {
        return number_format(
            self::round($number, 2),
            $precision, 
            '.', 
            ','
        );
    }

    /**
     * This method will merge pdf and return merge pdf instance.
     * @param filenames
     * @return FPDF_Merge Object 
     */
    public static function merge_pdfs(array $filenames): FPDF_Merge {
        $merge_pdf = new FPDF_Merge();
        foreach($filenames as $filename) $merge_pdf -> add(TEMP_DIR. $filename);

        // Return Merge PDF Object
        return $merge_pdf;
    }

    /**
     * This method will build store address.
     * @param store_id
     * @return array 
     */
    public static function build_store_address(int $store_id) : array {
        return [
            'company_name' => StoreDetails::STORE_DETAILS[$store_id]['address']['name'],
            'company_address_line_1' => StoreDetails::STORE_DETAILS[$store_id]['address']['street1'],
            'company_address_line_2' => StoreDetails::STORE_DETAILS[$store_id]['address']['city'].', '. StoreDetails::STORE_DETAILS[$store_id]['address']['province']. ', '. StoreDetails::STORE_DETAILS[$store_id]['address']['postal_code'],
            'company_address_line_3' => StoreDetails::STORE_DETAILS[$store_id]['address']['country'],
            'company_tel' => StoreDetails::STORE_DETAILS[$store_id]['address']['tel'],
            'company_fax' => StoreDetails::STORE_DETAILS[$store_id]['address']['fax'],
        ];
    }

    /**
     * This method will remove whitespace characters between the words, and append only a single space between.
     * @param text 
     * @return string 
     */
    public static function remove_whitespace_between_words(string $text): string {
        $words = explode(' ', $text);
        $new_text = '';
        foreach($words as $word) {
            // Remove any whitespace characters
            $word = trim($word);
            if(!isset($word[0])) continue;
            $new_text .= $word. ' ';
        }
        return rtrim($new_text);
    }

    /**
     * This method will sanitize values.
     * @param obj
     * @return array
     */
    public static function sanitize_values(array $obj) : array {
        $keys = array_keys($obj);

        // Fill null for empty values.
        foreach($keys as $key) {
            if(isset($obj[$key]) && gettype($obj[$key]) === 'array') continue;
            else if(gettype($obj[$key]) === 'string') {
                if(!isset($obj[$key][0])) $obj[$key] = '';
                else $obj[$key] = self::remove_whitespace_between_words($obj[$key]);
            }
        }

        // Trim all fields before processing
        foreach ($keys as $key) {
            if(isset($obj[$key]) && gettype($obj[$key]) !== 'array') $obj[$key] = strip_tags(trim($obj[$key]));
        }

        return $obj;
    }

    /**
     * This method will generate token.
     * @param length
     * @return string
     */
    public static function generate_token(int $length): string {
        return bin2hex(random_bytes($length));
    }

    /**
     * This method will delete files.
     * @param filenames
     * @return void
     */
    public static function delete_files(array $filenames): void {
        foreach($filenames as $filename) {
            $path_to_file = TEMP_DIR . $filename;
            if(file_exists($path_to_file)) {
                // Delete File
                register_shutdown_function('unlink', $path_to_file);
            }
        }
    }

    /**
     * This method will get current UTC Timestamp.
     * @return int
     */
    public static function get_current_utc_unix_timestamp(): int {
        return date_timestamp_get(date_create('now', new DateTimeZone('UTC')));
    }

    /**
     * This method will get UTC Timestamp from Date Format.
     * @param utc_str_timestamp
     * @return int 
     */
    public static function get_utc_unix_timestamp_from_utc_str_timestamp(string $utc_str_timestamp): int {
        $date = date_create($utc_str_timestamp, new DateTimeZone('UTC'));
        return date_timestamp_get($date);
    }

    /**
     * This method will return UTC str timestamp from UTC unix timestamp.
     * @param unix_timestamp 
     * @return string
     */
    public static function get_utc_str_timestamp_from_utc_unix_timestamp(int $unix_timestamp): string {
        date_default_timezone_set('UTC');
        return date('Y-m-d H:i:s', $unix_timestamp);
    }

    /**
     * This method will convert UTC timestamp to Local Timestamp.
     * @param utc_unix_timestamp 
     * @param store_id
     * @return string
     */
    public static function convert_to_local_timestamp_from_utc_unix_timestamp(int $utc_unix_timestamp, int $store_id): string {
        date_default_timezone_set('UTC');
        $date_created = date_create();
        $date = date_timestamp_set($date_created, $utc_unix_timestamp);
        $date = date_format($date, 'Y-m-d H:i:s');
        return Utils::get_local_timestamp($date, $store_id);
    }

    /**
     * This method will convert UTC timestamp to LocalTime.
     * @param utc_str_timestamp
     * @param store_id
     * @return string
     */
    public static function convert_utc_str_timestamp_to_localtime(string $utc_str_timestamp, int $store_id): string {
        date_default_timezone_set('UTC');
        $date_created = date_create($utc_str_timestamp);
        $date_timestamp = date_timestamp_get($date_created);
        $date = date_timestamp_set($date_created, $date_timestamp);
        $date = date_format($date, 'Y-m-d H:i:s');
        return Utils::get_local_timestamp($date, $store_id);
    }

    /**
     * This method will format date for front end.
     * @param date
     */
    public static function format_date_for_front_end(string $date): string {
        return "$date 12:00:00";
    }

    /**
     * This method will format for transactions by type for Receipt and Customer Statement.
     * @param transactions
     * @return array
     */
    public static function format_for_transaction_by_type(array $transactions): array {
        // Format Transactions
        $format_transactions = [];

        // Txn Type Keys
        $txn_type_keys = array_keys($transactions);

        foreach($txn_type_keys as $type) {
            $txns = $transactions[$type];
            foreach($txns as $txn) {
                $format_transactions[]= ['id' => $txn, 'type' => $type];
            }
        }

        return $format_transactions;
    }

    /**
     * This method will calculate COGS Margin. 
     * @param selling_price
     * @param buying_cost
     * @return float
     */
    public static function calculateCOGSMargin(float $selling_price, float $buying_cost): float {
        $x = $selling_price / abs($buying_cost);
        if ($x >= 1) $x = $x - 1;
        return Utils::round($x * 100, 2);
    } 

    /**
     * This method will calculate Profit Margin.
     * @param selling_price
     * @param buying_cost 
     * @return float
     */
    public static function calculateProfitMargin(float $selling_price, float $buying_cost): float {
        $gross_margin = $selling_price - abs($buying_cost);
        $gross_margin = $gross_margin / $selling_price;
        return Utils::round($gross_margin * 100, 2);
    }

    /**
     * This method will format to human readable date.
     * @param date
     * @return string
     */
    public static function format_to_human_readable_date(string $date): string {
        return date_format(date_create($date), 'd, F Y');
    }

    /**
     * This method will send the file to browser for download.
     * @param filepath
     */
    public static function user_download(string $filepath): void {
        if(file_exists($filepath)) {
            header('Content-Description: File Transfer'); 
            header('Content-Type: application/octet-stream'); 
            header('Content-Disposition: attachment; filename="'.basename($filepath).'"'); 
            header('Expires: 0'); 
            header('Cache-Control: must-revalidate'); 
            header('Pragma: public'); 
            header('Content-Length: ' . filesize($filepath)); 
    
            // Flush system output buffer 
            flush();  
            readfile($filepath); 

            // Delete File
            register_shutdown_function('unlink', $filepath);
        }
        else http_response_code(404);
        exit;
    }
}
?>