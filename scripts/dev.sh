#!/usr/bin/env bash
#
# Development helper for W3Chess.
#
# Builds the solution and the Fable client, then runs MailDev, the Kestrel
# server and the Vite dev server together. Each service runs in its own session
# and process group; a trap stops them all when this script exits or you press
# Ctrl-C, so no stray processes are left behind.
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DOTNET="${DOTNET:-$HOME/.dotnet/dotnet}"
CLIENT_DIR="$ROOT/src/W3Chess.Client"
SERVER_PROJECT="$ROOT/src/W3Chess.Server"
CONTAINER="${DISTROBOX_CONTAINER:-ubuntu}"

# Development turns on the developer exception page, so server errors are visible.
export ASPNETCORE_ENVIRONMENT="${ASPNETCORE_ENVIRONMENT:-Development}"

pids=()

stop_all() {
    trap - EXIT INT TERM
    echo
    echo "==> Stopping dev services"
    for pid in "${pids[@]:-}"; do
        # Kill the whole process group; fall back to the single process.
        kill -- "-$pid" 2>/dev/null || kill "$pid" 2>/dev/null || true
    done
    wait 2>/dev/null || true
    echo "==> All stopped"
}
trap stop_all EXIT INT TERM

# Start a command in its own session/process group so its whole tree can be killed.
run_bg() {
    setsid "$@" &
    pids+=("$!")
}

command -v setsid >/dev/null 2>&1 || {
    echo "setsid is required (util-linux)." >&2
    exit 1
}

# Warn when a port is taken, so a forgotten manual instance is obvious.
check_port() {
    local port="$1" name="$2"
    if command -v ss >/dev/null 2>&1 && ss -ltn 2>/dev/null | grep -q ":$port "; then
        echo "warning: port $port ($name) is already in use - stop that process first" >&2
    fi
}
check_port 1025 "MailDev SMTP"
check_port 5000 "Kestrel"
check_port 5173 "Vite"

echo "==> Building the .NET solution"
"$DOTNET" build "$ROOT/W3Chess.slnx" --nologo

echo "==> Transpiling the Fable client"
"$DOTNET" fable "$CLIENT_DIR"

echo "==> Starting MailDev (SMTP :1025, web UI http://localhost:1080)"
run_bg distrobox enter "$CONTAINER" -- bash -lc 'npx --yes maildev'

echo "==> Starting the Kestrel server (http://localhost:5000)"
run_bg "$DOTNET" run --project "$SERVER_PROJECT" --no-build

echo "==> Starting the Vite dev server (http://localhost:5173)"
run_bg distrobox enter "$CONTAINER" -- bash -lc "cd '$CLIENT_DIR' && npx --yes vite"

echo
echo "==> Up. App: http://localhost:5173   Mail: http://localhost:1080   Ctrl-C stops everything."
wait
