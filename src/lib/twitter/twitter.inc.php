<?php
require_once('/usr/share/php/OAuth.php');

class AddressMachineTwitterCommand {

    var $text;
    var $parameter;
    var $id; // ID of the status
    var $user_id;
    var $screen_name;

    var $action;

    var $availableActionClasses = array(
        'GIVE'   => 'AddressMachineGiveAction', // lookup or create
        'LOOKUP' => 'AddressMachineLookupAction', // lookup only
        'ADD'    => 'AddressMachineAddAction', // register a new address
        'DELETE' => 'AddressMachineDeleteAction', // delete an address
        'REVOKE' => 'AddressMachineRevokeAction', // mark an address revoked
        'DIE'    => 'AddressMachineDieAction', // delete everything we have on this user
    );

    function execute() {
        
        if (!$this->parse()) {
            print "Error: could not parse";            
        }

            print "continuing with action {$this->action}";

        if (!$handler = $this->action()) {
            print "error: no handler for action ";
            return false;
        }

        return $handler->execute();

    }

    function parse() {

        $bits = explode(' ', $this->text);

        // There should be 2 or 3 bits in a valid message:
        // Our address from the mention, then:
        // action parameter
        // ...or
        // parameter
        if (count($bits) <= 1) {
            print "too few bits";
            return false;
        }

        if (count($bits) > 3) {
            print "too many bits";
            return false;
        }

        if ($bits[0] != '@'.ADDRESSMACHINE_SCREEN_NAME) {
            print "first bit mismtach";
            return false;
        }

        // See if the first word is an action.
        // If they tell us what to do, just pass the next parameter to the relevant class
        // ...and let it figure out if it's valid.
        if (isset($this->availableActionClasses[strtoupper($bits[1])])) {
            $this->action = strtoupper($bits[1]);
            $this->parameter = $bits[2];
            return true;
        }

        // They didn't tell us what to do specifically, so see if we can guess.

        // Messaging just a @twittername is a 'GIVE'
        if (preg_match('/@[A-Za-z0-9_]{1,15}/', $bits[1])) {
            // Just a twitter handle. Default to GIVE.
            $this->action = 'GIVE';
            $this->parameter = $bits[1];
            return true;
        }

        // Messaging just a Bitcoin address is an 'ADD'
        if (Bitcoin::checkAddress($bits[1])) {
            $this->action = 'ADD';
            $this->parameter = $bits[1];
            return true;
        }

        // Don't know what you were trying to do, give up.
        return false;
    
    }

    // return a subclass of AddressMachineAction
    function action() {

        if (is_null($this->action)) {
            return null;
        }
        if (!$action = $this->action) {
            return null;
        }

        $availableActionClasses = $this->availableActionClasses;
        if (!isset($availableActionClasses[$action])) {
            return null; 
        }

        $cls = $availableActionClasses[$action];
        $obj = new $cls();
        $obj->screen_name = $this->screen_name;
        $obj->user_id = $this->user_id;
        $obj->parameter = $this->parameter;
        $obj->id = $this->id;

        return $obj;

    }

}

// 
// Abstract class for actions (things the user asks us to do,like add an address
// ...followed by a class for each action.
//

abstract class AddressMachineAction {

    var $text;
    var $parameter;
    var $id; // ID of the status
    var $user_id;
    var $screen_name;

    public function tweetResponse($text) {
        print "trying to tweet $text";

        if (!$this->screen_name) {
            print "no screen namme";
            return false;
        }

        if ($text == '') {
            return false;
        }

        $text = '@'.$this->screen_name.' '.$text;
        print "trying to tweet text: $text";

        $tweet = new AddressMachineTwitterUpdate();
        $tweet->in_reply_to_status_id = $this->id;
        $tweet->text = $text;
        return $tweet->send();

    }

    // Over-ride this to make your class actually do something.
    abstract public function execute();

}

class AddressMachineGiveAction extends AddressMachineAction {

    public function execute() {
        print "TODO: do actual lookup";
        $text = 'test';
        return $this->tweetResponse($text);
    }

}

class AddressMachineAddAction extends AddressMachineAction {

    public function execute() {
        
        $addr = $this->parameter;
        if ($addr == '') {
            print "Missing parameter";
            return false;
        }

        if (!Bitcoin::checkAddress($addr)) {
            print "Address $addr not like a real address";
            return false;
        }

        print "TODO: do actual add";
        $text = 'test';
        return $this->tweetResponse($text);

    }

}



////////////////////////////////////////////////

class AddressMachineTwitterUpdate {

    var $user_id;
    var $text;
    var $in_reply_to_status_id; 

    public function send() {

        $tweet = $this->text;

        if ($tweet == '') {
            print "error: no text, giving up";
            exit;
        }

        $consumer = new OAuthConsumer(ADDRESSMACHINE_TWITTER_CONSUMER_KEY, ADDRESSMACHINE_TWITTER_CONSUMER_SECRET, NULL);
        $acc_token = new OAuthToken(ADDRESSMACHINE_TWITTER_ACCESS_TOKEN, ADDRESSMACHINE_TWITTER_ACCESS_TOKEN_SECRET);
        $sig_method = new OAuthSignatureMethod_HMAC_SHA1();

        $options = array(
            'status' => $tweet, 
            'trim_user' => 'true', 
            'in_reply_to_status_id' => $this->in_reply_to_status_id
        );

        $twitter_req = OAuthRequest::from_consumer_and_token($consumer, $acc_token, 'POST', ADDRESSMACHINE_TWITTER_UPDATE_STATUS_URL, $options);
        $twitter_req->sign_request($sig_method, $consumer, $acc_token);

        $context = stream_context_create(array(
        'http' => array(
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => $twitter_req->to_postdata()
        )
        ));
        $result = file_get_contents(ADDRESSMACHINE_TWITTER_UPDATE_STATUS_URL, false, $context);
        var_dump($result);

    }

}


