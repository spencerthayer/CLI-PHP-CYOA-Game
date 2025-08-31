#!/usr/bin/env bash
# combine-llms-mdx.sh
# Combine all .mdx pages referenced by an LLMS.txt list into one MDX file.
# Usage:
#   ./combine-llms-mdx.sh -i ./LLMS.txt -o combined.mdx
#   ./combine-llms-mdx.sh -i https://openrouter.ai/docs/llms.txt -o openrouter.mdx

set -u

# Defaults
INPUT_SOURCE=""
OUTPUT_FILE="combined.mdx"

print_usage() {
  cat <<'USAGE'
Combine MDX links from an LLMS.txt file into one MDX file.

Options:
  -i, --input   URL or path to LLMS.txt
  -o, --output  Path for combined MDX output file (default: combined.mdx)
  -h, --help    Show this help

Examples:
  ./combine-llms-mdx.sh -i https://openrouter.ai/docs/llms.txt -o openrouter-docs.mdx
  ./combine-llms-mdx.sh -i ./LLMS.txt -o docs.mdx
USAGE
}

# Parse flags
while [ $# -gt 0 ]; do
  case "$1" in
    -i|--input)
      INPUT_SOURCE="${2:-}"
      shift 2
      ;;
    -o|--output)
      OUTPUT_FILE="${2:-}"
      shift 2
      ;;
    -h|--help)
      print_usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      print_usage
      exit 1
      ;;
  esac
done

if [ -z "$INPUT_SOURCE" ]; then
  echo "Error: you must provide -i URL_or_path_to_LLMS.txt" >&2
  exit 1
fi

# Check for required tools
for cmd in curl grep sed awk; do
  if ! command -v "$cmd" >/dev/null 2>&1; then
    echo "Error: required tool not found: $cmd" >&2
    exit 1
  fi
done

# Fetch or read LLMS.txt into a temp file
LLMS_TMP="$(mktemp)"
cleanup() { rm -f "$LLMS_TMP"; }
trap cleanup EXIT

if printf '%s' "$INPUT_SOURCE" | grep -Eq '^https?://'; then
  echo "Reading LLMS list from URL: $INPUT_SOURCE" >&2
  if ! curl -fsSL --retry 3 --retry-delay 1 "$INPUT_SOURCE" > "$LLMS_TMP"; then
    echo "Error: failed to download LLMS.txt from $INPUT_SOURCE" >&2
    exit 1
  fi
else
  echo "Reading LLMS list from file: $INPUT_SOURCE" >&2
  if ! cat "$INPUT_SOURCE" > "$LLMS_TMP"; then
    echo "Error: failed to read local LLMS.txt at $INPUT_SOURCE" >&2
    exit 1
  fi
fi

# Compute base origin for resolving relative links like /docs/foo.mdx
BASE_ORIGIN=""
if printf '%s\n' "$INPUT_SOURCE" | grep -Eq '^https?://'; then
  BASE_ORIGIN="$(printf '%s\n' "$INPUT_SOURCE" | awk -F/ '{print $1"//"$3}')"
fi

# Start the output file with the LLMS.txt content
{
  echo "<!-- Combined from: $INPUT_SOURCE on $(date -u +"%Y-%m-%dT%H:%M:%SZ") -->"
  echo
  echo "### Source LLMS.txt"
  echo
  echo '```text'
  cat "$LLMS_TMP"
  echo '```'
  echo
  echo '---'
  echo
} > "$OUTPUT_FILE"

# Extract .mdx links in order of appearance.
# Supports:
#   - absolute links: https://example.com/path/file.mdx
#   - relative links in markdown: (/path/file.mdx)
# Keeps order as they appear in the LLMS.txt file.
#
# Note: this captures each occurrence rather than deduplicating.
# If you want dedupe, pipe through: awk '!seen[$0]++'
grep -Eo 'https?://[^)[:space:]]+\.mdx|\([^)[:space:]]+\.mdx\)' "$LLMS_TMP" \
| sed 's/^\(.*\)$/\1/' \
| while IFS= read -r token; do
    # Strip surrounding parentheses if present
    url="$token"
    case "$url" in
      \(*\)) url="${url#(}"; url="${url%)}" ;;
    esac

    # Resolve relative paths if possible
    case "$url" in
      http*://*) : ;;             # already absolute
      /*)
        if [ -n "$BASE_ORIGIN" ]; then
          url="${BASE_ORIGIN}${url}"
        else
          echo "Skipping relative link without base: $url" >&2
          continue
        fi
        ;;
      *)
        # Not absolute or rooted relative. Skip to keep behavior predictable.
        echo "Skipping non absolute link: $url" >&2
        continue
        ;;
    esac

    echo "Fetching: $url" >&2

    # Write a clear separator, then append fetched MDX
    {
      echo
      echo "<!-- ===== BEGIN ${url} ===== -->"
      if ! curl -fsSL --retry 3 --retry-delay 1 "$url"; then
        echo "<!-- WARNING: failed to fetch ${url} -->"
      fi
      echo
      echo "<!-- ===== END ${url} ===== -->"
      echo
    } >> "$OUTPUT_FILE"
  done

echo "Done. Wrote: $OUTPUT_FILE" >&2
