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
        'TEMP'   => 'AddressMachineTempEmailAction', // lookup or create temporary
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

    public static function UnhashedConfirmationCommand($str, $user_email, $action) {

        $str = base64_decode($str);

        // TODO: Handle multiple addresses
        $bits = explode(" ", $str);
        $hash = array_pop($bits);
        if ($hash == '') {
            syslog(LOG_WARNING, "Hash was empty for command $str");
            return '';
        }

        $payload = join(" ", $bits);

        //var_dump($payload);
        if (AddressMachineEmailCommand::CommandHash($payload, $user_email, $action) != $hash) {
            syslog(LOG_WARNING, "Hash mismatch in string $str");
            return '';
        }

        //print "UnhashedConfirmationCommand returning :$payload:";

        return $payload;

    }

    function HashedConfirmationCommand($str, $user_email, $action) {

        $sig = AddressMachineEmailCommand::CommandHash($str, $user_email, $action);
        if ($sig == '') {
            return null;
        }

        $str .= ' '.$sig;
        return base64_encode($str);

    }

    public static function CommandHash($str, $user_email, $action) {

        // Tack the user email and service email onto the end...
        // ...so that the code only works for that mail address and that action.
        $str .= $user_email;
        $str .= $action;
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
        if (!$service_email = $this->service_email) {
            print "WARN: no service email";
            return false;
        }

        $service_email_user = ''; 
        if (preg_match('/^(.*?)\@.*$/', $service_email, $matches)) {
            $service_email_user = $matches[1];
        }

        $verb = strtoupper($service_email_user);

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

        if (in_array($this->action, array('ADD', 'DELETE'))){

            // Confirmation commands should contain the action , eg ADD, then the parameter, eg a Bitcoin address
            if (preg_match($confirm_pattern, $this->text, $matches)) {

                // Throw away the rest and use the matching bit
                $this->text = AddressMachineEmailCommand::UnhashedConfirmationCommand($matches[1], $this->user_email, $this->action);
                //var_dump($this->text);
                $this->is_confirmation = true;
                $bits = explode(" ", $this->text);
                $this->parameter = $bits[1];

            }

            // Look for a bitcoin address

            // TODO: In theory we should be able to handle multiple addresses in one go...
            $lines = explode("\n", $this->text);
            foreach($lines as $line) {
                $line = preg_replace( '/[^[:print:]]/', '',$line);
                if (preg_match('/(^1[1-9A-Za-z][^OIl]{20,40})/', $line, $matches)) {
                    //print "got address :".$matches[1].":\n";
                    $this->parameter = $matches[1];
                    break;
                }
            }


        } else if (in_array($this->action, array('LOOKUP', 'TEMP'))) {

            // Look for an email address

            // TODO: In theory we should be able to handle multiple addresses in one go...
            $lines = explode("\n", $this->text);
            foreach($lines as $line) {
                $line = preg_replace( '/[^[:print:]]/', '',$line);
                if (preg_match('/^([a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+)$/', $line, $matches)) {
                    //print "got address :".$matches[1].":\n";
                    $this->parameter = $matches[1];
                    break;
                }
            }

        }

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
        $obj->action = $this->action;
        $obj->text = $this->text;

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

        $identifier = $this->parameter;

        $id = AddressMachineEmailIdentity::ForIdentifier($identifier);
        $user_keys = $id->userBitcoinKeys();

        $text = '';

        // If they have a user key, refuse to return a temp one and give them the user one instead.
        if (!$text && ( count($user_keys) > 0 ) ) {
            // TODO: Think about the method of choosing between multiple keys.
            $key = $user_keys[0];

            $text =  "You or someone claiming to be you (".$this->user_email."), asked us to create a temporary Bitcoin address for $identifier so that you can send you some Bitcoins.\n";
            $text .= "\n";
            $text .= "\n";
            $text = 'We found that they had already registered a Bitcoin address with us, so we are going to give you that instead of creating a temporary one for them.'."\n";
            $text = 'You can pay '.$identifier .' at '.$user_key->address."\n";
            $text .= "\n";
            $text .= "\n";

            $text .= ADDRESSMACHINE_EMAIL_FOOTER."\n";



        }

        // Populate the key with a seed first, and try to DM the user with the seed.
        // Only save the key once we've sent the message to the user.
        // If the message fails we don't want anybody using the address.
        if (!$text && !$key = $id->seededTempKey() ) {
            $text =  "You or someone claiming to be you (".$this->user_email."), asked us to create a temporary Bitcoin address for $identifier so that you can send you some Bitcoins.\n";
            $text .= "\n";
            $text .= "\n";
            $text = 'Unfortunately something went wrong and I was unable to do it.'."\n";
            $text .= "\n";
            $text .= "\n";
            $text .= ADDRESSMACHINE_EMAIL_FOOTER."\n";
        }

        if (!$text && ( !$seed = $key->seed || !$address = $key->address) ) {
            $text =  "You or someone claiming to be you (".$this->user_email."), asked us to create a temporary Bitcoin address for $identifier so that you can send you some Bitcoins.\n";
            $text .= "\n";
            $text .= "\n";
            $text = 'Unfortunately something went wrong and I was unable to do it.'."\n";
            $text .= "\n";
            $text .= "\n";
            $text .= ADDRESSMACHINE_EMAIL_FOOTER."\n";
        }

        if (!$text) {
            
            $temp_text =  "Someone claiming to be ".$this->user_email.", asked to create a temporary Bitcoin address for you so that they can send you some Bitcoins.\n";
            $temp_text .= "\n";
            $temp_text .= 'I have made you an address at '.$key->address." and told them about it.\n";
            $temp_text .= 'You can see if they send you Bitcoins on this page:'."\n";
            $temp_text .= 'http://blockchain.info/address/'.$key->address."\n";
            $temp_text .= "\n";
            $temp_text .= "See the following page for information on how to access any Bitcoins they may send you.\n";
            $temp_text .= "http://electrum.org/tutorials.html#restoring-seed\n";
            $temp_text .= 'You will need the following secret "seed": '.$key->seed."\n";
            $temp_text .= "\n";
            $temp_text .= "Do not lose the seed, because we do not keep a copy of it after we send this email.\n";
            $temp_text .= "\n";
            $temp_text .= 'Since we created this ourselves and sent it over email, which isn\'t very secure, we recommend that you transfer the Bitcoins to another wallet.'."\n";
            $temp_text .= "\n";
            $temp_text .= ADDRESSMACHINE_EMAIL_FOOTER."\n";


            $email = new AddressMachineEmailMessage();
            $email->service_email = $this->service_email;
            $email->user_email = $identifier;
            $email->text = $temp_text;

            if ($email->send()) {

                $text =  "You or someone claiming to be you (".$this->user_email."), asked us to create a temporary Bitcoin address for $identifier so that you can send you some Bitcoins.\n";
                $text .= "\n";
                $text .= 'I have made an address for them at '.$key->address."\n";
                $text .= "\n";
                $text .= "I have emailed them the address and some secret information to access any Bitcoins you may send them.\n";
                $text .= "I cannot be certain that they will receive this message, and if they don't get it there will be no way to retrieve any bitcoins you send to that address. I do not keep their secret information after I have send the email.\n";
                $text .= "You should probably check with them before sending them a lot of Bitcoins.\n";
                $text .= "\n";
                $text .= ADDRESSMACHINE_EMAIL_FOOTER."\n";

            } else {

                $text = 'Sorry, I was unable to send a message to '.$identifier.' to give them a new wallet.';

            }
        }

        return $this->response($text);

    }

}

