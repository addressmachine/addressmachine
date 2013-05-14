<?php
require_once(dirname(__FILE__).'/../../../config.php');
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

if (!$key->write_file($filename, $json)) {
    //syslog(LOG_WARNING, "----end publish create failed---");
    exit;
}

print $filename;

//syslog(LOG_WARNING, "----end publish---");
