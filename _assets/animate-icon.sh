#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# animate-icon.sh – Generate animated icon GIF / MP4 from source layers
#
# Composites the Blades, Hand, and (optionally) Shared-text layers into a
# rotating-aperture animation using ffmpeg.
#
# Usage examples:
#   ./animate-icon.sh                           # 256px GIF with text
#   ./animate-icon.sh --no-text                 # 256px GIF without text
#   ./animate-icon.sh --size 440 --no-text      # 440px GIF without text
#   ./animate-icon.sh --format both             # GIF + MP4 with text
#   ./animate-icon.sh --format mp4 --mp4-fps 30 # MP4 at 30 fps
#   ./animate-icon.sh --direction cw            # clockwise rotation
#   ./animate-icon.sh --duration 3 --loops 2    # 3s with 2 full rotations
# ---------------------------------------------------------------------------
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# ── Defaults ──────────────────────────────────────────────────────────────
SIZE=256
DURATION=6
GIF_FPS=12.5
MP4_FPS=60
FORMAT="gif"            # gif | mp4 | both
DIRECTION="ccw"         # ccw (counter-clockwise) | cw (clockwise)
LOOPS=1                 # full rotations within DURATION
BG_COLOR="white"
MAX_COLORS=256
DITHER="sierra2_4a"     # sierra2_4a | bayer | floyd_steinberg | none
NO_TEXT=false
OUTPUT=""                # auto-generated when empty
BLADES="${SCRIPT_DIR}/Blades-256.png"
HAND="${SCRIPT_DIR}/Hand-256.png"
TEXT="${SCRIPT_DIR}/Shared-256.png"

# ── Usage ─────────────────────────────────────────────────────────────────
usage() {
  cat <<EOF
Usage: $(basename "$0") [OPTIONS]

Layer inputs:
  --blades PATH        Blades layer PNG            (default: Blades-256.png)
  --hand PATH          Hand layer PNG              (default: Hand-256.png)
  --text PATH          Text layer PNG              (default: Shared-256.png)

Animation:
  --size N             Output width & height in px (default: $SIZE)
  --duration SECS      Total duration in seconds   (default: $DURATION)
  --loops N            Full rotations per duration  (default: $LOOPS)
  --direction DIR      Rotation: ccw | cw           (default: $DIRECTION)

Output:
  --format FMT         gif | mp4 | both             (default: $FORMAT)
  --output PATH        Output filename (auto if empty; extension added per format)
  --no-text            Omit the text layer

GIF options:
  --gif-fps N          GIF frame rate               (default: $GIF_FPS)
  --max-colors N       Palette size (2-256)         (default: $MAX_COLORS)
  --dither METHOD      sierra2_4a | bayer | floyd_steinberg | none
                                                    (default: $DITHER)
  --bg-color COLOR     Background color/hex         (default: $BG_COLOR)

MP4 options:
  --mp4-fps N          MP4 frame rate               (default: $MP4_FPS)

  -h, --help           Show this help
EOF
  exit 0
}

# ── Parse arguments ───────────────────────────────────────────────────────
while [[ $# -gt 0 ]]; do
  case "$1" in
    --size)        SIZE="$2";       shift 2 ;;
    --duration)    DURATION="$2";   shift 2 ;;
    --gif-fps)     GIF_FPS="$2";    shift 2 ;;
    --mp4-fps)     MP4_FPS="$2";    shift 2 ;;
    --format)      FORMAT="$2";     shift 2 ;;
    --direction)   DIRECTION="$2";  shift 2 ;;
    --loops)       LOOPS="$2";      shift 2 ;;
    --bg-color)    BG_COLOR="$2";   shift 2 ;;
    --max-colors)  MAX_COLORS="$2"; shift 2 ;;
    --dither)      DITHER="$2";     shift 2 ;;
    --no-text)     NO_TEXT=true;    shift   ;;
    --output|-o)   OUTPUT="$2";     shift 2 ;;
    --blades)      BLADES="$2";     shift 2 ;;
    --hand)        HAND="$2";       shift 2 ;;
    --text)        TEXT="$2";       shift 2 ;;
    -h|--help)     usage ;;
    *) echo "Unknown option: $1" >&2; exit 1 ;;
  esac
done

# ── Validate ──────────────────────────────────────────────────────────────
for f in "$BLADES" "$HAND"; do
  [[ -f "$f" ]] || { echo "ERROR: layer not found: $f" >&2; exit 1; }
done
if [[ "$NO_TEXT" == false && ! -f "$TEXT" ]]; then
  echo "ERROR: text layer not found: $TEXT  (use --no-text to skip)" >&2
  exit 1
fi
command -v ffmpeg >/dev/null 2>&1 || { echo "ERROR: ffmpeg is required" >&2; exit 1; }

