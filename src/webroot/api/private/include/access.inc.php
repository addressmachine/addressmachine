<?php
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="Restricted API"');
    header('HTTP/1.0 401 Unauthorized');
    exit;
} 

if ( !isset($_SERVER['PHP_AUTH_PW']) ) {
    header('HTTP/1.0 401 Unauthorized');
    exit;
}

if ( $_SERVER['PHP_AUTH_USER'] != ADDRESSMACHINE_PUBLICATION_USERNAME ) {
    header('HTTP/1.0 401 Unauthorized');
    exit;
}

if ( $_SERVER['PHP_AUTH_PW'] != ADDRESSMACHINE_PUBLICATION_PASSWORD ) {
    header('HTTP/1.0 401 Unauthorized');
    exit;
}
