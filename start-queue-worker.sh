#!/usr/bin/env sh

set -eu

WORKER_COUNT="${WORKER_COUNT:-3}"
QUEUE_LIST="${QUEUE_LIST:-capture,migrate,default}"
TRIES="${WORKER_TRIES:-3}"
TIMEOUT="${WORKER_TIMEOUT:-1800}"
SLEEP="${WORKER_SLEEP:-1}"
BACKOFF="${WORKER_BACKOFF:-5}"

echo "Starting ${WORKER_COUNT} queue worker(s) for queue(s): ${QUEUE_LIST}"

i=1
while [ "$i" -le "$WORKER_COUNT" ]; do
	echo "Starting worker #${i}"
	php artisan queue:work \
		--queue="${QUEUE_LIST}" \
		--tries="${TRIES}" \
		--timeout="${TIMEOUT}" \
		--sleep="${SLEEP}" \
		--backoff="${BACKOFF}" &
	i=$((i + 1))
done

wait
