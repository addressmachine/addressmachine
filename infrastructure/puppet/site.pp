node your_dev_box {

    # We can develop with everything on one box.

    include bot_box

    include publisher_website
    # include static_website

}

node your_bot_host {

    include bot_box

}

node your_publisher_host {

    include publisher_website

}

node your_static_website_host {

    include static_website

}

class publisher_website {

    package { "apache2": ensure => installed }
    package { "php5": ensure => installed } 

}

class bot_box {

    # This was probably already there or we wouldn't have got this manifest yet...
    package { "git-core": ensure => installed } 

    # Likewise this, for obvious reasons...
    package { "puppet": ensure => installed } 

    # To run the app.
    package { "php5-cli": ensure => installed } 
 
    # Needed by the twitter bot.
    package { "liboauth-php": ensure => installed } 

    # For GPG signing.
    package { "php-crypt-gpg": ensure => installed } 

    # To send signed addresses to the publishing server.
    package { "php5-curl": ensure => installed } 

    # To connect to the imap server and fetch email
    package { "php5-imap": ensure => installed } 

    # To handle email. 
    # Note that you'll also need to put the SendGrid authentication details in here.
    # We'll do that manually after the package has been installed.
    # See INSTALL.txt for the specific lines to add.
    package { "postfix": ensure => installed } 

    # This may be useful to check mail can be sent from the box
    package { "mailutils": ensure => installed } 

}

class static_website {

    # NGINX would do fine here too, we're not doing anything fancy.
    package { "apache2": ensure => installed }

}
