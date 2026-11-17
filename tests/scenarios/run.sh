#!/bin/sh
# Runs every scenario of this folder, or the ones named as arguments, as a developer would: composer install,
# generate and generate --check on the scenario's PHP, then bidirekt validate and publish of the generated contract
# on a throwaway broker; a flow scenario runs the commands of its steps.txt instead. See README.md.
# COMPOSE_PROJECT_NAME picks the compose project (default php-sdk-scenarios).
set -eu

dir=$(cd "$(dirname "$0")" && pwd)
project=${COMPOSE_PROJECT_NAME:-php-sdk-scenarios}

# A run that mounts a volume keeps its container (no --rm) for the down on exit, which removes it before the volumes:
# with --rm, the daemon removes a container a signal killed only after down tried the volumes, which then stay.
# Compose takes the kept containers of another profile's service for orphans, and its warning would reach the outputs
# the scenarios compare.
compose() {
  COMPOSE_IGNORE_ORPHANS=1 docker compose --progress quiet -p "$project" -f "$dir/compose.yaml" "$@"
}

failed=0
tmp=$(mktemp -d)
# The broker's log tells why a broker step failed; a scenario that fails before the broker doesn't need it.
broker_log=1
cleanup() {
  if [ "$failed" != 0 ] && [ "$broker_log" = 1 ]; then
    compose logs broker
  fi
  # Without every profile, down keeps the volumes that only the services of a profile use.
  compose --profile '*' down -v --remove-orphans
  rm -rf "$tmp"
}
trap 'failed=$?; cleanup' EXIT
# dash and busybox ash skip the EXIT trap when a signal kills the shell.
trap 'exit 1' HUP INT TERM

