#!/bin/sh
#
# This file is part of loc2mapbot (https://github.com/mukens/loc2mapbot)
# Copyright (c) 2020 Magnus Sandin
# 
# This program is free software: you can redistribute it and/or modify  
# it under the terms of the GNU General Public License as published by  
# the Free Software Foundation, version 3.
#
# This program is distributed in the hope that it will be useful, but 
# WITHOUT ANY WARRANTY; without even the implied warranty of 
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU 
# General Public License for more details.
#
# You should have received a copy of the GNU General Public License 
# along with this program. If not, see <http://www.gnu.org/licenses/>.
#

dir="`dirname \"$0\"`"
varsfile="$dir/.vars"

store_variables()
{
    out="";

    store()
    {
	if [ "x$2" != "x" ] ; then
	    out="${out}$1=\"$2\"\n"
	else
	    out="${out}$1=\"$3\"\n"
	fi
    }

    store "tag_salt" "$tag_salt" ""
    store "tag_telegram_api_token" "$tag_telegram_api_token" ""
    store "tag_log_file" "$tag_log_file" "bot.log"
    store "tag_dbuser" "$tag_dbuser" ""
    store "tag_dbpass" "$tag_dbpass" ""
    store "tag_dbname" "$tag_dbname" ""
    store "tag_dbhost" "$tag_dbhost" "localhost"
    store "tag_host" "$tag_host" ""
    store "tag_domain" "$tag_domain" ""

    echo "$out" > "$varsfile"
}

ask()
{
    unset a
    eval "c=\"\$$1\""

    while [ "x${a}" = "x" ] ; do
	echo -n "${2} [`eval "echo -n \\$$1"`]: "
	read a

	a="`echo "$a" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//'`"

	if [ "x${a}" = "x" ] ; then
	    if [ "x${c}" = "x" ] ; then 
		echo "Value can't be blank..."
	    else
		a="$c"
	    fi
	fi
    done

    eval "$1=\"$a\""
}

prepare_file()
{
    sed "s/TAG_SALT/$tag_salt/;s/TAG_TELEGRAM_API_TOKEN/$tag_telegram_api_token/;s/TAG_LOG_FILE/$tag_log_file/;
	s/TAG_DBUSER/$tag_dbuser/;s/TAG_DBPASS/$tag_dbpass/;s/TAG_DBNAME/$tag_dbname/;s/TAG_DBHOST/$tag_dbhost/;
	s/TAG_HOST/$tag_host/;s/TAG_DOMAIN/$tag_domain/;s/TAG_COMPACT_DATE/$tag_compact_date/;s/TAG_DATE/$tag_date/;s/TAG_COMPACT_TIME/$tag_compact_time/" < "$1" > "$2"
}

# Load previous values if they exist
test -e "$varsfile" && . "$varsfile"

# Make sure we have a random salt
test "x$tag_salt" = "x" && tag_salt="`(date +'%s' && dd if=/dev/urandom bs=160 count=1 iflag=fullblock 2>/dev/null) | sha256sum -b | sed 's/ .*$//')`"

ask "tag_telegram_api_token" "Enter your Telegram Bot API Token"
ask "tag_log_file" "Enter logfile path and name"
ask "tag_dbuser" "Enter DB username"
ask "tag_dbpass" "Enter DB password"
ask "tag_dbname" "Enter DB name"
ask "tag_dbhost" "Enter DB host"
ask "tag_host" "Enter hostname to this webserver (omit https://)"
ask "tag_domain" "Enter the domain name of this webserver"

tag_compact_date="`date +'%Y%m%d'`"
tag_date="`date +'%Y-%m-%d'`"
tag_compact_time="`date +'%H%M'`"

# Store the settings for the future
store_variables

# Make sure all dependencies are installed
which composer > /dev/null || (echo "The command composer is not installed. ABORTING..." >&2 && exit 1)
which mysql > /dev/null || (echo "The command mysql is not installed. ABORTING..." >&2 && exit 1)
test -x /usr/bin/php || (echo "Unable to find /usr/bin/php. ABORTING..." >&2 && exit 2)

# Test database connection
echo exit | mysql -u "$tag_dbuser" -p"$tag_dbpass" -h "$tag_dbhost" "$tag_dbname" || echo "Unable to access database. ABORTING..."

# Create table if not exist
echo "show create table locations" | mysql -s -u "$tag_dbuser" -p"$tag_dbpass" -h "$tag_dbhost" "$tag_dbname" > /dev/null 2>&1 || \
    mysql -s -u "$tag_dbuser" -p"$tag_dbpass" -h "$tag_dbhost" "$tag_dbname" < "${dir}/livelocations.sql"

# Install Telegram library for PHP
composer require telegram-bot/api || (echo "Something went wrong when installing Telegram API. ABORTING..." >&2 && exit 3)

# Create iitc directory if it is missing
test -d "$dir/www/iirc" || mkdir "$dir/www/iitc"

# Copy all files
prepare_file "${dir}/templates/config.json" "${dir}/config.json"
prepare_file "${dir}/templates/iitc/telegram-live-location.meta.js" "${dir}/www/iitc/telegram-live-location.meta.js"
prepare_file "${dir}/templates/iitc/telegram-live-location.user.js" "${dir}/www/iitc/telegram-live-location.user.js"

# Done
echo "All has been setup. You can now start the bot by executing $dir/loc2mapbot.php"

exit 0
