<?php
class AddressMachineEmailCommand {

    var $raw_body; // the raw body of the email
    var $text; // The text after we parse it, which should just be a command or a confirmation string
    var $parameter;
    var $user_email;
    var $service_email; // The service we arrived at, without the domain name

    // We will receive two emails per update action.
    // The first will be the instruction telling us what we should do.
    // This will have an is_confirmation value of false.
    // We will then send back a signed version of that command and ask the user to ping it back to us.
    // When we get it back, we will treat that as confirmed and act on it.

    // Lookup actions will just work without confirmation.
    var $is_confirmation;

    var $action;

    var $availableEmailActionClasses = array(
        //'TEMP'   => 'AddressMachineTempEmailAction', // lookup or create temporary
        'LOOKUP' => 'AddressMachineLookupEmailAction', // lookup only
        'ADD'    => 'AddressMachineAddEmailAction', // register a new address
        'DELETE' => 'AddressMachineDeleteEmailAction', // delete an address
        'ERROR' => 'AddressMachineErrorEmailAction', // parsing error etc
        // 'REVOKE' => 'AddressMachineRevokeEmailAction', // mark an address revoked TODO
        'DIE'    => 'AddressMachineDieEmailAction', // delete everything we have on this user
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

    public static function UnhashedConfirmationCommand($str, $user_email, $service_email) {

        $str = base64_decode($str);

        // TODO: Handle multiple addresses
        $bits = explode(" ", $str);
        $hash = array_pop($bits);
        $payload = join(" ", $bits);
        if (AddressMachineEmailCommand::CommandHash($payload, $user_email, $service_email) != $hash) {
            syslog(LOG_WARNING, "Hash mismatch in string $str");
            return '';
        }

        return $payload;

    }

    function HashedConfirmationCommand($str, $user_email, $service_email) {

        $sig = AddressMachineEmailCommand::CommandHash($str, $user_email, $service_email);
        $str .= ' '.$sig;
        return base64_encode($str);

    }

    public static function CommandHash($str, $user_email, $service_email) {

        // Tack the user email and service email onto the end...
        // ...so that the code only works for that mail address and that action.
        $str .= $user_email;
        $str .= $service_email;
        return hash_hmac( 'sha256', $str, ADDRESSMACHINE_EMAIL_CONFIRMATION_SECRET );

    }

    function isPrettyMuchLikeAnEmailAddress($email) {

        // An intentionally loose regex for matching email.
        // The aim here is to get what the user intended to be an email address
        // ...as opposed to a Twitter address or something.
        return preg_match('/^\S+@\S+\.\S+$/', $email);

    }

    function parse() {

        // Get the action from the address the email arrived at
        $verb = strtoupper($this->service_email);

        // If they tell us what to do, just pass the next parameter to the relevant class
        // ...and let it figure out if it's valid.
        if ( isset($this->availableEmailActionClasses[$verb]) ) {
            $this->action = $verb;
        } else {
            syslog(LOG_WARNING, "Could not find action for incoming email address ".$this->service_email);
            return false;
        }

        // TODO: We should be parsing out any HTML email or other such junk
        $this->text = $this->raw_body;

        // regex to detect valid base 64 characters
        $base_64_pattern = '[a-zA-Z0-9\/\r\n+]*={0,2}';

        $confirm_pattern = '/'.ADDRESSMACHINE_EMAIL_CONFIRMATION_STRING_PREFIX.'('.$base_64_pattern.')'.ADDRESSMACHINE_EMAIL_CONFIRMATION_STRING_SUFFIX.'/';

        if (preg_match($confirm_pattern, $this->text, $matches)) {
            // Throw away the rest and use the matching bit
            $this->text = AddressMachineEmailCommand::UnhashedConfirmationCommand($matches[1], $this->user_email, $this->service_email);
            $this->is_confirmation = true;
        }

        // TODO: Handle HTML email etc
        $bits = explode(' ', $this->text);

        // There should be 1 or 2 bits in a valid message:
        // action parameter
        // ...or
        // parameter

        if (count($bits) < 1) {
            //print "too few bits";
            $this->action = 'ERROR';
            return false;
        }

        if (count($bits) > 2) {
            //print "too many bits";
            $this->action = 'ERROR';
            return false;
        }

/*
print "...\n";
        print "setting paramter for raw body ".$this->raw_body;
        var_dump($bits);
print "...\n";
*/

        $this->parameter = $bits[0];
        return true;
    
    }

    // return a subclass of AddressMachineEmailAction
    function action() {

        if (is_null($this->action)) {
            return null;
        }
        if (!$action = $this->action) {
            return null;
        }

        $availableEmailActionClasses = $this->availableEmailActionClasses;
        if (!isset($availableEmailActionClasses[$action])) {
            return null; 
        }

        $cls = $availableEmailActionClasses[$action];
        $obj = new $cls();
        $obj->user_email = $this->user_email;
        $obj->service_email = $this->service_email;
        $obj->parameter = $this->parameter;
        $obj->is_confirmation = $this->is_confirmation;

        return $obj;

    }

}

// 
// Abstract class for actions (things the user asks us to do,like add an address
// ...followed by a class for each action.
//

abstract class AddressMachineEmailAction {

