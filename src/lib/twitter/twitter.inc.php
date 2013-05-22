<?php
require_once('/usr/share/php/OAuth.php');

class AddressMachineTwitterCommand {

    var $text;
    var $parameter;
    var $id; // ID of the status
    var $user_id;
    var $screen_name;

    var $action;

    var $availableTwitterActionClasses = array(
        //'TEMP'   => 'AddressMachineTempTwitterAction', // lookup or create temporary
        'LOOKUP' => 'AddressMachineLookupTwitterAction', // lookup only
        'ADD'    => 'AddressMachineAddTwitterAction', // register a new address
        'DELETE' => 'AddressMachineDeleteTwitterAction', // delete an address
        'ERROR' => 'AddressMachineErrorTwitterAction', // parsing error etc
        // 'REVOKE' => 'AddressMachineRevokeTwitterAction', // mark an address revoked TODO
        'DIE'    => 'AddressMachineDieTwitterAction', // delete everything we have on this user
    );

    function execute() {
        
        $this->parse();

        //print "continuing with action {$this->action}";

        if (!$handler = $this->action()) {
            print "error: no handler for action ";
            return null;
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

        if ( ( count($bits) == 0 ) || ( $bits[0] != '@'.ADDRESSMACHINE_SCREEN_NAME ) ) {
            // Not for us, ignore without even making a response.
            return false;
        }

        if (count($bits) <= 1) {
            //print "too few bits";
            $this->action = 'ERROR';
            return false;
        }

        if (count($bits) > 3) {
            //print "too many bits";
            $this->action = 'ERROR';
            return false;
        }

        // See if the first word is an action.
        // If they tell us what to do, just pass the next parameter to the relevant class
        // ...and let it figure out if it's valid.
        if (isset($this->availableTwitterActionClasses[strtoupper($bits[1])])) {
            $this->action = strtoupper($bits[1]);
            $this->parameter = $bits[2];
            return true;
        }

        // They didn't tell us what to do specifically, so see if we can guess.

        // Messaging just a @twittername is a 'LOOKUP'
        if (preg_match('/@[A-Za-z0-9_]{1,15}/', $bits[1])) {
            // Just a twitter handle. Default to TEMP.
            $this->action = 'LOOKUP';
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
        $this->action = 'ERROR';
        return false;
    
    }

    // return a subclass of AddressMachineTwitterAction
    function action() {

        if (is_null($this->action)) {
            return null;
        }
        if (!$action = $this->action) {
            return null;
        }

        $availableTwitterActionClasses = $this->availableTwitterActionClasses;
        if (!isset($availableTwitterActionClasses[$action])) {
            return null; 
        }

        $cls = $availableTwitterActionClasses[$action];
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

abstract class AddressMachineTwitterAction {

    var $text;
    var $parameter;
    var $id; // ID of the status
    var $user_id;
    var $screen_name;

    public function response($text) {

        //print "trying to tweet $text";

        if (!$this->screen_name) {
            print "no screen namme";
            return false;
        }

        if ($text == '') {
            return false;
        }

        $text = '@'.$this->screen_name.' '.$text;

        $tweet = new AddressMachineTwitterUpdate();
        $tweet->in_reply_to_status_id = $this->id;
        $tweet->text = $text;
        return $tweet;

    }

    // Over-ride this to make your class actually do something.
    abstract public function execute();

}

class AddressMachineLookupTwitterAction extends AddressMachineTwitterAction {

    public function execute() {

        $identifier = $this->parameter;

        $id = AddressMachineTwitterIdentity::ForIdentifier($identifier);
        $keys = $id->userBitcoinKeys();

        $text = '';

        if (count($keys) == 0) {
            //$text = $identifier.' has not registered a Bitcoin address yet. Tweet "@addressmachine '.$identifier.' TEMP" to make a temporary one.';
            $text = $identifier.' has not added a Bitcoin address yet. Ask them to tweet one to @addressmachine.';
        }

        if (!$text) {
            // TODO: Think about the method of choosing between multiple keys.
            $key = $keys[0];
            $text = 'You can pay '.$identifier .' at '.$key->address;
        }

        return $this->response($text);

    }

}

class AddressMachineTempTwitterAction extends AddressMachineTwitterAction {

    public function execute() {
        print "TODO: do actual lookup, or create an address if it fails";
        $text = 'test';
        return $this->response($text);
    }

}

class AddressMachineAddTwitterAction extends AddressMachineTwitterAction {

    public function execute() {
        
        $text = '';

        $addr = $this->parameter;

        if ($addr == '') {
            $text = 'Please tweet @addressmachine ADD address'; 
        }

        if (!$text && !Bitcoin::checkAddress($addr)) {
            $text = $addr.' does not look like a real Bitcoin address.';
        }
    
        $id = null;
        if (!$text && !$id = AddressMachineTwitterIdentity::ForIdentifier('@'.$this->screen_name)) {
            $text = 'Sorry, something went wrong.';
        }

        if (!$text) {
            syslog(LOG_INFO, "Trying to add address $addr");
            if ( !$key = $id->addUserBitcoinKeyByAddress($addr)) {
                $text = 'Sorry, something went wrong.';
            } else {
                //$text = 'I have added the address $addr. You can delete it later with "@addressmachine DELETE '.$addr.'"';
                $text = 'I have added the address '.$addr.' for you.';
            }
            syslog(LOG_INFO, "Done trying to add address $addr");
        }


        return $this->response($text);

    }

}

class AddressMachineDeleteTwitterAction extends AddressMachineTwitterAction {

    public function execute() {
        
        $text = '';

        $addr = $this->parameter;

        if ($addr == '') {
            $text = 'Please tweet "@addressmachine DELETE address", substituting "address" for a Bitcoin address.'; 
        }

        if (!$text && !Bitcoin::checkAddress($addr)) {
            $text = $addr.' does not look like a real Bitcoin address.';
        }
    
        $id = null;
        if (!$text && !$id = AddressMachineTwitterIdentity::ForIdentifier('@'.$this->screen_name)) {
            $text = 'Sorry, something went wrong.';
        }

        if (!$text && !$key = $id->userBitcoinKeyForAddress($addr)) {
            $text = 'The address '.$addr.' was not registered in the first place.';
        }

        if ( !$text && !$key->delete() ) {
            $text = 'Sorry, something went wrong, I could not delete the address'.$addr;
        }

        if (!$text) {
            //$text = 'I have added the address $addr. You can delete it later with "@addressmachine DELETE '.$addr.'"';
            $text = 'I have deleted the address '.$addr.' for you.';
        }

        return $this->response($text);

    }

}

// NB This only gets called if we have a resonable attempt at a message to us.
class AddressMachineErrorTwitterAction extends AddressMachineTwitterAction {

    public function execute() {
        
        if ($this->screen_name) {
            return $this->response('Sorry, I could not understand that tweet.');
        }

        return null;

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

    }

}


