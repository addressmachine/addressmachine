<?php
// An identity represents something people may know you by, like a Twitter handle or and email address.
// We'll call plain text of the handle (as opposed to the object representing it) an "identifier".
abstract class AddressMachineIdentity {

    var $service;
    var $identifier;

    abstract static public function ForIdentifier($i);

    function pathToSignedTempFiles() {
        
        if ($this->identifier == '') {
            syslog(LOG_WARNING, "Cannot get pathToSignedTempFiles - identifier missing");
            return null;
        }

        return AddressMachinePaymentKey::PathToSignedFiles($this->identifier, null, 'temp', $this->service, 'bitcoin');

    }

    function pathToSignedUserFiles() {
        
        if ($this->identifier == '') {
            syslog(LOG_WARNING, "Cannot get pathToSignedUserFiles - identifier missing");
            return null;
        }

        return AddressMachinePaymentKey::PathToSignedFiles($this->identifier, null, 'user', $this->service, 'bitcoin');

    }

    // We keep two kinds of keys (Bitcoin addresses):  
    // User keys are registered by the user.
    // Temp keys are generated by us, in the hope that the user give us an address to pay to later.

    function userBitcoinKeys() {

        return $this->bitcoinKeysForType(ADDRESSMACHINE_KEY_TYPE_USER);

    }

    function tempBitcoinKeys() {
        
        return $this->bitcoinKeysForType(ADDRESSMACHINE_KEY_TYPE_TEMP);

    }

    function bitcoinKeysForType($keytype) {

        if (!$keytype) {
            syslog(LOG_WARNING, "Cannot get bitcoinKeysForType - type missing");
            return null;
        }

        if (!$identifier = $this->identifier) {
            syslog(LOG_WARNING, "Cannot get bitcoinKeysForType - identifier missing");
            return null;
        }

        if (!$service = $this->service) {
            syslog(LOG_WARNING, "Cannot get bitcoinKeysForType - service missing");
            return null;
        }

        if (!$dir = AddressMachinePaymentKey::PathToSignedFiles($identifier, null, $service, $keytype, 'bitcoin')) {
            syslog(LOG_WARNING, "Cannot get bitcoinKeysForType - could not get path to signed files");
            return null;
        }

        if (!file_exists($dir)) {
            syslog(LOG_DEBUG, "Directory $dir does not exist, bitcoinKeysForType returning an empty array.");
            return array();
        }

        if (!$handle = opendir($dir)) {
            syslog(LOG_WARNING, "Directory $dir exists but could not be read, bitcoinKeysForType returning null.");
            return null;
        }

        $ret = array();

        while (false !== ($entry = readdir($handle))) {

            if ($entry == '.') {
                continue;    
            }

            if ($entry == '..') {
                continue;
            }

            $key = new AddressMachinePaymentKey();
            $key->address = $entry;
            $key->identifier = $this->identifier;
            $key->service = $this->service;
            $key->keytype = $keytype;
            $key->paymenttype = 'bitcoin';
            $ret[] = $key;

            syslog(LOG_DEBUG, "Found address entry for $entry .");

        }

        closedir($handle);

        return $ret;

    }

    function allBitcoinKeys() {

        return array_merge((array)$this->userBitcoinKeys(), (array)$this->tempBitcoinKeys());

    }

    function addTempBitcoinKeyByAddress($addr) {
        
        return $this->addBitcoinKeyByAddressAndKeyType($addr, ADDRESSMACHINE_KEY_TYPE_TEMP);
        
    }

    function tempBitcoinKeyCreateIfNecessary() {

        $keys = $this->tempBitcoinKeys();

        if (count($keys) == 0) {
            return $this->createTempBitcoinKey();
        }

        return $keys[0];

    }

    function createTempBitcoinKey() {

    }

    function tempBitcoinKeyForAddress($addr) {

        return $this->bitcoinKeyForAddressAndKeyType($addr, ADDRESSMACHINE_KEY_TYPE_TEMP);

    }

    function addUserBitcoinKeyByAddress($addr) {

        return $this->addBitcoinKeyByAddressAndKeyType($addr, ADDRESSMACHINE_KEY_TYPE_USER);
        
    }

    function userBitcoinKeyForAddress($addr) {

        return $this->bitcoinKeyForAddressAndKeyType($addr, ADDRESSMACHINE_KEY_TYPE_USER);

    }


    function addBitcoinKeyByAddressAndKeyType($addr, $keytype) {

        if (!$this->identifier) {
            return false;
        }
        
        $key = new AddressMachinePaymentKey();
        $key->identifier = $this->identifier;
        $key->address = $addr;
        $key->keytype = ADDRESSMACHINE_KEY_TYPE_USER;
        $key->service = $this->service;
        $key->paymenttype = 'bitcoin';

        if ($key->create()) {
            return $key;
        }

        return null;

    }

