<?php
print "\n\n\n";
print "Starting test at ".date('Y-m-d H:i:s')."\n";
define('ADDRESSMACHINE_IS_DEVEL_ENVIRONMENT', 1);

require_once(dirname(__FILE__).'/../config.php');

require_once(ADDRESSMACHINE_LIB_ROOT_EXTERNAL.'/simpletest/autorun.php');

require_once(ADDRESSMACHINE_LIB_ROOT_EXTERNAL.'/bitcoin-php/src/bitcoin.inc');

require_once(ADDRESSMACHINE_LIB_ROOT.'/twitter/twitter.inc.php');
require_once(ADDRESSMACHINE_LIB_ROOT.'/address/address.inc.php');
require_once(ADDRESSMACHINE_LIB_ROOT.'/publisher/client.inc.php');
require_once(ADDRESSMACHINE_LIB_ROOT.'/publisher/server.inc.php');

class AddressMachineAddressTest extends UnitTestCase {

    function testPublisherWebService() {
        
        // Upload
        $key = new AddressMachinePaymentKey();
        $key->address = '12G3vYbNmJWpxUbNmvzdLb7mySGvSvz68Z';
        $key->service = 'twitter';
        $key->identifier = '@exampletwitteruserthatistoolongtobearealtwitteruser';
        $key->paymenttype = 'bitcoin';
        $key->keytype = ADDRESSMACHINE_KEY_TYPE_USER;
        $contents = $key->toJson();
        //var_dump($contents);

        $upload_result = AddressMachinePublisherClient::Publish($contents);
        $this->assertTrue($upload_result);
        $filename = $key->filename(true);
        $this->assertTrue(file_exists($filename));

        $delete_result = AddressMachinePublisherClient::UnPublish($contents);
        $this->assertTrue($delete_result);

        $delete_result = AddressMachinePublisherClient::UnPublish($contents);
        $this->assertFalse($delete_result, 'UnPublish returns false on second unpublish');
        // TODO: Handle failures properly

        //var_dump($upload_result);
        return;


        

        // Download
        $published_path = ADDRESSMACHINE_PUBLICATION_DATA_DIRECTORY.'/data/addresses/twitter/bitcoin/user/8aaaefb7fabbVwFo2gU';

        $id = AddressMachineTwitterIdentity::ForIdentifier('@edmundedgar');
        $addresses = $id->userBitcoinKeys();
        $this->assertTrue(is_array($addresses));
        $this->assertEqual(count($addresses), 0, 'No addresses for user at start of test');

        // If there's something left over from previous broken test runs, delete.
        foreach($addresses as $addr) {
            $this->assertTrue($addr->delete(), 'Delete OK');
        }

        $key = $id->addUserBitcoinKeyByAddress('12G3vYbNmJWpxUbNmvzdLb7mySGvSvz68Z');

        $this->assertNotNull($key);
        $this->assertIsA($key, 'AddressMachinePaymentKey');

        $filename = $key->fileName();

        $this->assertTrue($key->isSignatureValid());
        $this->assertTrue($key->delete());

    }

    function testGPGSignatures() {

        $payload = new stdClass();
        $payload->address = '12G3vYbNmJWpxUbNmvzdLb7mySGvSvz68Z';

        $sig = AddressMachinePaymentKey::PayloadSignature($payload);
        //var_dump($sig);


    }

    function testTwitterIdentity() {

        $id = AddressMachineTwitterIdentity::ForIdentifier('@edmundedgar');

        $addresses = $id->userBitcoinKeys();

        $this->assertTrue(is_array($addresses));
        $this->assertEqual(count($addresses), 0, 'No addresses for user at start of test');

        // If there's something left over from previous broken test runs, delete.
        foreach($addresses as $addr) {
            $this->assertTrue($addr->delete(), 'Delete OK');
        }

        $key = $id->addUserBitcoinKeyByAddress('12G3vYbNmJWpxUbNmvzdLb7mySGvSvz68Z');

        $this->assertNotNull($key);
        $this->assertIsA($key, 'AddressMachinePaymentKey');

        $this->assertTrue($key->isSignatureValid());

        $addresses = $id->userBitcoinKeys();
        $this->assertEqual(count($addresses), 1, '1 addresses for user after creation');

        // Check the file exists on the publication server.
        // This test will only pass if the two are on the same machine.
        // ...otherwise we'd have to fetch it with wget.

        // @edmundedgar data should end up here:
        $published_path = ADDRESSMACHINE_PUBLICATION_DATA_DIRECTORY.'/data/addresses/twitter/bitcoin/user/8aaaefb7fabbVwFo2gU';
        //$this->assertTrue(file_exists($published_path), 'File has been published to publishing server');


        $this->assertTrue($key->delete());
        $this->assertFalse($key->delete());

    }

