#!/bin/bash

LOGDIR="/var/log/gw2spidy"

if [ -z "$ROOT" ]; then
    ROOT=`php -r "echo dirname(dirname(realpath('$(pwd)/$0')));"`
    export ROOT
fi

if [ -z "$PHP" ]; then
    if [ -z `which hhvm` ]; then
        PHP="php"
        export PHP
    else
        PHP="hhvm"
    fi
fi

echo "Start $SECONDS"
$PHP ${ROOT}/tools/update-items-from-api.php

echo "Items created $SECONDS"
$PHP ${ROOT}/tools/create-recipe-map.php recipemap

echo "recipemap created $SECONDS"
$PHP ${ROOT}/tools/import-recipe-map.php recipemap

rm recipemap
echo "Finish $SECONDS"