    function bitcoinKeyForAddressAndKeyType($addr, $keytype) {

        if (!$this->identifier) {
            return false;
        }
        
        $key = new AddressMachinePaymentKey();
        $key->identifier = $this->identifier;
        $key->address = $addr;
        $key->keytype = ADDRESSMACHINE_KEY_TYPE_USER;
        $key->service = $this->service;
        $key->paymenttype = 'bitcoin';

        if ($key->exists()) {
            return $key;
        }

        return null;

    }

}

class AddressMachineTwitterIdentity extends AddressMachineIdentity {

    static public function ForIdentifier($id) {
        $i = new AddressMachineTwitterIdentity();
        $i->service = 'twitter';
        $i->identifier = $id;
        return $i;
    }

}

class AddressMachineEmailIdentity extends AddressMachineIdentity {

    static public function ForIdentifier($id) {
        $i = new AddressMachineEmailIdentity();
        $i->service = 'email';
        $i->identifier = $id;
        return $i;
    }


}

// Internally we call what is commonly called an "address" a key.
// This allows us to differentiate it from the address, which is the hash of the public key.
// In practice we will mostly manage addresses.
class AddressMachinePaymentKey {

    var $address;
    var $identifier;
    var $identifierhash; // On the publisher server we use this because we don't know the unhashed identifier
    var $keytype;
    var $service;
    var $paymenttype;

    static public function ForStdClass($obj) {

        $key = new AddressMachinePaymentKey();
        $key->address = $obj->address;
        $key->identifierhash = $obj->identifierhash;
        $key->keytype = $obj->keytype;
        $key->service = $obj->service;
        $key->paymenttype = $obj->paymenttype;

        return $key;

    }

    function exists() {

        if (!$filename = $this->filename()) {
            syslog(LOG_DEBUG, "File {$this->filename()} does not exist.");
            return null;
        }

        return file_exists($filename);

    }

    function write_file($file, $contents) {

        if (!$handle = fopen($file, 'w')) {
                syslog(LOG_ERR, "Could not open file $file to write.");
                return false;
        }
        if (!flock($handle, 2)) {
            fclose($handle);
            syslog(LOG_ERR, "Could not lock file $file to write.");
            return false;
        }
        fputs($handle, $contents);
        fclose($handle);

        return true;

    }

    function create() {

        if (!$address = $this->address) {
            if (!$publisher) {
                syslog(LOG_WARNING, "Cannot create file for address - address not set.");
                return false;
            }
        }

        if ($this->exists()) {
            syslog(LOG_WARNING, "File already exists, refusing to create.");
            return false;
        }

        if (!$path = $this->path()) {
            syslog(LOG_WARNING, "Creation failed, could not get path to place we create the file.");
            return false;
        }

        // If we don't already have a directory for this, create it.
        if (!file_exists($path)) {
            
            if (!mkdir( $path, ADDRESSMACHINE_ADDRESS_DIR_CREATION_MODE, true ) ) {

                // Check again in case it failed because another process made it in the meantime
                if (!file_exists($path)) {
                    syslog(LOG_ERR, "Directory creation failed for path $path.");
                    return false;
                }

            }

        }

        $file = $path.'/'.$address;
        $contents = $this->toJSON();

        if (!$this->write_file($file, $contents)) {
            syslog(LOG_ERR, "Failed to write file $file.");
            return false;
        }

        if (!AddressMachinePublisherClient::Publish($contents, $file)) {
            syslog(LOG_WARNING, "Publishing $file failed");
        }

        syslog(LOG_INFO, "Wrote new address $file.");

        return true;

    }

    function delete() {

        if (!$file = $this->filename()) {
            syslog(LOG_ERR, "Cannot get filename for deletion.");
            return false;
        }

        if (!file_exists($file)) {
            syslog(LOG_WARNING, "Cannot delete file $file - does not exist.");
            return false;
        }

    
        if (!$this->delete_file($file)) {
            syslog(LOG_ERR, "Could not delete file $file.");
            return false;
        }

        syslog(LOG_INFO, "Deleted file $file");

        return true;

    }

    function delete_file($file) {

        return @unlink($file);

    }

    function toJSON() {

        $obj = new stdClass();
        $payload = new stdClass(); // Wrap the data in a separate payload object. The only other thing we have should be the signature.
        $payload->address = $this->address;    
        $payload->keytype = $this->keytype;    
        $payload->service = $this->service;    
        $payload->paymenttype = $this->paymenttype;    
        $payload->identifierhash = AddressMachinePaymentKey::IdentifierHash($this->identifier);
        $obj->gpg_signature = AddressMachinePaymentKey::PayloadSignature($payload);
        $obj->payload = $payload;
        
        return json_encode($obj);

    }

