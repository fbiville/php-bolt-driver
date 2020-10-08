#!/usr/bin/env bash

set -Eeuxo pipefail

composer install
composer dump-autoload
composer test
