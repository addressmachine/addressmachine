<?php
require_once(dirname(__FILE__).'/../../../config.publication.php');

// Print not-authorized headers and exit unless auth details ok
// You may want to remove this and set the check it at the web server level instead.
require_once(dirname(__FILE__).'/include/access.inc.php');

require_once(ADDRESSMACHINE_LIB_ROOT.'/address/address.inc.php');

//syslog(LOG_WARNING, "----in publish---");

$json = file_get_contents('php://input');
$input = json_decode( $json ); 

$key = AddressMachinePaymentKey::ForStdClass($input->payload); 
$filename = $key->filename($publisher = true);
/*
if (!$key->isSignatureValid()) {
    print "NG";
    exit;
}
*/

if (!$key->delete_file($filename)) {
    //syslog(LOG_WARNING, "----end publish create failed---");
    exit;
}

print $filename;

//syslog(LOG_WARNING, "----end publish---");