    function path($publisher = false) {

        if (!$address = $this->address) {
            syslog(LOG_WARNING, "No address, can't make filename.");
            return null;
        }

        if (!$path = AddressMachinePaymentKey::PathToSignedFiles($this->identifier, $this->identifierhash, $this->service, $this->keytype, $this->paymenttype, $publisher)) {
            syslog(LOG_WARNING, "Could not get path to signed files.");
            return null;
        }

        return $path;

    }

    function filename($publisher = false) {

        if (!$address = $this->address) {
            syslog(LOG_WARNING, "Could not make filename - address not set.");
            return null;
        }

        if (!$path= $this->path($publisher)) {
            syslog(LOG_WARNING, "Could not make filename - could not get path to signed files.");
            return null;
        }

        return $path.'/'.$address;

    }

    static function PayloadSignature($payload) {

        require_once 'Crypt/GPG.php'; // From PEAR - the puppet install file should install this on Ubuntu.

        $json = json_encode($payload);

        $gpg = new Crypt_GPG(array('homedir' => ADDRESSMACHINE_GPG_ROOT));
        $gpg->addSignKey('admin@addressmachine.com', '');
        $signature = $gpg->sign($json, Crypt_GPG::SIGN_MODE_CLEAR);

        return $signature;

    }

    function revoke() {

        return false;

    }

    function isSignatureValid() {

        if (!$filename = $this->filename()) {
            syslog(LOG_DEBUG, "File {$this->filename()} does not exist, cannot validate signature.");
            return false;
        }

        $contents = file_get_contents($filename);

        $json = json_decode($contents);
        $payload = $json->payload;
        $gpg_signature = $json->gpg_signature;

        syslog(LOG_WARNING, "TODO: Fix validation to work with just the public key");

        return $gpg_signature == AddressMachinePaymentKey::PayloadSignature($payload);

    }

    public static function IdentifierHash($identifier) {

        return sha1($identifier);

    }

    // The publisher flag allows us to use the same class to get information about where the file would live on our publishing server.
    // This will follow the same conventions and just have a different data root, although in theory it doesn't have to.
    public static function PathToSignedFiles($identifier = null, $identifierhash = null, $service, $keytype, $paymenttype, $publisher = false) {
        if (!$identifier && !$identifierhash) {
            syslog(LOG_WARNING, "No identifier supplied when trying to get path to signed files.");
            return null;
        }

        if (!$keytype) {
            syslog(LOG_WARNING, "No keytype supplied when trying to get path to signed files.");
            return null;
        }

        if (!$service) {
            syslog(LOG_WARNING, "No service supplied when trying to get path to signed files.");
            return null;
        }

        $service_paths = array(
            'twitter' => $publisher ? ADDRESSMACHINE_PUBLICATION_DIRECTORY_TWITTER : ADDRESSMACHINE_DATA_DIRECTORY_TWITTER,
            'email'   => $publisher ? ADDRESSMACHINE_PUBLICATION_DIRECTORY_EMAIL : ADDRESSMACHINE_DATA_DIRECTORY_EMAIL,
        );

        if (!isset($service_paths[$service])) {
            syslog(LOG_WARNING, "No service path known for service type $service when trying to get path to signed files.");
            return null;
        }

        if (!$service_path = $service_paths[$service]) {
            syslog(LOG_WARNING, "Empty service path for service type $service when trying to get path to signed files.");
            return null;
        }

        $paymenttype_paths = array(
            'bitcoin'  => 'bitcoin',
            'litecoin' => 'litecoin',
        );

        if (!$paymenttype_path  = $paymenttype_paths[$paymenttype]) {
            syslog(LOG_WARNING, "Payment type {$paymenttype} not known, no path for it.");
            return null;
        }
        
        if ($identifierhash) {
            if ($identifier) {
                if ($identifierhash != AddressMachinePaymentKey::IdentifierHash($identifier)) {
                    syslog(LOG_WARNING, "Identifier $identifier and identifier hash $identifierhash both set, but don't match");
                    return null;
                }
            }
        }

        if (!$identifierhash && !$identifierhash = AddressMachinePaymentKey::IdentifierHash($identifier)) {
            syslog(LOG_WARNING, "Could not make hash for identifier $identifier when trying to get path to signed files.");
            return null;
        }

        return $service_path.'/'.$paymenttype_path.'/'.$keytype.'/'.$identifierhash;

    }

}
