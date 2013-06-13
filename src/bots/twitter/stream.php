<?php

define('ADDRESSMACHINE_IS_DEVEL_ENVIRONMENT', 1);

require_once(dirname(__FILE__).'/../../config.php');

require_once(ADDRESSMACHINE_LIB_ROOT_EXTERNAL.'/phirehose/lib/Phirehose.php');
require_once(ADDRESSMACHINE_LIB_ROOT_EXTERNAL.'/phirehose/lib/UserstreamPhirehose.php');
require_once(ADDRESSMACHINE_LIB_ROOT_EXTERNAL.'/bitcoin-php/src/bitcoin.inc');

require_once(ADDRESSMACHINE_LIB_ROOT.'/twitter/twitter.inc.php');
require_once(ADDRESSMACHINE_LIB_ROOT.'/address/address.inc.php');
require_once(ADDRESSMACHINE_LIB_ROOT.'/publisher/client.inc.php');

/**
 * Barebones example of using UserstreamPhirehose.
 */
class MyUserConsumer extends UserstreamPhirehose 
{
  /**
   * First response looks like this:
   *    $data=array('friends'=>array(123,2334,9876));
   *
   * Each tweet of your friends looks like:
   *   [id] => 1011234124121
   *   [text] =>  (the tweet)
   *   [user] => array( the user who tweeted )
   *   [entities] => array ( urls, etc. )
   *
   * Every 30 seconds we get the keep-alive message, where $status is empty.
   *
   * When the user adds a friend we get one of these:
   *    [event] => follow
   *    [source] => Array(   my user   )
   *    [created_at] => Tue May 24 13:02:25 +0000 2011
   *    [target] => Array  (the user now being followed)
   *
   * @param string $status
   */
  public function enqueueStatus($status)
  {
    /*
     * In this simple example, we will just display to STDOUT rather than enqueue.
     * NOTE: You should NOT be processing tweets at this point in a real application, instead they
     *  should be being enqueued and processed asyncronously from the collection process. 
     */
     print "\n";
     print "\n";
     print "\n";
     print "NEW MESSAGES!!!\n";
    $data = json_decode($status, true);
    var_dump($data);

    if ( !isset($data['entities']['user_mentions']) || (count($data['entities']['user_mentions']) == 0) ) {
        print "No user mentions, skipping";
        return false;
    }

    $first_mention = $data['entities']['user_mentions'][0];
    if ($first_mention['screen_name'] != ADDRESSMACHINE_SCREEN_NAME) {
        return $this->handleError('I ('.ADDRESSMACHINE_SCREEN_NAME.') am not mentioned first - '.$first_mention['screen_name']. " is."); 
    }
    if ($first_mention['indices'][0] != 0) {
        $this->handleError('I am not mentioned first');      
    }

    $cmd = new AddressMachineTwitterCommand();
    $cmd->screen_name = $data['user']['screen_name'];
    $cmd->user_id = $data['user']['id'];
    $cmd->text = $data['text'];
    $cmd->id = $data['id'];

    if ($response = $cmd->execute()) {
        $response->send();
    }

    //echo date("Y-m-d H:i:s (").strlen($status)."):".print_r($data,true)."\n";
  }

  public function handleError($str) {
      print $str;
      return true;
  }

}

//These are the application key and secret
//You can create an application, and then get this info, from https://dev.twitter.com/apps
//(They are under OAuth Settings, called "Consumer key" and "Consumer secret")
define('TWITTER_CONSUMER_KEY', ADDRESSMACHINE_TWITTER_CONSUMER_KEY);
define('TWITTER_CONSUMER_SECRET', ADDRESSMACHINE_TWITTER_CONSUMER_SECRET);

//These are the user's token and secret
//You can get this from https://dev.twitter.com/apps, under the "Your access token"
//section for your app.
define('OAUTH_TOKEN', ADDRESSMACHINE_TWITTER_ACCESS_TOKEN);
define('OAUTH_SECRET', ADDRESSMACHINE_TWITTER_ACCESS_TOKEN_SECRET);

// Start streaming
$sc = new MyUserConsumer(OAUTH_TOKEN, OAUTH_SECRET);
$sc->consume();
