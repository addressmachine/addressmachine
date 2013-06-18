<?php
/*
This script turns commands from the web UI into emails to be sent to the relevant add@ and delete@ addresses.
It does not directly add or remove anything to or from the database - this has to be done by the firewalled bot box, which is the only box that has the GPG private key needed to sign addresses.
*/
$email = $_POST['email'];
$bitcoin = $_POST['bitcoin'];
$email_toggle = $_POST['email_toggle'];

if (!$email || !$bitcoin) {
    print 'Params NG';
    exit;
}

$action = ( $email_toggle == 'link' ) ? 'add' : 'delete';
$ok = mail( $action.'@addressmachine.com', 'form', $bitcoin, 'Reply-To: '.$email);
print $ok ? 'OK' : 'NG';
exit;
