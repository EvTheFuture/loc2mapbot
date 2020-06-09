# loc2mapbot
Telegram bot to collect and display Live Locations in IITC

__Requirements__
* A Working web server with PHP and SSL support

* A MySQL or MariaDB Database

* PHP support in CLI

* PHP Curl (https://www.php.net/manual/en/class.curlfile.php)

* A valid certificate for your web server


__**** VERY IMPORTANT ****__

<u>YOU MUST INITILIZE ALL CONFIGURATION BY FOLLOWING THESE STEPS</u>

1. Configure your web server to serve PHP files in ./www/

2. PHP version must have CURLFile support

3. Make sure the convert command is installed from ImageMagick

4. Create a MariaDb/Mysql database and a valid database user

5. Create a Telegram Bot in Telegram with help of the bot @BotFather

6. Run the ./init.sh script to install all required components and setup the configuration

7. Start the bot ./loc2mapbot.php

__OPTIONAL:__

In order to specify valid admin chats, you will need a file called .data.json including the following

    {
	"last_update_id": 0,
        "admin_chats": [
            -11675,
            12717
        ]
    }

