# Steinmetz Dashboard Notes

Adds an admin-only notes box to the WordPress dashboard. Notes are stored in the `dashboard_notes_text` option.

## Updates

The plugin updates itself from this repo's GitHub releases, so it doesn't need Git Updater. It reads the tag that `github.com/…/releases/latest` redirects to (no API, so no anonymous rate limit) every 6 hours (or when you click **Dashboard → Updates → Check again**) and installs the `steinmetz-dashboard-notes.zip` attached to the release. The `Update URI` header stops WordPress from matching this plugin to the unrelated `dashboard-notes` plugin on wordpress.org.

## Releasing

1. Bump `Version:` and `SDN_VERSION` in `steinmetz-dashboard-notes.php`.
2. Commit.
3. Run `./release.sh "What changed"`. It tags the commit, pushes, and creates the GitHub release with the zip attached.
