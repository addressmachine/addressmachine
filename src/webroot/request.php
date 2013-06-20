<?php
/*
This script turns commands from the web UI into emails to be sent to the relevant add@ and delete@ addresses.
It does not directly add or remove anything to or from the database - this has to be done by the firewalled bot box, which is the only box that has the GPG private key needed to sign addresses.
*/
$email = $_POST['email'];
$bitcoin = $_POST['bitcoin'];
$email_toggle = $_POST['email_toggle'];

$response = new stdClass();
$response->result = false;
$response->invalid_fields = array();

if (!preg_match('/(^1[1-9A-Za-z][^OIl]{20,40})/', $bitcoin)) {
    $response->invalid_fields[] = 'bitcoin';
}

if (!preg_match('/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/', $email)) {
    $response->invalid_fields[] = 'email';
}

$action = ( $email_toggle == 'link' ) ? 'add' : 'delete';

if ( count($response->invalid_fields) == 0 ) {
    $response->result = mail( $action.'@addressmachine.com', 'form', $bitcoin, 'Reply-To: '.$email);
}

print json_encode($response);

exit;
