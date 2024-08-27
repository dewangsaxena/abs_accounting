<?php 
/**
 * This module implements functionality to send email.
 * 
 * https://help.saleshandy.com/article/121-what-is-an-spf-and-how-to-setup
 * https://hostbrook.com/help/phpmailer-settings
 * https://medium.com/@djaho/how-to-create-dkim-keys-and-use-them-with-phpmailer-a6003449c718
 * https://dmarcly.com/tools/dkim-record-checker
 * https://dkimcore.org/tools/
 * https://www.mail-tester.com/
 * 
 */
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/third_party/PHPMailer/src/Exception.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/third_party/PHPMailer/src/PHPMailer.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/third_party/PHPMailer/src/SMTP.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/validate.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/store_details.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class Email {
    // Service Used
    private const SERVICE = 'postmarkapp';
    private const FROM_EMAIL = 'abs@abs.company';

    // Username
    private const USERNAME = [
        'google' => self::FROM_EMAIL,
        'sendgrid' => 'apikey',
        'postmarkapp' => 'PM-B-broadcast-bQWBIt6Eq51i-XmcSJFLn',
    ][self::SERVICE];

    // SendGrid
    private const APP_ACCESS_KEY = [
        'google' => 'dtfzftppvtyaaqua',
        'sendgrid' => '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',
        'postmarkapp' => 'jY5ocel379u_TwwZN0XJiNtZ-xYXsH6FQR9g',
    ][self::SERVICE];

    // Port 
    private const PORT = [
        'google' => 587,
        'sendgrid' => 587,
        'postmarkapp' => 587,
    ][self::SERVICE];

    // Host 
    private const HOST = [
        'google' => 'smtp.gmail.com',
        'sendgrid' => 'smtp.sendgrid.net',
        'postmarkapp' => 'smtp-broadcasts.postmarkapp.com',
    ][self::SERVICE];

    private const DEBUG_STATUS = false;

    /**
     * This method will send email.
     * 
     * @param subject The subject of the email.
     * @param recipient_email Recipient
     * @param recipient_name Name 
     * @param content The email Content.
     * @param path_to_attachment The local(server) path of any file send.
     * @param file_name The filename
     * @param store_id 
     * @param additional_email_addresses
     * @param is_html 
     * @param add_cc
     * @return array
     */
    public static function send(string $subject, string $recipient_email, string $recipient_name, string $content, string $path_to_attachment=null, string $file_name=null, int $store_id, ?string $additional_email_addresses=null, ?bool $is_html=false, ?bool $add_cc=true) : array {
        /* Return on Localhost */
        if(defined('DISABLE_EMAIL_ON_LOCALHOST')) return ['status' => true];
        try {
            $mail = new PHPMailer(self::DEBUG_STATUS);
            if(self::DEBUG_STATUS) $mail -> SMTPDebug = SMTP::DEBUG_SERVER;
            $mail -> isSMTP();
            $mail -> Host = self::HOST;
            $mail -> Port = self::PORT;
            $mail -> Mailer = 'smtp';
            $mail -> SMTPAuth = true;
            $mail -> SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail -> Username = self::USERNAME;
            $mail -> Password = self::APP_ACCESS_KEY;
            $mail -> IsHTML($is_html);
            $mail -> AddAddress($recipient_email, $recipient_name);

            // Add additional email addresses, if any.
            if(isset($additional_email_addresses)) {
                $email_ids = explode(',', $additional_email_addresses);
                foreach($email_ids as $email_id) {
                    if(Validate::is_email_id(trim($email_id))) $mail -> AddAddress(trim($email_id), $recipient_name);
                }
            }
            $mail -> SetFrom(self::FROM_EMAIL, StoreDetails::STORE_DETAILS[$store_id]['email']['from_name'][SYSTEM_INIT_MODE]);
            $mail -> Subject = $subject;
            $mail -> Body = $content;
            $mail -> Encoding = 'base64';

            // Add BCC 
            if(false && $add_cc) {
                $mail -> addBCC(
                    StoreDetails::STORE_DETAILS[$store_id]['email']['bcc'][SYSTEM_INIT_MODE], 
                    StoreDetails::STORE_DETAILS[$store_id]['address']['name'],
                );
            }
            
            /* Add Attachment */ 
            if($path_to_attachment !== null) {
                $mail -> AddAttachment($path_to_attachment, $file_name);
            }
            
            // Log Error
            $is_sent = $mail -> send() ? true : false;
            if($is_sent === false) {
                $err_msg = $mail -> ErrorInfo;
                throw new Exception($err_msg);
            }

            // All Successful
            return ['status' => $is_sent];
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }
}
?>