# The project scenarios first, then the flows, which deploy to production.
if [ $# = 0 ]; then
  for scenario in "$dir"/*/.php-version "$dir"/*/steps.txt; do
    if [ -f "$scenario" ]; then
      set -- "$@" "$(basename "$(dirname "$scenario")")"
    fi
  done
fi

# The services of the flow scenario $1: its folders with a .php-version.
flow_services() {
  for version in "$dir/$1"/*/.php-version; do
    if [ -f "$version" ]; then
      basename "$(dirname "$version")"
    fi
  done
}

services=$(compose --profile '*' config --services)
# Exits unless compose.yaml has a service for the PHP version in the file $1 of tests/scenarios.
check_version() {
  version=$(cat "$dir/$1")
  if ! printf '%s\n' "$services" | grep -qx "php-$version"; then
    echo "tests/scenarios/$1 says $version, but tests/scenarios/compose.yaml has no service php-$version" >&2
    exit 1
  fi
}

for name in "$@"; do
  if [ -f "$dir/$name/steps.txt" ]; then
    flow=$(flow_services "$name")
    if [ -z "$flow" ]; then
      echo "tests/scenarios/$name/steps.txt has no service to run in: no folder of tests/scenarios/$name has a .php-version" >&2
      exit 1
    fi
    for service in $flow; do
      check_version "$name/$service/.php-version"
    done
    continue
  fi
  if [ ! -f "$dir/$name/.php-version" ]; then
    echo "no scenario \"$name\": tests/scenarios/$name has no .php-version nor steps.txt" >&2
    exit 1
  fi
  check_version "$name/.php-version"
done

compose --profile php build
compose up -d --wait

# docker compose run reads all of a stdin that is not a terminal, such as a script piped to sh.
BIDIREKT_API_KEY=$(compose run --rm -T setup </dev/null)
export BIDIREKT_API_KEY
if ! output=$(compose run -T bidirekt create-environment production 2>&1 </dev/null); then
  printf 'bidirekt create-environment failed: %s\n' "$output" >&2
  exit 1
fi
broker_log=0

# Prints why the scenario failed, below its FAIL line.
fail() {
  failures=$((failures + 1))
  echo "FAIL $1 ($(($(date +%s) - started))s)"
  printf '%s\n' "$2" | sed 's/^/  /'
}

# Runs a bidirekt command in the scenario's copy, or prints why it failed and returns 1.
bidirekt() {
  if ! output=$(compose run -T -w "/work/$name" bidirekt "$@" 2>&1 </dev/null); then
    broker_log=1
    fail "$name" "bidirekt $1 failed:
$output"
    return 1
  fi
}

# Runs a command of steps.txt, $2, in the copy of the service $1, and appends what it prints and its exit code to the
# transcript. bidirekt runs in the CLI's image, anything else in the service's PHP.
run_step() {
  case $2 in
    bidirekt | 'bidirekt '*) set -- --entrypoint sh -w "/work/$name/$1" bidirekt -c "exec 2>&1; $2" ;;
    *) set -- -w "/sdk/tests/scenarios/$name/$1" "php-$(cat "$dir/$name/$1/.php-version")" sh -c "exec 2>&1; $2" ;;
  esac
  code=0
  # compose prints an empty line of its own when the command fails: only its messages go to the transcript.
  compose run -T "$@" >>"$tmp/transcript" 2>"$tmp/compose" </dev/null || code=$?
  grep . "$tmp/compose" >>"$tmp/transcript" || true
  if [ -n "$(tail -c 1 "$tmp/transcript")" ]; then
    echo >>"$tmp/transcript"
  fi
  if [ "$code" != 0 ]; then
    echo "[$code]" >>"$tmp/transcript"
  fi
}

# Runs the flow scenario $name: copies it, runs composer install in each service, then each command of steps.txt,
# and compares the transcript with steps.txt.
run_flow() {
  copy=/sdk/tests/scenarios/$name
  copied=0
  for service in $(flow_services "$name"); do
    php=php-$(cat "$dir/$name/$service/.php-version")
    if [ "$copied" = 0 ]; then
      if ! output=$(compose run -T "$php" sh -c 'rm -rf "$1" && cp -R "/scenarios/$2" "$1"' sh "$copy" "$name" 2>&1 </dev/null); then
        fail "$name" "copying the scenario failed:
$output"
        return
      fi
      copied=1
    fi
    if ! output=$(compose run -T -w "$copy/$service" "$php" sh -c 'rm -rf vendor && composer install --no-progress' 2>&1 </dev/null); then
      fail "$name" "composer install in $service failed:
$output"
      return
    fi
  done

  : >"$tmp/transcript"
  number=0
  while IFS= read -r line || [ -n "$line" ]; do
    number=$((number + 1))
    case $line in
      *'$ '*) service=${line%%'$ '*} ;;
      *) continue ;;
    esac
    case $service in
      '' | *[!a-z0-9_-]*) continue ;;
    esac
    if [ ! -f "$dir/$name/$service/.php-version" ]; then
      fail "$name" "steps.txt:$number: no service \"$service\": tests/scenarios/$name/$service/.php-version does not exist"
      return
    fi
    printf '%s\n' "$line" >>"$tmp/transcript"
    run_step "$service" "${line#*'$ '}"
  done <"$dir/$name/steps.txt"

  if ! output=$(diff -u -L steps.txt -L transcript "$dir/$name/steps.txt" "$tmp/transcript"); then
    broker_log=1
    fail "$name" "the steps printed another transcript:
$output"
    return
  fi
  passed=$((passed + 1))
  echo "ok $name ($(($(date +%s) - started))s)"
}

passed=0
failures=0
for name in "$@"; do
  started=$(date +%s)
  if [ -f "$dir/$name/steps.txt" ]; then
    run_flow
    continue
  fi
  php=php-$(cat "$dir/$name/.php-version")

  if ! contract=$(compose run -T "$php" sh /scenarios/generate.sh "$name" 2>&1 </dev/null); then
    fail "$name" "$contract"
    continue
  fi

  if [ -n "$contract" ]; then
    participant=$(printf '%s' "$name" | tr - _)
    bidirekt create-participant "$participant" || continue
    bidirekt validate "$contract" --participant "$participant" --environment production || continue
    bidirekt publish "$contract" --participant "$participant" --version 1 || continue
  fi

  passed=$((passed + 1))
  echo "ok $name ($(($(date +%s) - started))s)"
done

echo "$passed passed, $failures failed"
[ "$failures" = 0 ]
