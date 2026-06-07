#!/usr/bin/env sh
set -eu

version=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' downloadtracker.xml | head -n 1)

cp downloadtracker.xml administrator/downloadtracker.xml
mkdir -p dist
rm -f dist/com_downloadtracker-*.zip
zip -r "dist/com_downloadtracker-${version}.zip" downloadtracker.xml administrator site media
