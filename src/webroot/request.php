<?php
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
