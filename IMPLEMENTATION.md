# Catalog experience and Romanian metadata

Baseline: `677560f52fc8786ad8963ab948886b4f9799907f`.

## Work checklist

- [ ] TMDb credentials: private GitHub Actions secret and private PHP configuration; never commit credentials.
- [ ] Independent image resolution, including titles without Romanian translations.
- [ ] Consistent metadata and image URLs across compact exports, title pages and search.
- [ ] Persistent metadata cache, correct cache keys and bounded retries.
- [ ] Initial catalog enrichment in resumable batches; preserve previously verified data on failure.
- [ ] Localized season/episode metadata without changing provider episode membership.
- [ ] Original titles, dates, languages, countries, certifications and source attribution.
- [ ] Curated home, simpler poster cards, coherent favorites and continuation.
- [ ] Relevant recommendations and minimum-vote/content-kind filters.
- [ ] A single responsive stylesheet; keyboard navigation and empty/error states.
- [ ] Subtitle UI binding and honest availability labels.
- [ ] Correct CLI paths, stable release pointers and deployment/rollback process.
- [ ] Validate legacy player inputs; separate legacy static variants from the PHP application.
- [ ] Automated checks, representative live checks, backup and hosting publication.

## Boundaries

TMDb supplies existing translations, not universal translation or video/subtitle availability.
Missing facts remain missing. Image binaries and video files are not cached by this project.
GitHub Pages publishes generated data; the PHP hosting release is deployed separately.

## Verification log

Baseline: 23 Python unit tests passed; 14 JavaScript scripts parsed successfully.
Changes, deployment identifiers and remaining blockers will be recorded here.
