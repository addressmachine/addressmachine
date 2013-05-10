<?php
define('ADDRESSMACHINE_IS_DEVEL_ENVIRONMENT', 1);

require_once(dirname(__FILE__).'/../config.php');

require_once(ADDRESSMACHINE_LIB_ROOT_EXTERNAL.'/simpletest/autorun.php');

require_once(ADDRESSMACHINE_LIB_ROOT_EXTERNAL.'/bitcoin-php/src/bitcoin.inc');

require_once(ADDRESSMACHINE_LIB_ROOT.'/twitter/twitter.inc.php');
require_once(ADDRESSMACHINE_LIB_ROOT.'/address/address.inc.php');

class AddressMachineAddressTest extends UnitTestCase {

    function testGPGSignatures() {

        $payload = new stdClass();
        $payload->address = '12G3vYbNmJWpxUbNmvzdLb7mySGvSvz68Z';

        $sig = AddressMachineBitcoinKey::PayloadSignature($payload);
        //var_dump($sig);


    }

    function testTwitterIdentity() {

        $id = AddressMachineTwitterIdentity::ForIdentifier('edmundedgar');

        $addresses = $id->userBitcoinKeys();

        $this->assertTrue(is_array($addresses));
        $this->assertEqual(count($addresses), 0, 'No addresses for user at start of test');

        // If there's something left over from previous broken test runs, delete.
        foreach($addresses as $addr) {
            $this->assertTrue($addr->delete(), 'Delete OK');
        }

        $key = $id->addUserBitcoinKeyByAddress('12G3vYbNmJWpxUbNmvzdLb7mySGvSvz68Z');

        $this->assertNotNull($key);
        $this->assertIsA($key, 'AddressMachineBitcoinKey');

        $this->assertTrue($key->isSignatureValid());

        $this->assertTrue($key->delete());
        $this->assertFalse($key->delete());

    }

}
