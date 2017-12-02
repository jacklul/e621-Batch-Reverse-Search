#!/bin/bash
# --------------------
 # e621 Batch Reverse Search Script
 #
 # Copyright (c) 2016 Jack'lul <jacklulcat@gmail.com>
 #
 # For the full copyright and license information,
 # please view the LICENSE file that was distributed
 # with this source code.
# --------------------

SPATH=$(dirname $0)
PATH=$SPATH/runtime:$PATH:

if which php >/dev/null; then
	php "$SPATH/e621BRS.phar" $@
else
	echo "Install 'php-cli' package first!"
fi

echo Press ENTER key to continue...
read -p "" key
