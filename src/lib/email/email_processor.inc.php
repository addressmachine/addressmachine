<?php
/*
This is a base class for email processors.
It needs to be extended by a specific email processor, stored in the email_processors directory.
*/
class email_processor {

    // The following hold the raw data from the email.
    var $_subject;
    var $_from_address;
    var $_html_body;
    var $_plain_body;
    var $_charset;
    var $_attachments = array();

    // The following are filled during parsing. 
    var $_images= array();
    var $_userid = null;
    var $_prepared_body;
    var $_prepared_subject;

    var $_importer;

    var $_print_not_send;

    // Return the user ID
    function get_user_id() {
        return $this->_userid;
    }

    function set_charset($c) {
        $this->_charset = $c;
    }

    function get_charset() {
        return $this->_charset;
    }

    function get_plain_body() {
        return $this->_plain_body;
    }

    function set_plain_body($b) {
        $this->_plain_body = $b;
    }

    // Return the message body
    function get_html_body() {
        return $this->_html_body;
    }

    function set_html_body($m) {
        $this->_html_body = $m;
    }

    function set_subject($s) {
        $this->_subject = $s;
    }

    function set_from_address($e) {
        $this->_from_address = $e;
    }

    function get_subject() {
        return $this->_subject;
    }

    static function verbose_output($verbose, $msg) {

        if ($verbose) {
            print $msg."\n";
        }

    }

    static function read_mail($cfg, $verbose, $daemon, $handler = null, $nodelete = false, $cron = false, $printnotsend = false) {

        $statuses = array(
            'result' => array(),
            'errors' => array(),
            'messages' => array(
            )
        );

        $giveup = false;
        $msgcount = 0;

        // In daemon mode, the handler is kept alive between calls to this function with its connection open.
        email_processor::verbose_output($verbose, "Trying to get connection...");
        $handler = !is_null($handler) ? $handler : new imap_message_handler();

        if (!$giveup) {
            email_processor::verbose_output($verbose, "Connecting...");
            //var_dump($cfg);
            if (!$handler->connect($cfg->mail_box_settings, $cfg->mail_user_name, $cfg->mail_user_pass)) {
                email_processor::verbose_output($verbose, "Connection failed.");
                $statuses['errors']["-2"] = "Connection failed. Could not fetch email.";
                $giveup = true;
            //var_dump($handler);
            }
        }


        if (!$giveup) {
            if (!$msgcount = $handler->count()) {
                // In daemon mode, keep the connection open, and return the handler object so we can reuse it.
                if ($daemon) {
                    return $handler;
                }
                $handler->close();
                email_processor::verbose_output($verbose, "No messages found.");
                $statuses['result']["1"] = "No messages.";
                $giveup = true;
            }
        }

        if (!$giveup) {

            email_processor::verbose_output($verbose, "Got $msgcount messages.");

            if ($msgcount > 0)  {  

                if ($cfg->mail_maxcheck && $msgcount > $cfg->mail_maxcheck) {
                    $msgcount = $cfg->mail_maxcheck;
                }

                for ($mid = 1; $mid <= $msgcount; $mid++) {

                    $statuses['messages'] = array();

                    email_processor::verbose_output($verbose, "Considering loading message with ID :$mid:");

                    // Load the header first so that we can check what we need to know before downloading the rest. 
                    if (!$handler->load_header($mid)) {
                        $statuses['messages'][] = array( 
                            'errors' => array('-101' => 'Could not load header') 
                        );
                        continue;
                    }

                    $subject = $handler->get_subject();
                    $fromaddress = $handler->get_from_address();

                    $toaddress = $handler->get_to_address();
                    /*
                    if (!strtolower($toaddress) == strtolower($cfg->mail_email_address)) {
                        print "not for us: $toaddress";
                        // Not for us.
                        continue;
                    }
                    */

                    $info = array(
                        'subject' => $subject,
                        'fromaddress' => $fromaddress
                    );

                    $size_in_bytes = $handler->get_size_in_bytes();
                    if ($cfg->mail_maxsize && $size_in_bytes > $cfg->mail_maxsize) {
                        $statuses['messages'][] = array( 
                            'errors' => array('-101' => 'Could not load header.'),
                            'info' => $info
                        );
                        continue;
                    }
                    email_processor::verbose_output($verbose, "Message size :$size_in_bytes: small enough - continuing.");

                    // TODO: Separate load_header and load_body so we don't load the whole thing if it's too big.
                    if (!$handler->load($mid)) {
                        $statuses['messages'][] = array( 
                            'errors' => array('-102' => 'Could not load.'),
                            'info' => $info
                        );

                        continue;
                    }
                    email_processor::verbose_output($verbose, "Loaded message...");

                    $htmlmsg = $handler->get_html_message();;
                    $plainmsg = $handler->get_plain_message();; 
                    $charset = $handler->get_charset();
                    $attachments = $handler->get_attachments();

                    email_processor::verbose_output($verbose, "Trying processor...");

                    /*
                    foreach($attachments as $attachment_filename => $attachment_body) {
                    }
                    */

                    email_processor::verbose_output($verbose, "Preparing message...");
                    $cmd = new AddressMachineEmailCommand();
                    $cmd->service_email = $toaddress;
                    $cmd->user_email = $fromaddress;
                    $cmd->raw_body = $plainmsg ? $plainmsg : $htmlmsg;
                    //var_dump($cmd);
                    $response = $cmd->execute();

                    if ($nodelete) {
                        email_processor::verbose_output($verbose, "Skipping deletion of message $mid because you asked for nodelete.");
                    } else {
                        email_processor::verbose_output($verbose, "Deleting message $mid.");
                        if (!$handler->delete($mid)) {
                            email_processor::verbose_output($verbose, "Deletion of message $mid failed.");
                        }
                    }

                    if ($response) {
                        // Dump the email content instead of sending.
                        // This allows us to test without filling up our sendgrid limits
                        if ($printnotsend) {
                            var_dump($response);
                        } else {
                            $response->send();
                        }
                    }
                    //imap_delete($mailbox, $mid);

                    //print "skipping user mail content";

                }

            }

            $handler->expunge();

        }

        // In daemon mode, keep the handler with its connection alive and return it so it can be used again next time.
        if ($daemon) {
            return $handler;
        }

        $handler->close();

        return true;

    }

    static function status_text($statuses) {

        $str = '';

        if (count($statuses['errors'])) {
            $str .= 'Fetching email failed:'."\n";
            $str .= implode("\n", $statuses['errors'])."\n";
        }

        $str .= count($statuses['messages']).' messages processed.';

        return $str;

    }

}