# ── Build output basename ─────────────────────────────────────────────────
if [[ -z "$OUTPUT" ]]; then
  SUFFIX=""
  [[ "$NO_TEXT" == true ]] && SUFFIX="-no-text"
  OUTPUT="${SCRIPT_DIR}/icon-${SIZE}x${SIZE}${SUFFIX}"
fi
# Strip any extension the user may have added - we append per format
OUTPUT_BASE="${OUTPUT%.*}"
# If user gave e.g. "foo.gif", keep the base; we add the right extension later

# ── Rotation expression ──────────────────────────────────────────────────
# ccw = negative angle (default, matches original); cw = positive
if [[ "$DIRECTION" == "cw" ]]; then
  ANGLE_EXPR="2*PI*${LOOPS}*t/${DURATION}"
else
  ANGLE_EXPR="-2*PI*${LOOPS}*t/${DURATION}"
fi

# ── Helper: build the compositing filter graph ───────────────────────────
# Produces a single output labelled [final] at the requested SIZE.
build_filter() {
  local fps="$1"
  local F=""

  # Background
  F+="color=${BG_COLOR}:${SIZE}x${SIZE}:d=${DURATION}:r=${fps}[bg];"

  # Scale & rotate blades
  F+="[0:v]format=rgba,scale=${SIZE}:${SIZE}:flags=lanczos,"
  F+="rotate=${ANGLE_EXPR}:c=0x00000000:ow=${SIZE}:oh=${SIZE}[blades];"

  # Scale hand
  F+="[1:v]format=rgba,scale=${SIZE}:${SIZE}:flags=lanczos[hand];"

  # Composite: bg + blades + hand (+ text)
  F+="[bg][blades]overlay=0:0:format=auto[tmp1];"
  F+="[tmp1][hand]overlay=0:0:format=auto"

  if [[ "$NO_TEXT" == false ]]; then
    F+="[tmp2];"
    F+="[2:v]format=rgba,scale=${SIZE}:${SIZE}:flags=lanczos[txt];"
    F+="[tmp2][txt]overlay=0:0:format=auto"
  fi

  echo "$F"
}

# ── Generate GIF ──────────────────────────────────────────────────────────
generate_gif() {
  local out="${OUTPUT_BASE}.gif"
  local frames
  frames=$(python3 -c "print(int(${DURATION} * ${GIF_FPS}))")

  local filter
  filter="$(build_filter "$GIF_FPS")"

  # Append palette split for high-quality GIF
  local dither_opt="dither=${DITHER}"
  [[ "$DITHER" == "none" ]] && dither_opt="dither=none"

  filter+=",split[s0][s1];"
  filter+="[s0]palettegen=max_colors=${MAX_COLORS}:stats_mode=full[p];"
  filter+="[s1][p]paletteuse=${dither_opt}"

  local inputs=(-loop 1 -t "$DURATION" -i "$BLADES" -loop 1 -t "$DURATION" -i "$HAND")
  [[ "$NO_TEXT" == false ]] && inputs+=(-loop 1 -t "$DURATION" -i "$TEXT")

  echo "→ Generating GIF: $out  (${SIZE}x${SIZE}, ${GIF_FPS} fps, ${frames} frames)"
  ffmpeg -y "${inputs[@]}" \
    -filter_complex "$filter" \
    -frames:v "$frames" \
    "$out" 2>/dev/null

  echo "  ✓ $(du -h "$out" | cut -f1 | xargs) - $out"
}

# ── Generate MP4 ──────────────────────────────────────────────────────────
generate_mp4() {
  local out="${OUTPUT_BASE}.mp4"

  local filter
  filter="$(build_filter "$MP4_FPS")"

  local inputs=(-loop 1 -t "$DURATION" -i "$BLADES" -loop 1 -t "$DURATION" -i "$HAND")
  [[ "$NO_TEXT" == false ]] && inputs+=(-loop 1 -t "$DURATION" -i "$TEXT")

  echo "→ Generating MP4: $out  (${SIZE}x${SIZE}, ${MP4_FPS} fps)"
  ffmpeg -y "${inputs[@]}" \
    -filter_complex "${filter}[vout]" \
    -map "[vout]" \
    -c:v libx264 -profile:v high -pix_fmt yuv420p \
    -movflags +faststart \
    -t "$DURATION" \
    "$out" 2>/dev/null

  echo "  ✓ $(du -h "$out" | cut -f1 | xargs) - $out"
}

# ── Run ───────────────────────────────────────────────────────────────────
echo "Animate icon  •  ${SIZE}x${SIZE}  •  ${DURATION}s  •  ${LOOPS} loop(s)  •  ${DIRECTION}"
[[ "$NO_TEXT" == true ]] && echo "  (text layer excluded)"

case "$FORMAT" in
  gif)  generate_gif ;;
  mp4)  generate_mp4 ;;
  both) generate_gif; generate_mp4 ;;
  *)    echo "ERROR: unknown format '$FORMAT' (use gif, mp4, or both)" >&2; exit 1 ;;
esac

echo "Done."
