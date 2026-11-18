# Catalog experience and Romanian metadata

Baseline: `677560f52fc8786ad8963ab948886b4f9799907f`.

## Work checklist

- [x] TMDb credentials: private GitHub Actions secret and private PHP configuration; never commit credentials.
- [x] Independent image resolution, including titles without Romanian translations.
- [x] Consistent metadata and image URLs across compact exports, title pages and search.
- [x] Persistent metadata cache, correct cache keys and bounded retries.
- [ ] Initial catalog enrichment in resumable batches; preserve previously verified data on failure.
- [x] Localized season/episode metadata without changing provider episode membership.
- [x] Original titles, dates, languages, countries, certifications and source attribution.
- [x] Curated home, simpler poster cards, coherent favorites and continuation.
- [x] Relevant recommendations and minimum-vote/content-kind filters.
- [x] A single responsive stylesheet; keyboard navigation and empty/error states.
- [x] Subtitle UI binding and honest availability labels.
- [x] Correct CLI paths, stable release pointers and deployment/rollback process.
- [x] Validate legacy player inputs; separate legacy static variants from the PHP application.
- [x] Automated checks, representative live checks, backup and hosting publication.

## Boundaries

TMDb supplies existing translations, not universal translation or video/subtitle availability.
Missing facts remain missing. Image binaries and video files are not cached by this project.
GitHub Pages publishes generated data; the PHP hosting release is deployed separately.

## Verification log

Baseline: 23 Python unit tests passed; 14 JavaScript scripts parsed successfully.
Changes, deployment identifiers and remaining blockers will be recorded here.

## Publication and checks — 18 November 2026

- Published PHP interface v15 on filme.bratu-marian.com, preserving private config and local data.
- Backup: `/domains/filme.bratu-marian.com/pre-v15-20261117.zip`, outside public_html.
- Source commit `4f9f14d5e6090bd15b294ec22ac195da43bfd7b5`; GitHub validation run 44208990164 passed.
- 26 Python tests passed, 43 PHP files validated, 9 remaining embedded/standalone JavaScript scripts and SVG validated.
- Live: home, search suggestions, film poster 260×390, favorite consent/save/remove, TV season switch (season 8 loaded 6 episodes), mobile home/film/TV at a 390px iframe viewport. Film mobile content width equals viewport (375px excluding scrollbar).
- Catalog build run 44208990261 stopped because provider latest endpoints returned HTTP 403. The previous verified catalog was retained; no incomplete data was published.
- TMDb credentials and initial enrichment are still pending. Full Romanian coverage depends on translations available at TMDb.

Final interface source: `38976cbdb6d0b017ed1cc406320a2fcedb0d74f5`; validation run 44209764627 passed. Asset query versions now use content hashes. Public previews were moved outside public_html; ZIP/backup/log downloads and old preview/debug PHP routes are denied. Catalog retry run 44209764579 is in progress. GitHub secret setup is waiting for interactive account authentication; no TMDb key was received or committed.

TMDb activated on 18 November 2026: repository secret verified by run 44334559507 (`configured=true`, `api_ok=true`); private PHP configuration installed with permissions 0600 and verified HTTP 403 for direct access. Live Game of Thrones page now shows a Romanian synopsis, Romanian episode synopses and a TMDb poster. Temporary credential files removed. Initial 2000-title/200-season enrichment run 44334296341 is in progress. Runtime episode metadata now prefers the requested TMDb localization over an existing IMDb primary episode name.

## Interface v16 — requested premium presentation

- SVG icons in the primary, footer and mobile menus; no emoji navigation.
- Five highest-rated feature films added today in Romanian time, completed from the preceding seven days if necessary. Deterministic vote/date tie breakers, a two-minute cache, explicit tabs with arrow/Home/End keyboard support, no automatic rotation.
- Full card titles, equal row/card heights within each grid, consistent 2:3 posters; IMDb badge left and calendar/year badge right; no duration on cards.
- The application's player contains no outgoing provider link, including its error state.
- Romanian interface copy simplified; original-language synopses are available only in an explicitly labelled disclosure when Romanian text is unavailable. Known editorial titles retain correct Romanian diacritics.
- Isolated staging preview tested at desktop and 390px iframe widths. Measured mobile grid: 165px width / 378px height for every card in the tested grid, no clipped titles and no horizontal overflow.
- Live selection independently matched the first five entries from today's feature-film catalog sorted by IMDb rating. Tests cover today's priority, rating order, seven-day cutoff, no TV/specials/unrated/future items, and the Romanian midnight boundary.
- Rollback interface archive remains `/domains/filme.bratu-marian.com/v15-tmdb-release.zip`; private config and data are excluded from v16 installation archives.
