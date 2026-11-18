# Catalog experience and Romanian metadata

Baseline: `677560f52fc8786ad8963ab948886b4f9799907f`.

## Work checklist

- [ ] TMDb credentials: private GitHub Actions secret and private PHP configuration; never commit credentials.
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
- [ ] Automated checks, representative live checks, backup and hosting publication.

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