class AddressMachineAddEmailAction extends AddressMachineEmailAction {

    public function execute() {
        
        $text = '';

        $addr = $this->parameter;

        if ($addr == '') {
            $text = 'Address not found'; 
        }

        if (!$text && !Bitcoin::checkAddress($addr)) {
            $text = $addr.' does not look like a real Bitcoin address.';
        }
    
        $id = null;
        if (!$text && !$id = AddressMachineEmailIdentity::ForIdentifier($this->user_email)) {

                $text .= "Someone, probably you, asked me to add the following address for you:\n";
                $text .= "$this->parameter\n";
                $text .= "$this->user_email\n";
                $text .= "\n";
                $text .= "Unfortunately something went wrong and we weren't able to do it.";
                $text .= "\n";
                $text .= "If you didn't ask me to do this, or you've changed your mind, you can ignore this email.\n";
                $text .= "\n";
                $text .= "\n";
                $text .= ADDRESSMACHINE_EMAIL_FOOTER."\n";
        }

        // TODO: We should probably keep a record of this and only send you one of these emails
        // ...in case we end up with some kind of auto-responder loop or something.
        if (!$text && $id->userBitcoinKeyForAddress($this->parameter)) {
            
            $text .= "Someone, probably you, asked me to add the following address for you:\n";
            $text .= "$this->parameter\n";
            $text .= "$this->user_email\n";
            $text .= "\n";
            $text .= "It looks like this address is already in our database, so we're going to do nothing.\n";
            $text .= "\n";
            $text .= "If you want to remove the address, please email it to delete@addressmachine.com then reply to the confirmation mail.\n";
            $text .= "\n";
            $text .= "If you didn't ask me to do this, or you've changed your mind, you can ignore this email.\n";
            $text .= "\n";
            $text .= "\n";
            $text .= ADDRESSMACHINE_EMAIL_FOOTER."\n";

        }

        if (!$text) {

            if (!$this->is_confirmation) {

                $command = "ADD ".$this->parameter;
                $encodedCommand = AddressMachineEmailCommand::HashedConfirmationCommand($command, $this->user_email, $this->action);
                $encodedCommand = ADDRESSMACHINE_EMAIL_CONFIRMATION_STRING_PREFIX.$encodedCommand.ADDRESSMACHINE_EMAIL_CONFIRMATION_STRING_SUFFIX;

                $text .= "Someone, probably you, asked me to add the following address for you:\n";
                $text .= "$this->parameter\n";
                $text .= "$this->user_email\n";
                $text .= "\n";
                $text .= "To go ahead and add it, reply to this email with the following line intact:\n";
                $text .= "$encodedCommand\n";
                $text .= "\n";
                $text .= "If you didn't ask me to do this, or you've changed your mind, you can ignore this email.\n";
                $text .= "\n";
                $text .= "\n";
                $text .= ADDRESSMACHINE_EMAIL_FOOTER."\n";

                syslog(LOG_INFO, "Created response with confirmation command $encodedCommand");

                return $this->response($text);

            }

            syslog(LOG_INFO, "Trying to add address $addr");
            if ( !$key = $id->addUserBitcoinKeyByAddress($addr)) {
                $text = 'Sorry, something went wrong.';
            } else {
                //$text = 'I have added the address $addr. You can delete it later with "@addressmachine DELETE '.$addr.'"';
                $text = 'I have added the following address for you:';
                $text .= "\n";
                $text .= $this->parameter;
                $text .= "\n";
                $text .= "\n";
                $text .= 'It will be available to people who search for your email address on or website or through an application that uses our API.';
                $text .= "\n";
                $text .= "\n";
                $text .= 'You can delete it at any time by emailing it to delete@addressmachine.com then replying to the confirmation mail.';
                $text .= "\n";
                $text .= "\n";
                $text .= ADDRESSMACHINE_EMAIL_FOOTER."\n";
                $text .= "\n";


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
            $text = 'No record for '.$this->user_email.' was registered.';
        }

        if (!$text && !$key = $id->userBitcoinKeyForAddress($addr)) {
            $text = 'The address '.$addr.' was not registered in the first place.';
        }

        if (!$text && !$this->is_confirmation) {

            $command = "DELETE ".$this->parameter;
            $encodedCommand = AddressMachineEmailCommand::HashedConfirmationCommand($command, $this->user_email, $this->action);
            $encodedCommand = ADDRESSMACHINE_EMAIL_CONFIRMATION_STRING_PREFIX.$encodedCommand.ADDRESSMACHINE_EMAIL_CONFIRMATION_STRING_SUFFIX;

            $text .= "Someone, probably you, asked me to delete the following address for you;\n";
            $text .= "$addr\n";
            $text .= "$this->user_email\n";
            $text .= "\n";
            $text .= "\n";
            $text .= "To go ahead and delete it, reply to this email with the following line intact:\n";
            $text .= "$encodedCommand\n";
            $text .= "\n";
            $text .= "If you didn't ask me to do this, or you've changed your mind, you can ignore this email.\n";
            $text .= "\n";
            $text .= "\n";
            $text .= ADDRESSMACHINE_EMAIL_FOOTER."\n";

            syslog(LOG_INFO, "Created response with confirmation command $encodedCommand");

            return $this->response($text);

        }

        if ( !$text && !$key->delete() ) {

            syslog(LOG_WARNING, "Deletion failed for command $encodedCommand");

            $text = 'I got a request to delete the address '.$addr;
            $text .= "\n";
            $text .= "Unfortunately something went wrong and I was unable to do it.\n";
            $text .= "\n";
            $text .= "The problem has been logged and we'll be looking into what happened.\n";
            $text .= "\n";
            $text .= "\n";
            $text .= ADDRESSMACHINE_EMAIL_FOOTER."\n";

        }

        if (!$text) {
            //$text = 'I have added the address $addr. You can delete it later with "@addressmachine DELETE '.$addr.'"';
            $text = 'I have deleted the address '.$addr.' for you.';
            $text .= "\n";
            $text .= "The address will no longer be published on our website, but you may still receive payments from people who have already looked it up.\n";
            $text .= "\n";
            $text .= "If you want to add it again, you can email it to add@addressmachine.com or add it from our website.\n";
            $text .= "\n";
            $text .= ADDRESSMACHINE_EMAIL_FOOTER."\n";

        }

        return $this->response($text);

    }

}

// NB This only gets called if we have a resonable attempt at a message to us.
class AddressMachineErrorEmailAction extends AddressMachineEmailAction {

