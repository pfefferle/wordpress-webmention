#!/usr/bin/env bash
# See https://raw.githubusercontent.com/wp-cli/scaffold-command/master/templates/install-wp-tests.sh

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [cp-version] [skip-database-creation]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
CP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e "s/\/$//")
CP_TESTS_DIR=${CP_TESTS_DIR-$TMPDIR/classicpress-tests-lib}
CP_CORE_DIR=${CP_CORE_DIR-$TMPDIR/classicpress}

# Remove trailing slashes
CP_TESTS_DIR=$(echo "$CP_TESTS_DIR" | sed 's:/\+$::')
CP_CORE_DIR=$(echo "$CP_CORE_DIR" | sed 's:/\+$::')

download() {
	if [ `which curl` ]; then
		curl -L -s "$1" -o "$2"
	elif [ `which wget` ]; then
		wget -nv -O "$2" "$1"
	fi
}

set -ex

# $CP_VERSION may be one of the following:
# 'latest' - latest stable release
# '1.2.3' or '1.2.3-rc1' etc - any released version number
# 'git+abc123' - use the specific commit 'abc123' from the *development* repo

CP_RELEASE=y
if [[ "$CP_VERSION" == latest ]]; then
	# Find the version number of the latest release
	download \
		https://api.github.com/repos/ClassicPress/ClassicPress-release/releases/latest \
		"$TMPDIR/cp-latest.json"
	CP_VERSION="$(grep -Po '"tag_name":\s*"[^"]+"' "$TMPDIR/cp-latest.json" | cut -d'"' -f4)"

	if [ -z "$CP_VERSION" ]; then
		echo "ClassicPress version not detected correctly!"
		cat "$TMPDIR/cp-latest.json"
		exit 1
	fi
elif [[ "$CP_VERSION" = git-* ]]; then
	# Use a specific commit from the development repo
	CP_RELEASE=n
	CP_VERSION=${CP_VERSION#git-}
elif ! [[ "$CP_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-|$) ]]; then
	# Anything else needs to be a release version number
	echo "ClassicPress version number not supported: $CP_VERSION"
	exit 1
fi

if [ $CP_RELEASE = y ]; then
	# Remote URLs for release and dev packages/files
	CP_BUILD_ZIP_URL="https://github.com/ClassicPress/ClassicPress-release/archive/$CP_VERSION.zip"
	CP_DEV_ZIP_URL="https://github.com/ClassicPress/ClassicPress/archive/$CP_VERSION+dev.zip"
	CP_DEV_FILE_URL="https://raw.githubusercontent.com/ClassicPress/ClassicPress/$CP_VERSION+dev"
	# Local paths
	CP_BUILD_ZIP_PATH="$TMPDIR/classicpress-release-$CP_VERSION.zip"
	CP_DEV_ZIP_PATH="$TMPDIR/classicpress-dev-$CP_VERSION.zip"
	CP_DEV_PATH="$TMPDIR/classicpress-dev-$CP_VERSION"
else
	# Remote URLs for dev packages/files (no release build)
	CP_DEV_ZIP_URL="https://github.com/ClassicPress/ClassicPress/archive/$CP_VERSION.zip"
	CP_DEV_FILE_URL="https://raw.githubusercontent.com/ClassicPress/ClassicPress/$CP_VERSION"
	# Local paths
	CP_DEV_ZIP_PATH="$TMPDIR/classicpress-dev-$CP_VERSION.zip"
	CP_DEV_PATH="$TMPDIR/classicpress-dev-$CP_VERSION"
fi

install_cp() {
	if [ -d $CP_CORE_DIR ]; then
		return;
	fi

	mkdir -p $CP_CORE_DIR

	if [ $CP_RELEASE = y ]; then
		download "$CP_BUILD_ZIP_URL" "$CP_BUILD_ZIP_PATH"
		unzip -q "$CP_BUILD_ZIP_PATH" -d "$CP_CORE_DIR"
	else
		download "$CP_DEV_ZIP_URL" "$CP_DEV_ZIP_PATH"
		unzip -q "$CP_DEV_ZIP_PATH" -d "$CP_CORE_DIR"
	fi
	clean_github_download "$CP_CORE_DIR" true

	download \
		https://raw.github.com/markoheijnen/wp-mysqli/master/db.php \
		"$CP_CORE_DIR/wp-content/db.php"

	# Hello Dolly is still used in some tests.
	download \
		"$CP_DEV_FILE_URL/src/wp-content/plugins/hello.php" \
		"$CP_CORE_DIR/wp-content/plugins/hello.php"
}

clean_github_download() {
	# GitHub downloads extract with a single folder inside, named based on the
	# version downloaded. Get rid of this.
	dir="$1"
	remove_src_dir="$2"
	mv "$dir" "$dir-old"
	mv "$dir-old/ClassicPress-"* "$dir"
	rmdir "$dir-old"
	if [ -d "$dir/src" ] && [ "$remove_src_dir" = true ]; then
		# Development build - get rid of the 'src' directory too.
		mv "$dir" "$dir-old"
		mv "$dir-old/src" "$dir"
		rm -rf "$dir-old"
	fi
}

install_test_suite() {
	# portable in-place argument for both GNU sed and Mac OSX sed
	if [[ $(uname -s) == 'Darwin' ]]; then
		local ioption='-i .bak'
	else
		local ioption='-i'
	fi

	# set up testing suite if it doesn't yet exist
	if [ ! -d "$CP_TESTS_DIR" ]; then
		mkdir -p "$CP_TESTS_DIR"
		download "$CP_DEV_ZIP_URL" "$CP_DEV_ZIP_PATH"
		unzip -q "$CP_DEV_ZIP_PATH" -d "$CP_DEV_PATH"
		clean_github_download "$CP_DEV_PATH" false
		cp -ar \
			"$CP_DEV_PATH/tests/phpunit/includes" \
			"$CP_DEV_PATH/tests/phpunit/data" \
			"$CP_TESTS_DIR/"
	fi

	if [ ! -f wp-tests-config.php ]; then
		download \
			"$CP_DEV_FILE_URL/wp-tests-config-sample.php" \
			"$CP_TESTS_DIR/wp-tests-config.php"
		sed $ioption "s:dirname( __FILE__ ) . '/src/':'$CP_CORE_DIR/':" "$CP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" "$CP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourusernamehere/$DB_USER/" "$CP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourpasswordhere/$DB_PASS/" "$CP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s|localhost|${DB_HOST}|" "$CP_TESTS_DIR"/wp-tests-config.php
	fi

}

install_db() {

	if [ ${SKIP_DB_CREATE} = "true" ]; then
		return 0
	fi

	# parse DB_HOST for port or socket references
	local PARTS=(${DB_HOST//\:/ })
	local DB_HOSTNAME=${PARTS[0]};
	local DB_SOCK_OR_PORT=${PARTS[1]};
	local EXTRA=""

	if ! [ -z $DB_HOSTNAME ] ; then
		if [ $(echo $DB_SOCK_OR_PORT | grep -e '^[0-9]\{1,\}$') ]; then
			EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
		elif ! [ -z $DB_SOCK_OR_PORT ] ; then
			EXTRA=" --socket=$DB_SOCK_OR_PORT"
		elif ! [ -z $DB_HOSTNAME ] ; then
			EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
		fi
	fi

	# create database
	mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA
}

install_e2e_site() {

	if [[ ${RUN_E2E} == 1 ]]; then

		# Script Variables
		CONFIG_DIR="./tests/e2e-tests/config/travis"
		CP_CORE_DIR="$HOME/classicpress"
		CC_PLUGIN_DIR="$CP_CORE_DIR/wp-content/plugins/classic-commerce"
		NGINX_DIR="$HOME/nginx"
		PHP_FPM_BIN="$HOME/.phpenv/versions/$TRAVIS_PHP_VERSION/sbin/php-fpm"
		PHP_FPM_CONF="$NGINX_DIR/php-fpm.conf"
		CP_SITE_URL="http://localhost:8080"
		BRANCH=$TRAVIS_BRANCH
		REPO=$TRAVIS_REPO_SLUG
		CP_DB_DATA="$HOME/build/$REPO/tests/e2e-tests/data/e2e-db.sql"
		WORKING_DIR="$PWD"

		if [ "$TRAVIS_PULL_REQUEST_BRANCH" != "" ]; then
			BRANCH=$TRAVIS_PULL_REQUEST_BRANCH
			REPO=$TRAVIS_PULL_REQUEST_SLUG
		fi

		set -ev
		npm install
		export NODE_CONFIG_DIR="./tests/e2e-tests/config"

		# Set up nginx to run the server
		mkdir -p "$CP_CORE_DIR"
		mkdir -p "$NGINX_DIR"
		mkdir -p "$NGINX_DIR/sites-enabled"
		mkdir -p "$NGINX_DIR/var"

		cp "$CONFIG_DIR/travis_php-fpm.conf" "$PHP_FPM_CONF"

		# Start php-fpm
		"$PHP_FPM_BIN" --fpm-config "$PHP_FPM_CONF"

		# Copy the default nginx config files.
		cp "$CONFIG_DIR/travis_nginx.conf" "$NGINX_DIR/nginx.conf"
		cp "$CONFIG_DIR/travis_fastcgi.conf" "$NGINX_DIR/fastcgi.conf"
		cp "$CONFIG_DIR/travis_default-site.conf" "$NGINX_DIR/sites-enabled/default-site.conf"

		# Start nginx.
		nginx -c "$NGINX_DIR/nginx.conf"

		# Set up ClassicPress using wp-cli
		cd "$CP_CORE_DIR"

		curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
		# Note, `wp core download` does not work when running the tests with a
		# development version of ClassicPress, so we'll substitute the latest
		# build instead!
		if [ -z "$CP_BUILD_ZIP_URL" ]; then
			CP_BUILD_ZIP_URL="https://www.classicpress.net/latest.zip"
		fi
		php wp-cli.phar core download "$CP_BUILD_ZIP_URL"
		php wp-cli.phar core config --dbname=$DB_NAME --dbuser=$DB_USER --dbpass=$DB_PASS --dbhost=$DB_HOST --dbprefix=wp_ --extra-php <<PHP
/* Change WP_MEMORY_LIMIT to increase the memory limit for public pages. */
define('WP_MEMORY_LIMIT', '256M');
define('SCRIPT_DEBUG', true);
PHP
		php wp-cli.phar core install --url="$CP_SITE_URL" --title="Example" --admin_user=admin --admin_password=password --admin_email=info@example.com --path=$CP_CORE_DIR --skip-email
		php wp-cli.phar db import $CP_DB_DATA
		php wp-cli.phar search-replace "http://local.wordpress.test" "$CP_SITE_URL"
		php wp-cli.phar theme install twentytwelve --activate

		# Instead of installing WC from a GH zip, rather used the checked out branch?
		# php wp-cli.phar plugin install https://github.com/$REPO/archive/$BRANCH.zip --activate
		echo "CREATING Classic Commerce PLUGIN DIR AT $CC_PLUGIN_DIR"
		mkdir $CC_PLUGIN_DIR
		echo "COPYING CHECKED OUT BRANCH TO $CC_PLUGIN_DIR"
		cp -R "$TRAVIS_BUILD_DIR" "$CP_CORE_DIR/wp-content/plugins/"
		ls "$CP_CORE_DIR/wp-content/plugins/classic-commerce/"

		# Compile assets and installing dependencies
		echo "COMPILING ASSETS IN $CC_PLUGIN_DIR"
		cd $CC_PLUGIN_DIR
		npm install
		composer install
		grunt e2e-build

		echo "ACTIVATING Classic Commerce PLUGIN"
		php wp-cli.phar plugin activate classic-commerce
		echo "RUNNING Classic Commerce UPDATE ROUTINE"
		php wp-cli.phar wc update

		echo "DONE INSTALLING E2E SUITE."
		cd "$WORKING_DIR"
		echo "WORKING DIR: $WORKING_DIR"
	fi
}

install_cp
install_test_suite
install_db
install_e2e_site
