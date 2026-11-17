#!/bin/sh
# Runs in a php-<version> service of compose.yaml for the scenario named by $1: copies it, runs composer install,
# generate and generate --check in the copy as a developer would, and compares the results with the scenario's.
# On success prints the path of the generated contract inside the copy, or nothing when the scenario expects exit 1.
# On failure prints why and exits 1.
set -eu
set -f
exec 2>&1

name=$1
scenario=/scenarios/$name
copy=/sdk/tests/scenarios/$name
results=$(mktemp -d)

rm -rf "$copy"
cp -R "$scenario" "$copy"
rm -rf "$copy/vendor"
cd "$copy"

if ! composer install --no-progress >"$results/composer" 2>&1; then
  echo "composer install failed:"
  cat "$results/composer"
  exit 1
fi

args=
if [ -f args.txt ]; then
  args=$(cat args.txt)
fi
contract=contracts/generated.yaml
set -- $args
while [ $# -gt 1 ]; do
  if [ "$1" = --project ]; then
    contract=$2/contracts/generated.yaml
  fi
  shift
done

if [ -f expected-stderr.txt ]; then
  cp expected-stderr.txt "$results/expected-stderr"
else
  : >"$results/expected-stderr"
fi

files() {
  find . -name vendor -prune -o -type f -exec md5sum {} + | sort -k 2
}

# Runs vendor/bin/php-sdk with the given flags and the scenario's, and fails unless stdout is empty and stderr is expected.
# Leaves the exit code in $code.
generate() {
  command=$(echo generate "$@" $args)
  code=0
  vendor/bin/php-sdk generate "$@" $args >"$results/stdout" 2>"$results/stderr" </dev/null || code=$?
  if [ -s "$results/stdout" ]; then
    echo "$command printed to stdout:"
    cat "$results/stdout"
    exit 1
  fi
  if ! diff -u -L expected-stderr.txt -L stderr "$results/expected-stderr" "$results/stderr"; then
    echo "$command printed another stderr (exit $code)"
    exit 1
  fi
}

files >"$results/before"

if [ ! -f expected.yaml ]; then
  generate
  if [ "$code" != 1 ]; then
    echo "$command exited with $code, expected 1"
    exit 1
  fi
  files >"$results/after"
  if ! diff -u -L before -L after "$results/before" "$results/after"; then
    echo "$command wrote files, expected none"
    exit 1
  fi
  exit 0
fi

generate
if [ "$code" != 0 ]; then
  echo "$command exited with $code, expected 0"
  exit 1
fi
if [ ! -f "$contract" ]; then
  echo "$command wrote no $contract"
  exit 1
fi
if ! diff -u -L expected.yaml -L "$contract" expected.yaml "$contract"; then
  echo "$contract differs from expected.yaml"
  exit 1
fi
files | grep -v "  ./$contract\$" >"$results/after" || true
grep -v "  ./$contract\$" "$results/before" >"$results/before-but-contract" || true
if ! diff -u -L before -L after "$results/before-but-contract" "$results/after"; then
  echo "$command wrote files other than $contract"
  exit 1
fi

generate --check
if [ "$code" != 0 ]; then
  echo "$command exited with $code, expected 0"
  exit 1
fi

echo "$contract"
