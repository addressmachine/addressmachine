<?php

require_once dirname(__FILE__)."/../../config.php";

require_once(ADDRESSMACHINE_LIB_ROOT_EXTERNAL.'/bitcoin-php/src/bitcoin.inc');

require_once(ADDRESSMACHINE_LIB_ROOT.'/email/email.inc.php');
require_once(ADDRESSMACHINE_LIB_ROOT.'/address/address.inc.php');
require_once(ADDRESSMACHINE_LIB_ROOT.'/publisher/client.inc.php');

$CFG = new stdClass();
$CFG->mail_box_settings = ADDRESSMACHINE_EMAIL_IMAP_SETTINGS;
$CFG->mail_user_name = ADDRESSMACHINE_EMAIL_IMAP_USER;
$CFG->mail_user_pass = ADDRESSMACHINE_EMAIL_IMAP_PASSWORD;
$CFG->mail_maxcheck = ADDRESSMACHINE_EMAIL_IMAP_MAX_CHECK;
$CFG->mail_maxsize = ADDRESSMACHINE_EMAIL_IMAP_MAX_SIZE;

// Class for handling an imap message connection, and fetching and parsing emails one by one.
require_once(ADDRESSMACHINE_LIB_ROOT.'/email/imap_message_handler.inc.php');
require_once(ADDRESSMACHINE_LIB_ROOT.'/email/email_processor.inc.php');

// It will then need to find something to do with the email, like import it into the blog.
//require_once 'moodle_importer.php';

$verbose = in_array("-v", $argv);
$daemon = in_array("-d", $argv);
$nodelete = in_array("-n", $argv); // Useful when testing. 
$printnotsend = in_array("-p", $argv); // Useful when testing. 

if ($nodelete && $daemon) {
    echo "Refusing to run in daemon mode with nodelete specified, as this will spam you into oblivion.\n";
    exit;
}

if ($daemon) {

    $handler = null;
    while ($handler = email_processor::read_mail($CFG, $verbose, $daemon, $handler, $nodelete, false, $printnotsend)) {
        email_processor::verbose_output($verbose, "Handling run done, sleeping");
        sleep(2);
    }

} else {
    print "Reading mail\n";
    email_processor::read_mail($CFG, $verbose, $daemon, null, $nodelete, false, $printnotsend);

}

exit;
