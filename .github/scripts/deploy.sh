#!/usr/bin/env bash
# Deploys the checked-out theme to one Hostinger site with rsync over SSH. Called by .github/workflows/deploy.yml.
# Env (from the GitHub environment's secrets): SSH_PRIVATE_KEY, SSH_HOST, SSH_PORT, SSH_USER, THEME_PATH.
# DRY_RUN=true lists what would change and changes nothing (no upload, no delete, no cache purge).
set -euo pipefail

for v in SSH_PRIVATE_KEY SSH_HOST SSH_PORT SSH_USER THEME_PATH; do
  if [ -z "${!v:-}" ]; then echo "::error::Secret $v is not set in this GitHub environment."; exit 1; fi
done

# --delete must only ever act inside one theme folder: refuse anything that isn't …/wp-content/themes/<folder>.
THEME_PATH="${THEME_PATH%/}"
if ! [[ "$THEME_PATH" =~ ^/.+/wp-content/themes/[A-Za-z0-9._-]+$ ]] || [[ "$THEME_PATH" =~ /\.\.?(/|$) ]]; then
  echo "::error::THEME_PATH must be an absolute path ending in /wp-content/themes/<theme-folder> (got: $THEME_PATH)."; exit 1
fi
WP_ROOT="${THEME_PATH%/wp-content/themes/*}"

mkdir -p ~/.ssh && chmod 700 ~/.ssh
KEY=~/.ssh/deploy_key
printf '%s\n' "$SSH_PRIVATE_KEY" > "$KEY" && chmod 600 "$KEY"
ssh-keyscan -T 20 -p "$SSH_PORT" -H "$SSH_HOST" >> ~/.ssh/known_hosts 2>/dev/null
SSH=(ssh -i "$KEY" -p "$SSH_PORT" -o BatchMode=yes -o IdentitiesOnly=yes -o ConnectTimeout=20)
REMOTE="$SSH_USER@$SSH_HOST"

# The themes folder must exist; the target is either new or already this theme (never another folder's content).
"${SSH[@]}" "$REMOTE" bash -s -- "$THEME_PATH" <<'REMOTE_CHECK'
set -e
t="$1"
[ -d "$(dirname "$t")" ] || { echo "::error::$(dirname "$t") does not exist on the server."; exit 1; }
if [ -e "$t" ] && ! grep -q '^Theme Name: CustomImpulse$' "$t/style.css" 2>/dev/null; then
  echo "::error::$t exists but is not the CustomImpulse theme. Refusing to overwrite it."; exit 1
fi
REMOTE_CHECK

OPTS=(-rlptz --delete --exclude-from=.distignore --itemize-changes)
[ "${DRY_RUN:-false}" = "true" ] && OPTS+=(--dry-run)
echo "Deploying $(git rev-parse --short HEAD) to $THEME_PATH${DRY_RUN:+ (dry run: $DRY_RUN)}"
# Trailing slashes: copy the folder's content into THEME_PATH; --delete stays inside THEME_PATH.
rsync "${OPTS[@]}" -e "${SSH[*]}" ./ "$REMOTE:$THEME_PATH/"

if [ "${DRY_RUN:-false}" = "true" ]; then
  echo "Dry run: nothing was changed on the server."; exit 0
fi

# LiteSpeed cache purge through WP-CLI when both are available; otherwise a reminder.
if "${SSH[@]}" "$REMOTE" "command -v wp >/dev/null && wp --path='$WP_ROOT' plugin is-active litespeed-cache >/dev/null 2>&1"; then
  "${SSH[@]}" "$REMOTE" "wp --path='$WP_ROOT' litespeed-purge all"
  echo "LiteSpeed cache purged."
else
  echo "::warning::Could not purge the LiteSpeed cache automatically (WP-CLI or the LiteSpeed Cache plugin not found). Purge it in WordPress → LiteSpeed Cache → Toolbox → Purge All, or in hPanel → Websites → Cache Manager."
fi
