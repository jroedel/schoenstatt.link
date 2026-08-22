#!/usr/bin/env bash
#
# Measure what the caches are actually doing, per route, cold versus warm.
#
# Installs tools/perf/perf.local.php into config/autoload/, drives a fixed URL set through
# the running capsule, and removes it again. Everything it writes goes to data/perf/.
#
# Two things this script exists to get right, because both are easy to get wrong by hand and
# both silently produce a flattering number:
#
#   * the merged-config cache. Adding or removing a file in config/autoload/ changes the
#     merged config, and data/config/ holds a serialized copy of it. Forget to clear it and
#     the probes are simply never installed — the run completes and reports zero cache
#     activity, which looks like a finding.
#   * OPcache revalidation. The capsule runs revalidate_freq=2, so a file written less than
#     two seconds before a request may not be seen by the worker that serves it.
#
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT" || exit 1

BASE="${PERF_BASE:-http://localhost:8080}"
KEY="${PERF_API_KEY:-local-dev-api-key}"
OUT_DIR="$ROOT/data/perf"
OUT_HOST="$OUT_DIR/requests.jsonl"
INSTALLED="$ROOT/config/autoload/zz-perf.local.php"
REPEATS="${PERF_REPEATS:-3}"

bold() { printf '\033[1m%s\033[0m\n' "$*"; }
ok()   { printf '  \033[32mok\033[0m    %s\n' "$*"; }
warn() { printf '  \033[33mwarn\033[0m  %s\n' "$*"; }
die()  { printf '  \033[31mFAIL\033[0m  %s\n' "$*"; exit 1; }

# ---------------------------------------------------------------- URL set
#
# Chosen to cover every SionTable the application declares plus the two front controllers,
# not to be a traffic sample. A route is here because some table's cache behaviour is only
# visible through it; the comment on each says which.
URLS=(
  "/en/                                                              home         two getChangesCountPerMonth() calls, navigation, layout"
  "/de/                                                              home-de      the same page in a second locale: translation caches"
  "/en/dictionary                                                    dictionary   DictionaryTable index"
  "/en/music                                                         music        MusicTable index, 1675 compositions"
  "/en/SL100320A/schoenstatt-shrine-nueva-helvecia                   shrine       SchoenstattTable entity show, the slowest page measured"
  "/en/SL202154L/les-annees-cachees-pere-joseph-kentenich-enfance-e  publication  PublicationsTable entity show, largest table (32k urls)"
  "/en/SL500372C/website-with-many-schoenstatt-songs                 composition  MusicTable entity show"
  "/en/developers                                                    developers   a near-static page: the floor for layout + translation"
  "/api/v3/schema                                                    api-schema   public JSON, no entity tables"
  "/sitemap.xml                                                      sitemap      served by Apache from disk: the control, PHP never runs"
)

# ---------------------------------------------------------------- install
install_probe() {
  cp "$ROOT/tools/perf/perf.local.php" "$INSTALLED" || die "could not install the probe config"
  rm -rf "$ROOT"/data/config/*.php
  mkdir -p "$OUT_DIR"
  : > "$OUT_HOST"
  chmod 0666 "$OUT_HOST" 2>/dev/null
  #OPcache revalidate_freq=2: without this the worker serving the first request may still be
  #running the pre-install config. Two seconds is the configured window, three is the margin.
  sleep 3
  ok "probe installed at config/autoload/zz-perf.local.php"
}

remove_probe() {
  rm -f "$INSTALLED"
  rm -rf "$ROOT"/data/config/*.php
  #Disarms the collector even if a copy of the probe config survives somewhere.
  [ -d "$OUT_DIR" ] && mv "$OUT_HOST" "$ROOT/data/perf-${PERF_TAG:-last}.jsonl" 2>/dev/null
  rmdir "$OUT_DIR" 2>/dev/null
  ok "probe removed, merged-config cache cleared"
}
trap remove_probe EXIT

flush_cache() {
  local body
  body=$(curl -s -o /dev/null -w '%{http_code}' -H "X-Api-Key: $KEY" "$BASE/en/sm/clear-persistent-cache")
  [ "$body" = "200" ] || warn "cache flush answered HTTP $body (expected 200) — 'cold' may not be cold"
}

hit() {
  local path="$1" label="$2"
  curl -s -o /dev/null -w '%{http_code} %{time_total}\n' \
    -H "X-Perf-Label: $label" "$BASE$path"
}

# ---------------------------------------------------------------- run
bold "=== Cache performance harness ==="
printf '  base %s, %s repeat(s) per phase\n\n' "$BASE" "$REPEATS"

#Not /_health: that route exists only under the Symfony front controller, and this harness
#has to be runnable against both — comparing them is the point.
curl -sf -o /dev/null "$BASE/en/" || die "the capsule is not answering at $BASE"

KERNEL=$(curl -s "$BASE/_health" | grep -o '"kernel":"[a-z]*"' || true)
printf '  front controller: %s\n\n' "${KERNEL:-laminas (no /_health route)}"

install_probe

for phase in cold warm; do
  bold "--- $phase"
  [ "$phase" = "cold" ] && flush_cache
  for row in "${URLS[@]}"; do
    read -r path label _rest <<<"$row"
    for _ in $(seq 1 "$REPEATS"); do
      #The label reaches the collector through the environment of the PHP worker, which curl
      #cannot set — so it goes in the URI's query string, which the collector already records.
      out=$(curl -s -o /dev/null -w '%{http_code} %{time_total}' "$BASE$path?_perf=${PERF_TAG:+$PERF_TAG-}$phase.$label")
      code="${out%% *}"
      case "$code" in
        200|301|302) ;;
        *) warn "$label answered HTTP $code" ;;
      esac
    done
    printf '  %-14s %s\n' "$label" "$path"
  done
  echo
done

# ---------------------------------------------------------------- report
#data/ is bind-mounted into the container, so the file the workers appended to *is* this
#one; there is nothing to copy back.
lines=$(wc -l < "$OUT_HOST" 2>/dev/null || echo 0)
[ "$lines" -gt 0 ] || die "no measurements were recorded — the probe did not install (merged-config cache?)"
ok "$lines request records in data/perf/requests.jsonl"

# A probe that records requests but no work inside them is broken, not a finding. The first
# version of the cache listener threw a TypeError on every getItem — laminas-cache catches
# Exception, not Error, so the failure was invisible — and the harness confidently reported
# zero queries and zero cache activity on every route. Refusing to print that is the
# difference between a tool and a source of wrong conclusions.
work=$(php -r '
$q = 0; $c = 0;
foreach (file($argv[1], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $r = json_decode($line, true);
    if (! is_array($r)) { continue; }
    $q += (int) ($r["queries"] ?? 0);
    $c += count($r["cache"] ?? []);
}
echo $q + $c;
' "$OUT_HOST")
[ "$work" -gt 0 ] || die "records were written but every one is empty — the probes are attached but recording nothing. Do not read this as 'the site does no database work'; debug the harness."
ok "$work query/cache observations recorded"
echo

php "$ROOT/tools/perf/report.php" "$OUT_HOST"
