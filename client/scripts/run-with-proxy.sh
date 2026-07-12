#!/usr/bin/env bash
# Run any command with HTTP(S) proxy env vars (curl, git, node tools that respect them).
export HTTP_PROXY=http://127.0.0.1:10808
export HTTPS_PROXY=http://127.0.0.1:10808
export ALL_PROXY=http://127.0.0.1:10808
export http_proxy=http://127.0.0.1:10808
export https_proxy=http://127.0.0.1:10808
export all_proxy=http://127.0.0.1:10808
exec "$@"
