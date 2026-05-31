# GASF WordPress Backup Repository

Custom code backup for **germantampabay.com** — German-American Society Friendship of Pinellas County.

## What's in here

| Folder | Contents |
|---|---|
| `snippets/` | All Code Snippets (PHP) — shortcodes, fixes, integrations |
| `css/` | Full SiteOrigin CSS stylesheet (~129KB, 32 sections, CSS custom properties) |
| `theme/` | Custom theme files (MEC event template, etc.) |
| `pages/` | Published page HTML content |
| `docs/` | Crontab, bug reports, session manifests |

## Key snippets

| # | Name | Purpose |
|---|---|---|
| 12 | Welton Brewing Status Blurb | `[welton_status]` time-aware open/close shortcode |
| 13 | Bundesliga Table | `[bundesliga_table]` — live standings via OpenLigaDB |
| 14 | Bayern Scorers | `[bundesliga_scorers]` — Bayern goals from match data |
| 15 | Bundesliga Top Scorers | `[bundesliga_top_scorers]` — league-wide top 10 |
| 16 | MEC Upcoming Dates | `[mec_upcoming_dates]` — generic upcoming events widget |
| 17 | MEC Importer Cron Fix | Patches MEC Advanced Importer scheduled sync bug |
| 18 | MEC Facebook Recurring Event Expander | Expands Facebook recurring events into individual MEC events |

## CSS architecture

All custom CSS uses CSS custom properties defined in `:root`.  
**For a color refresh: edit only the `/* ── GASF component system ── */` block.**

Key tokens: `--gasf-gold`, `--gasf-hero-gradient`, `--gasf-cf-green`, `--gasf-dark-bg`, `--font-display`, `--font-body`

## Backup schedule

Auto-backup runs via server cron — see `docs/crontab.txt`.

## Related repos

- [flinchbot/JPlusAPIArtofPossible](https://github.com/flinchbot/JPlusAPIArtofPossible) — Jabra Demos IT Ops Suite