    public function execute() {
        
        if ($this->user_email) {
            return $this->response('Sorry, I could not understand that email.');
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

        $service_email = $this->service_email;

        $address_names = array(
            'add@addressmachine.com' => 'Address Machine (Add)',
            'delete@addressmachine.com' => 'Address Machine (Delete)',
            'lookup@addressmachine.com' => 'Address Machine (Lookup)',
            'temp@addressmachine.com' => 'Address Machine (Temporary Address)',
        );

        if (!isset($address_names[$service_email])) {
            return null;
        }

        $name = $address_names[$service_email];

        $txt = $this->text;

        if ($txt == '') {
            print "error: no text, giving up";
            exit;
        }

        //print "TODO: Send email\n";
        //var_dump($this->user_email);
        //var_dump($txt);

        $headers =  "From: {$name} <{$service_email}>" . "\r\n"; 
        $headers .= "Reply-To: {$name} <{$service_email}>" . "\r\n"; 

        return mail($this->user_email, "Address Machine Request", $txt, $headers);

        //$twitter_req = OAuthRequest::from_consumer_and_token($consumer, $acc_token, 'POST', ADDRESSMACHINE_TWITTER_UPDATE_STATUS_URL, $options);
        //$twitter_req->sign_request($sig_method, $consumer, $acc_token);


    }

}
