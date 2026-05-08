#!/usr/bin/env sh
set -eu

version=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' downloadtracker.xml | head -n 1)

cp downloadtracker.xml administrator/downloadtracker.xml
zip -r "com_downloadtracker-${version}.zip" downloadtracker.xml administrator site media
