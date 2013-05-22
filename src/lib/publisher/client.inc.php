<?php
class AddressMachinePublisherClient {

    static public function Publish($json_contents) {

        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
        );

        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, ADDRESSMACHINE_PUBLICATION_PUBLISH_URL);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handle, CURLOPT_USERPWD, ADDRESSMACHINE_PUBLICATION_USERNAME.':'.ADDRESSMACHINE_PUBLICATION_PASSWORD);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $json_contents);

        $response = curl_exec($handle);
        //print ".....\n";
        //print $response;
        //print "\n.....\n";
        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        return $response;
        
    }

    static public function UnPublish($json_contents) {

        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
        );

        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, ADDRESSMACHINE_PUBLICATION_UNPUBLISH_URL);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handle, CURLOPT_USERPWD, ADDRESSMACHINE_PUBLICATION_USERNAME.':'.ADDRESSMACHINE_PUBLICATION_PASSWORD);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $json_contents);

        $response = curl_exec($handle);
        //print ".....\n";
        //print $response;
        //print "\n.....\n";
        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        return $response;
 

    }

    // TODO
    static public function Revoke($address) {
        return false;
    }
}