    var $text;
    var $parameter;
    var $user_email;
    var $service_email;
    var $is_confirmation;

    public function response($text) {

        //print "trying to tweet $text";

        if (!$this->user_email) {
            print "to address missing";
            return false;
        }

        if ($text == '') {
            return false;
        }

        $email = new AddressMachineEmailMessage();
        $email->service_email = $this->service_email;
        $email->user_email = $this->user_email;
        $email->text = $text;
        return $email;

    }

    // Over-ride this to make your class actually do something.
    abstract public function execute();

}

class AddressMachineLookupEmailAction extends AddressMachineEmailAction {

    public function execute() {

        $identifier = $this->parameter;

        $id = AddressMachineEmailIdentity::ForIdentifier($identifier);
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

class AddressMachineTempEmailAction extends AddressMachineEmailAction {

    public function execute() {
        print "TODO: do actual lookup, or create an address if it fails";
        $text = 'test';
        return $this->response($text);
    }

}

class AddressMachineAddEmailAction extends AddressMachineEmailAction {

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
        if (!$text && !$id = AddressMachineEmailIdentity::ForIdentifier($this->user_email)) {
            $text = 'Sorry, something went wrong.';
        }

        if (!$text) {

            if (!$this->is_confirmation) {

                $command = $this->parameter;
                $encodedCommand = AddressMachineEmailCommand::HashedConfirmationCommand($command, $this->user_email, $this->service_email);
                $encodedCommand = ADDRESSMACHINE_EMAIL_CONFIRMATION_STRING_PREFIX.$encodedCommand.ADDRESSMACHINE_EMAIL_CONFIRMATION_STRING_SUFFIX;

                $text .= "Someone, probably you, asked me to add the following address for you.\n";
                $text .= "\n";
                $text .= "To go ahead and add it, reply to this email with the following line intact:\n";
                $text .= "$encodedCommand\n";
                $text .= "\n";
                $text .= "If you didn't ask me to do this, or you've changed your mind, you can ignore this email.\n";
                $text .= ADDRESSMACHINE_EMAIL_FOOTER."\n";

                syslog(LOG_INFO, "Created response with confirmation command $encodedCommand");

                return $this->response($text);

            }

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

class AddressMachineDeleteEmailAction extends AddressMachineEmailAction {

    public function execute() {
        
        $text = '';

        $addr = $this->parameter;

        if ($addr == '') {
            $text = 'Please email me a Bitcoin address.'; 
        }

        if (!$text && !Bitcoin::checkAddress($addr)) {
            $text = $addr.' does not look like a real Bitcoin address.';
        }
    
        $id = null;
        if (!$text && !$id = AddressMachineEmailIdentity::ForIdentifier($this->user_email)) {
            $text = 'Sorry, something went wrong.';
        }

        if (!$text && !$key = $id->userBitcoinKeyForAddress($addr)) {
            $text = 'The address '.$addr.' was not registered in the first place.';
        }

        if (!$text && !$this->is_confirmation) {

            $command = $this->parameter;
            $encodedCommand = AddressMachineEmailCommand::HashedConfirmationCommand($command, $this->user_email, $this->service_email);
            $encodedCommand = ADDRESSMACHINE_EMAIL_CONFIRMATION_STRING_PREFIX.$encodedCommand.ADDRESSMACHINE_EMAIL_CONFIRMATION_STRING_SUFFIX;

            $text .= "Someone, probably you, asked me to delete the following address for you.\n";
            $text .= "\n";
            $text .= "To go ahead and delete it, reply to this email with the following line intact:\n";
            $text .= "$encodedCommand\n";
            $text .= "\n";
            $text .= "If you didn't ask me to do this, or you've changed your mind, you can ignore this email.\n";
            $text .= ADDRESSMACHINE_EMAIL_FOOTER."\n";

            syslog(LOG_INFO, "Created response with confirmation command $encodedCommand");

            return $this->response($text);

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
class AddressMachineErrorEmailAction extends AddressMachineEmailAction {

    public function execute() {
        
        if ($this->screen_name) {
            return $this->response('Sorry, I could not understand that tweet.');
        }

        return null;

    }

}


////////////////////////////////////////////////

class AddressMachineEmailMessage {

    var $text;
    var $service_email;
    var $user_email;

    public function send() {

        $tweet = $this->text;

        if ($tweet == '') {
            print "error: no text, giving up";
            exit;
        }

        $options = array(
            'status' => $tweet, 
            'trim_user' => 'true', 
            'in_reply_to_status_id' => $this->in_reply_to_status_id
        );

        //$twitter_req = OAuthRequest::from_consumer_and_token($consumer, $acc_token, 'POST', ADDRESSMACHINE_TWITTER_UPDATE_STATUS_URL, $options);
        //$twitter_req->sign_request($sig_method, $consumer, $acc_token);

        print "TODO: Send email";

    }

}
