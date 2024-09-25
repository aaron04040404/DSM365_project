<?php
/**
 * PHPMailer for OS system script
 * 
 * ---- Made by Nick Feng.
 * 
 * argv[0] is the php file name.
 * argv[1] settings string in JSON.
 * argv[2] email contents in JSON.
 * 
 */
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
//use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * JSON structure:
 * 
 * (DON'T forget use escapeshellarg() before use system() to linux command line.)
 * 
 * escapeshellarg( json_encode([
 *     'debug'       => 0,                         // Enable verbose debug output
 *     'is_smtp'     => true,                      // Set mailer to use SMTP
 *     'host'        => 'smtp.gmail.com',          // Specify main and backup SMTP servers
 *     'port'        => 587,                       // TCP port to connect to
 *     'smtp_auth'   => true,                      // Enable SMTP authentication
 *     'user_name'   => 'dev.dynascan@gmail.com',  // SMTP username
 *     'user_pw'     => 'fgnick170391',            // SMTP password
 *     'smtp_secure' => 'tls',                     // Enable TLS encryption, `ssl` also accepted
 *     'is_html'     => true
 * ]));
 * @var array $settings
 */
$settings = json_decode( $argv[1], true ); // get input 1 arguments

/**
 * JSON structure:
 * (DON'T forget use escapeshellarg() before use system() to linux command line.)
 * [
 *      'email address' => [
 *          'subject' => 'your subject',
 *          'body'    => 'your body string( in HTML)',
 *          ......
 *      ]
 * ]
 * @var array $data
 */
$data = json_decode( $argv[2], true ); // get input 2 arguments

try {
    $datetime = date_format((new DateTime(null, new DateTimeZone('UTC'))), 'Y-m-d H:i:s');
    printf('[' . $datetime . ']: php mailer process start...');
} catch ( \Exception $e) {
    printf ( '*** DataTime Object generates on failure!!!! ***' );
    exit();
}
$mailer = new PHPMailer( true );
try {
    // Server default settings
    $mailer->SMTPDebug  = $settings['debug'];         // Enable verbose debug output
    $mailer->Host       = $settings['host'];          // Specify main and backup SMTP servers
    $mailer->Hostname   = $settings['hostname'];      // Specify mail host name to HELO/EHLO.
    $mailer->SMTPAuth   = $settings['smtp_auth'];     // Enable SMTP authentication
    $mailer->Username   = $settings['user_name'];     // SMTP username
    $mailer->Password   = $settings['user_pw'];       // SMTP password
    $mailer->SMTPSecure = $settings['smtp_secure'];   // Enable TLS encryption, `ssl` also accepted
    $mailer->Port       = $settings['port'];          // TCP port to connect to
    $mailer->CharSet    = PHPMailer::CHARSET_UTF8;
    $mailer->isHTML( $settings['is_html'] );
    if ( $settings['is_smtp'] ) {
        $mailer->isSMTP();    // Set mailer to use SMTP
    }
    
    $mailer->setFrom( $settings['user_name'] );
    foreach ( $data as $addr => $form ) {
        if ( filter_var( $addr, FILTER_VALIDATE_EMAIL ) === false ) {
            printf ( '[' . $datetime . ']: [' . $addr . '] is not in address format. ({' . $form['subject'] . '} Skipped)' . PHP_EOL );
            continue;
        }
        // Recipients
        $mailer->addAddress( $addr );
        // Content
        $mailer->Subject = $form['subject'];
        $mailer->Body    = $form['body'];
        if ( $mailer->send() === false ) {
            printf ( '[' . $datetime . ']:[' . $addr . '] {' . $form['subject'] . '} sent fail!' . PHP_EOL );
        } else {
            printf ( '[' . $datetime . ']:[' . $addr . '] {' . $form['subject'] . '} sent success!' . PHP_EOL );
        }
        $mailer->ClearAllRecipients(); // Remove all address you want to send
        $mailer->ClearAttachments();   // Remove all attachments
    }
} catch ( Exception $e ) {
    printf ( '[' . $datetime . ']: ' . $mailer->ErrorInfo . PHP_EOL );
}
