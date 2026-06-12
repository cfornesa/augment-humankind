#!/usr/bin/env sh
set -eu

exec php \
  -d upload_max_filesize=32M \
  -d post_max_size=32M \
  -S localhost:8000 \
  -t public \
  public/index.php
