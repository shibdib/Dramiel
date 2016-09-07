#!/bin/bash
# Absolute path 
SCRIPT=$(readlink -f "$0")
# Absolute path 
SCRIPTPATH=$(dirname "$SCRIPT")
cd $SCRIPTPATH
git -C ../.. pull origin dev
composer update -d ../..
echo "1"