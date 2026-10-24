#!/bin/sh
# Builds unglomar.zip from the userscript and README, without macOS metadata.
set -e
cd "$(dirname "$0")"
rm -f unglomar.zip
zip -X -q unglomar.zip unglomar.user.js README.md
