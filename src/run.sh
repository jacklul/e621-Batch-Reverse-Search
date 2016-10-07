#!/bin/bash

SPATH=$(dirname $0)
PATH=$PATH:$SPATH/runtime

if which php >/dev/null; then
	php "$SPATH/e621BRS.phar" $@
else
	echo "Install 'php-cli' package first!"
fi

read -p "Press ENTER key to continue..." key
