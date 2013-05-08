node beech {

    # Needed by the twitter bot.
    package { "liboauth-php": ensure => installed } 

    # For GPG signing
    package { "php-crypt-gpg": ensure => installed } 

}