    function testTwitterCommands() {

        // delete any old leftovers from previous tests
        $id = AddressMachineTwitterIdentity::ForIdentifier('@edmundedgar');
        $addresses = $id->userBitcoinKeys();
        if (count($addresses)) { 
            foreach($addresses as $addr) {
                $this->assertTrue($addr->delete(), 'Delete OK');
            }
        }

        // Look up a user who doesn't have an address yet.
        $cmd = new AddressMachineTwitterCommand();
        $cmd->screen_name = 'thecatboris';
        $cmd->user_id = 562680317; // Shouldn't matter
        $cmd->id = rand(1000000,999999999999999);
        $cmd->text = '@addressmachine oink oink oink';
        $response = $cmd->execute();
        $this->assertIsA($response, 'AddressMachineTwitterUpdate');
        //$this->assertEqual($response->text, '@thecatboris @edmundedgar has not registered a Bitcoin address yet. Tweet "@addressmachine @edmundedgar TEMP" to make a temporary one.', 'Lookup for non-existent address produces error message suggesting using TEMP');
        $this->assertEqual($response->text, '@thecatboris Sorry, I could not understand that tweet.', 'Rubbish message produces error message.');


        // Look up a user who doesn't have an address yet.
        $cmd = new AddressMachineTwitterCommand();
        $cmd->screen_name = 'thecatboris';
        $cmd->user_id = 562680317; // Shouldn't matter
        $cmd->id = rand(1000000,999999999999999);
        $cmd->text = '@edmundedgar oink oink oink';
        $response = $cmd->execute();
        $this->assertNull($response, 'Tweet to someone else produces null response');


        // Look up a user who doesn't have an address yet.
        $cmd = new AddressMachineTwitterCommand();
        $cmd->screen_name = 'thecatboris';
        $cmd->user_id = 562680317; // Shouldn't matter
        $cmd->id = rand(1000000,999999999999999);
        $cmd->text = '@addressmachine LOOKUP @edmundedgar';
        $response = $cmd->execute();
        $this->assertIsA($response, 'AddressMachineTwitterUpdate');
        //$this->assertEqual($response->text, '@thecatboris @edmundedgar has not registered a Bitcoin address yet. Tweet "@addressmachine @edmundedgar TEMP" to make a temporary one.', 'Lookup for non-existent address produces error message suggesting using TEMP');
        $this->assertEqual($response->text, '@thecatboris @edmundedgar has not added a Bitcoin address yet. Ask them to tweet one to @addressmachine.', 'Lookup for non-existent address produces error message.');

        // Register an address for the user.
        $cmd = new AddressMachineTwitterCommand();
        $cmd->screen_name = 'edmundedgar';
        $cmd->user_id = 13682; // Shouldn't matter
        $cmd->id = rand(1000000,999999999999999);
        $cmd->text = '@addressmachine ADD 12G3vYbNmJWpxUbNmvzdLb7mySGvSvz68Z';
        $response = $cmd->execute();
        $this->assertIsA($response, 'AddressMachineTwitterUpdate');
        $this->assertEqual($response->text, '@edmundedgar I have added the address 12G3vYbNmJWpxUbNmvzdLb7mySGvSvz68Z for you.');

        $id = AddressMachineTwitterIdentity::ForIdentifier('@edmundedgar');
        $addresses = $id->userBitcoinKeys();
        $this->assertEqual(count($addresses), 1, '@edmundedgar should have 1 address after registering it. Found '.count($addresses));

        // Repeat the lookup, this time it should give us an address.
        $cmd = new AddressMachineTwitterCommand();
        $cmd->screen_name = 'thecatboris';
        $cmd->user_id = 562680317; // Shouldn't matter
        $cmd->id = rand(1000000,999999999999999);
        $cmd->text = '@addressmachine LOOKUP @edmundedgar';
        $response = $cmd->execute();
        $this->assertIsA($response, 'AddressMachineTwitterUpdate');
        $this->assertEqual($response->text, '@thecatboris You can pay @edmundedgar at 12G3vYbNmJWpxUbNmvzdLb7mySGvSvz68Z');

        // Delete the address.
        $cmd = new AddressMachineTwitterCommand();
        $cmd->screen_name = 'edmundedgar';
        $cmd->user_id = 13682; // Shouldn't matter
        $cmd->id = rand(1000000,999999999999999);
        $cmd->text = '@addressmachine DELETE 12G3vYbNmJWpxUbNmvzdLb7mySGvSvz68Z';
        $response = $cmd->execute();
        $this->assertIsA($response, 'AddressMachineTwitterUpdate');
        $this->assertEqual($response->text, '@edmundedgar I have deleted the address 12G3vYbNmJWpxUbNmvzdLb7mySGvSvz68Z for you.');

        // Look up a user who doesn't have an address yet.
        $cmd = new AddressMachineTwitterCommand();
        $cmd->screen_name = 'thecatboris';
        $cmd->user_id = 562680317; // Shouldn't matter
        $cmd->id = rand(1000000,999999999999999);
        $cmd->text = '@addressmachine LOOKUP @edmundedgar';
        $response = $cmd->execute();
        $this->assertIsA($response, 'AddressMachineTwitterUpdate');
        //$this->assertEqual($response->text, '@thecatboris @edmundedgar has not registered a Bitcoin address yet. Tweet "@addressmachine @edmundedgar TEMP" to make a temporary one.', 'After deletion lookup for non-existent address produces error message suggesting using TEMP');
        $this->assertEqual($response->text, '@thecatboris @edmundedgar has not added a Bitcoin address yet. Ask them to tweet one to @addressmachine.', 'After deletion lookup for non-existent address produces error message.');

        /*
        // Look up a user who doesn't have an address yet.
        $cmd = new AddressMachineTwitterCommand();
        $cmd->screen_name = 'thecatboris';
        $cmd->user_id = 562680317; // Shouldn't matter
        $cmd->id = rand(1000000,999999999999999);
        $cmd->text = '@addressmachine TEMP @edmundedgar';
        $response = $cmd->execute();
        $this->assertIsA($response, 'AddressMachineTwitterUpdate');
        //$this->assertEqual($response->text, '@thecatboris @edmundedgar ', 'After deletion lookup for non-existent address produces error message suggesting using TEMP (2)');
        */

        return;

    }


    function testEmailCommands() {



    }

}
