#!/bin/bash
# Watches /templates/ for changes and syncs to DB automatically.
# Templates (.html) go through import_templates.php; stylesheets
# (One_Piece_Gaiden_Templates/stylesheets/*.css) go through
# import_stylesheets.php — see docs/css-sync-design.md. Both run on every
# detected change, same as import_templates.php already did on its own:
# cheap, and it means one file's save doesn't need its own watcher.
# Requirements: fswatch (brew install fswatch)
#
# Usage: ./watch_templates.sh

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

if ! command -v fswatch &> /dev/null; then
    echo "fswatch not found. Install it with: brew install fswatch"
    exit 1
fi

echo "Watching templates/ for changes... (Ctrl+C to stop)"

fswatch -o "$SCRIPT_DIR/templates/" | while read -r event; do
    echo "[$(date '+%H:%M:%S')] Change detected, syncing..."
    php "$SCRIPT_DIR/import_templates.php"
    php "$SCRIPT_DIR/import_stylesheets.php"
done
