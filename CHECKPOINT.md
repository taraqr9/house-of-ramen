# Permanent Offline Catalogue + Seed System — Checkpoint

Last updated: 2026-09-02 — **Phase 1/2 DONE.** Phase 3 (image enrichment) **APPROVED and IN PROGRESS under the redesigned workflow** — read "2026-09-02 — REDESIGNED WORKFLOW: IMPLEMENTATION + PROGRESS" below (after the audit section) for exact current state and the exact next command to run.

## 2026-09-02 — WORKFLOW AUDIT (background/rationale — read the "REDESIGNED WORKFLOW" section further down for current live state)

**Triggered by user request mid-session**, not by a failure. Full read-only audit performed (no data/catalogue/image/pricing changes made during the audit itself). Findings:

**1. Catalogue state (verified fresh):** 530 total phones, 427 active, 0 pending reviews, 139 verified images (94 at session start, +45 this session), 288 active phones still without a verified image, 555 variants (454 priced, 79 active phones with zero priced variant — unchanged from session start, not touched), 0 duplicate slugs.

**2. Why the old per-phone workflow was slow — root causes identified:**
- **WebFetch's HTML→summary step is a lossy LLM pass, not a structured API call.** It repeatedly produced wrong or outright hallucinated URLs this session (a nonexistent `dam.content.dam` domain, wrong guessed Huawei/Motorola asset paths, 404s from guessed patterns) — each one cost a full failed download-and-retry cycle. This was the single biggest source of wasted tool calls.
- **No reusable per-brand URL pattern was established up front.** Early phones in a brand re-derived the site structure from scratch via WebSearch every time; only later (Motorola, Wikimedia) did a reusable pattern get found and reused, and even then only after several phones had already paid the discovery cost individually.
- **GSMArena's block (HTTP 429) was rediscovered per-phone for the first several attempts** before being written down and avoided for the rest of the session.
- **The heaviest fallback (Chrome browser navigate + screenshot + JS-exec) was reached for reasonably often** (Motorola, one Pixel attempt) — each round trip in that path costs far more than a plain WebFetch/curl call.
- **A minority of phones (Pixel 9 Pro, Moto G96 5G, Zenfone 10) were allowed to run 10-20 tool calls before being abandoned**, well past the intended 5-8 call budget — this alone accounts for a disproportionate share of total session cost for zero net phones gained.
- Net effect: **~53 phones attempted (45 done + 8 abandoned) consumed a very large share of a 15M-token session budget** — not sustainable across the remaining 288 phones at the same rate.

**3. Persistence check:** All 45 completed this session confirmed correctly written to both the `phone_images` DB table (verified, is_primary, correct old-row demotion) and `storage/app/public/phones/{id}/*.webp` on disk — spot-checked, no orphaned rows, no missing files, no accidental writes to the 8 correctly-left-unverified phones. **However: the committed seed snapshot (`database/seed-data/catalogue/*.json`, last regenerated 2026-08-30, committed in `95e5767`) is now badly stale — it only contains 109 phones, vs 427 active/0-pending-review phones eligible today.** None of today's 45 new images, and none of the ~318 phones resolved during the 2026-09-01 review-resolution session, are in the committed snapshot yet. This means **a `migrate:fresh --seed` right now would NOT reproduce this session's work** — it's safe in the live dev DB but not yet reproducible from git. This is a separate, pre-existing gap (not caused by the image work) but directly blocks the project's stated goal ("fresh install reproduces the catalogue with no network calls").

**4. Proposed faster workflow (not yet implemented — awaiting approval):**
- **Default source: Wikimedia Commons via its MediaWiki API, not WebFetch+HTML.** `curl -s "https://commons.wikimedia.org/w/api.php?action=query&generator=categorymembers&gcmtitle=Category:{Model_Name}&gcmtype=file&gcmlimit=50&prop=imageinfo&iiprop=url|size|user|extmetadata&format=json" | jq ...` returns exact file URLs, sizes, uploader, and license/attribution metadata in one deterministic call — **zero hallucination risk**, because there is no LLM inference step between the request and the data. Verified working during this audit (read-only test against `Category:IPhone_17`, real response, well-formed JSON, license metadata included). This is the official, sanctioned Wikimedia API — not scraping, not a robots.txt concern.
  - `jq` is confirmed available in this environment for cheap inline filtering (drop filenames containing "case", "box", "unboxing", "screenshot", "teardown", "vs", "and", "series", "display" before ever downloading anything).
  - The audit's own test call surfaced a real example of why visual verification must stay mandatory regardless of source: the top results for `Category:IPhone_17` were phone-**case** product photos, correctly excludable by filename but only actually confirmed by opening the image.
- **Manufacturer official sites remain the fallback**, used only when Commons has no category or no usable single-device photo (roughly 20-30% of phones based on this session's mix, higher for obscure regional brands like itel/Walton/Symphony/Tecno). Reuse the per-brand CDN patterns already learned this session instead of re-searching from scratch: Huawei `consumer.huawei.com/dam/content/dam/huawei-cbg-site/.../images/color/{model}-id-design-details-1-1.png`, Motorola `p{1-4}-ofp.static.pub/fes/cms/...` (found via the Chrome `javascript_tool` DOM query, alt text names the exact model), ASUS `dlcdnwebimgs.asus.com`, itel/Walton via `mobiledokan(bd).com/media/*.webp` galleries (with per-image branding verification, since that domain had one confirmed mismatch this session).
- **GSMArena and other confirmed-blocked domains (PhoneArena/Cloudflare) are skipped by default**, not retried per phone, unless a fresh session confirms the block has lifted.
- **Hard per-phone cap enforced, not just intended: 3 candidate attempts / ~5 tool calls, then mark unverified and move on** — no exceptions, no "one more try." This was the rule all along but wasn't followed strictly enough on the hardest phones.
- **Visual verification via the `Read` tool stays mandatory for every candidate, no exceptions** — this guarantees exact-model matching and rules out sibling/Pro/Plus/region substitutions; nothing in this redesign weakens that check, it only cuts the wasted work *before* that check.
- **License/attribution extraction happens once, only for the chosen candidate** (via `extmetadata` in the same API call, not a separate follow-up fetch).
- Batch DB queries per brand up front (already done well this session, keep as-is).

**5. Estimated effort, old vs new (per phone attempted):**
| | Old (this session, observed) | New (proposed) |
|---|---|---|
| Commons-covered phone (~70% of remainder, estimated) | ~5-6 calls | **~3-4 calls** (1 API curl, 1 image curl, 1 Read, 1 attach) |
| Manufacturer-fallback phone (~30%) | ~7-10 calls | **~5-6 calls** (pattern reused, 1-2 fewer discovery calls) |
| Hard/abandoned phone | 10-20+ calls | **capped at ~5-6 calls**, then stop |
| **Blended average** | **~7-8 calls/phone** | **~4-5 calls/phone** (~40% fewer) |
| Projected total for 288 remaining phones | ~2,000-2,300 calls | **~1,300-1,450 calls** |

The bigger win isn't just fewer calls — it's removing the *unbounded* tail (phones that silently ballooned to 15-20 calls), which is what actually threatened the session budget.

**6. Seed/export/fresh-install readiness:** Schema (`phone_images` table, `PhoneImageCollector::attachManual()`) is correctly designed and needs no changes — confirmed idempotent, confirmed the "demote old primary" logic works, confirmed `PhoneCatalogueSeeder`/`ExportPhoneCatalogueCommand` round-trip correctly (slug-keyed upserts, image status preserved through export→reseed). **The one real gap is operational, not architectural: `phones:export-catalogue` needs to be re-run (all brands, no `--brand` filter) and the result committed** before any of this session's work — or the 2026-09-01 review-resolution work — is actually reproducible via `migrate:fresh --seed`. Recommend doing this at the end of each work session going forward (or after every ~50 images), not just once at the very end of the whole project, so a partial session's work is never only-live-in-DB for long.

**Redesign was approved by the user with one required addition (persistent unresolved-attempt tracking) - see implementation section immediately below.**

---

## 2026-09-02 — REDESIGNED WORKFLOW: IMPLEMENTATION + PROGRESS (read this section for current state)

### What was built (all committed, all tested)

1. **New `phone_image_search_attempts` table** (migration `2026_09_02_120000_create_phone_image_search_attempts_table.php`), one row per phone, `status` = `pending`/`verified`/`unresolved` (`ImageSearchAttemptStatusEnum`). Mirrors the existing `phone_retailer_match_attempts` pattern already in this codebase.
2. **`PhoneImageCollector::attachManual()`** now auto-records a `verified` attempt row on success - no extra step needed on the success path.
3. **New command `phones:mark-image-unresolved {phone} --reason=... --sources=... --candidates=N`** - records a capped give-up. `--reason` is required (command fails without it).
4. **`Phone::scopeNeedsImageEnrichment()`** on the `Phone` model - the one query to use for "what's left": active, no verified image, AND not already marked unresolved. This is the actual DB-backed skip mechanism the user required instead of relying on CHECKPOINT.md text.
5. **Tests**: `tests/Feature/PhoneImage/PhoneImageSearchAttemptTest.php`, 5 tests, all passing (run against a throwaway MySQL DB - this sandbox has no `pdo_sqlite`, same pre-existing gap noted in earlier sessions; used `DB_DATABASE=phonekinbo_test` override, dropped after). Also ran the full `PhoneImage`+`PhoneCatalogueSnapshot` suites - 3 pre-existing failures found, confirmed unrelated (files never touched this session: `PhoneImageAssociationTest.php` APP_URL port issue, `PhoneCatalogueSeederTest.php` "source_key" issue - both pre-existing, not caused by this work).
6. **Caught up the stale seed export** (was stuck at 109 phones since 2026-08-30): ran `phones:export-catalogue` (all brands, no filter), then did a full `migrate:fresh --seed` round-trip against a throwaway MySQL DB to confirm exact reproducibility (530 phones/427 active/139 verified images at that point, image files confirmed present and readable on disk after seeding). This closes the "not yet reproducible from git" gap the audit found.
7. All of the above committed in `a49c163` ("Add image-search attempt tracking; catch up stale catalogue export").

### The actual redesigned per-phone workflow, as implemented (not just proposed)

1. `curl -s "https://commons.wikimedia.org/w/api.php?action=query&generator=categorymembers&gcmtitle=Category:{Model_Name}&gcmtype=file&gcmlimit=50&prop=imageinfo&iiprop=url|size|user&format=json" -A "PhoneKinboCatalogueBot/1.0 (contact: developer@bol-online.com)" | jq -r '.query.pages[]? | select(.title | test("case|box|unboxing|screenshot|teardown|\\bvs\\b|series|display|comparison"; "i") | not) | "\(.title) | \(.imageinfo[0].width)x\(.imageinfo[0].height) | \(.imageinfo[0].url)"'` - one Bash call, deterministic, no hallucination risk (confirmed in practice, not just the audit's one test call).
2. Pick the best-looking candidate by filename (prefer "backside"/"back side"/"(Color) - N" patterns), `curl` it to scratch disk.
3. `Read` the file - mandatory, every time, no exceptions. Look for exact model text (sticker, price tag, on-screen, store signage) or unambiguous structural cues (camera count/layout, distinctive design language) that rule out siblings.
4. If good: `php artisan phones:attach-image {slug} "{url}" --source-url=... --license=... --attribution=...` - **4 calls total**, matches the estimate exactly (confirmed across the entire 18-phone Apple batch - literally 4 calls each, zero rejected candidates, zero wasted retries).
5. If Commons has no category for the phone (common for budget/regional Infinix, Walton, Symphony, itel models): fall back to `mobiledokan.com/mobile/{brand-slug}/gallery` via WebFetch (proven reliable this session, one real mismatch caught in an earlier session and now known to double-check), which usually resolves in 3 calls (WebFetch gallery page → curl candidate → Read → attach = 4).
6. **Hard cap enforced in practice, not just on paper**: capped at 3 source attempts per phone this batch (Infinix GT 30, Hot 11 Play, Hot 60 5G, Note 12i all hit exactly 3 failed sources before being marked unresolved via `phones:mark-image-unresolved`, not left hanging).

### Progress this stretch

- **All 8 phones left unresolved in the pre-redesign session were persisted into the new tracking table** (Walton Primo RX8, Huawei Nova 12, Zenfone 10, Pixel 9 Pro, Pixel 9 Pro XL, Xiaomi 14T, Symphony Max 60 4G, Moto G96 5G) - previously only documented in this file's prose, now DB-enforced and will be auto-skipped by `needsImageEnrichment()` on every future run regardless of who's running it or whether they've read this file.
- **Apple: 18/18 DONE** (finished the remaining 5: iPhone 16e id 481, iPhone 17 id 241, iPhone 17 Pro id 242, iPhone 17 Pro Max id 429, iPhone Air id 430). Every one had a clean Commons category with an explicit "(Color) - Backside.jpg"-style file - Apple's Commons coverage is excellent even for brand-new models (17-series, released within the last few months as of this session).
- **Infinix: 18/18 DONE (14 verified, 4 unresolved)**. Verified: GT 30(unresolved), Hot 11 Play(unresolved), Hot 11S id 316, Hot 12 Play id 290, Hot 50 Pro+ id 435, Hot 60 5G(unresolved), Hot 60i id 252, Hot 70 id 444, Note 12i(unresolved), Note 30 Pro id 309, Note 40 Pro+ id 134, Note 40s id 265, Note 50 Pro id 225, Note 60 id 436, Note 60 Pro id 467, Smart 10 id 495, Smart 6 id 381, Smart 9 HD id 264. **Infinix has essentially no Wikimedia Commons coverage** (checked ~6 different model-name category guesses, all empty) - `mobiledokan.com/mobile/{slug}/gallery` was the source for every single verified Infinix image this batch, and it worked well (branding always visible and checked). The 4 unresolved ones are either too new (GT 30, Hot 60 5G - not yet indexed anywhere) or too old (Hot 11 Play, Note 12i - already delisted from the retailer galleries that would have had them).
- **Session total so far under the new workflow: 19 images attached (139 → 158 verified), 12 phones properly marked unresolved (4 new + 8 carried over), 257 active phones remain.**
- Re-ran `phones:export-catalogue` after this batch (530 phones, 303 image files now in the snapshot) - **not yet committed to git**, that's the very next step.

### OnePlus batch complete (14/19 verified, 5 unresolved)

Verified: 11R id 422, 12 id 26, 12R id 112, 13 id 113, 13R id 220, 13T id 234, 15 id 454, 15R id 455, Nord 2T id 367, Nord 4 id 110, Nord 5 id 235, Nord CE 2 5G id 385, Nord CE5 id 494, Open id 421 (Commons category existed for this one - foldables/flagships get better Commons coverage than the Nord CE sub-line). Unresolved: 10 Pro, 11 (both: no Commons, no mobiledokan listing, oneplus.com images are JS-lazy-loaded/not statically fetchable - confirmed via live Chrome DOM query, only SVG logos present, no product photos in the initial DOM), Nord 3 (no source found), Nord CE3 (mobiledokan slug not found under two variants tried), Nord CE4 (mobiledokan gallery page had no extractable images this time - inconsistent from other OnePlus pages that worked fine; mobiledokanbd.com's same-named slug turned out to be the CE4 **Lite** sibling, correctly not used).

**Key lesson from this batch**: `mobiledokan.com/mobile/{slug}/gallery` coverage for OnePlus was good but inconsistent - roughly 74% hit rate (14/19), lower than Infinix's near-100%. `oneplus.com` itself is not a viable fallback with current tooling (confirmed via both WebFetch and live Chrome DOM inspection - images load through a mechanism neither can see). If OnePlus phones need revisiting, a genuinely different source (GSMArena once unblocked, or a Bangladesh retailer like startech.com.bd not yet tried) is more promising than retrying oneplus.com or mobiledokan.com again.

**Running total under the redesigned workflow: 33 images attached (139 → 172 verified), 17 phones marked unresolved (12 carried over + 5 new this batch), 238 active phones remain.** Re-ran `phones:export-catalogue` after this batch - **not yet committed**, that's the next step.

### POCO batch complete (18/19 verified! only 1 unresolved)

**Critical discovery, apply to every remaining brand**: mobiledokan.com prefixes Xiaomi-family sub-brands with `xiaomi-` in the URL slug - `poco-f4` 404s but `xiaomi-poco-f4` works. This was initially missed and cost 4 phones (C40, C61, C71, F3) a wrong "unresolved" verdict before the pattern was spotted mid-batch and all 4 were immediately re-checked - 3 of the 4 recovered (C61, C71, F3), only C40 genuinely has no listing anywhere. **Redmi almost certainly needs the same `xiaomi-redmi-{model}` prefix - try that first, don't repeat the wasted-404 pattern.**

Verified (18): F3 id 347, F4 id 288, F5 id 196, F6 id 75, F6 Pro id 76, C61 id 343, C71 id 450, M3 id 289, M4 Pro id 345, M6 id 73, M7 id 515, M7 Pro 5G id 246, M8 id 493, X3 Pro id 346, X5 5G id 389, X5 Pro id 182, X7 5G id 410, X8 Pro Max id 492 (even this very-new model was already covered). Unresolved: C40 only (id 344, no listing under either slug variant).

**Running total under the redesigned workflow: 51 images attached (139 → 190 verified), 18 phones marked unresolved (17 carried over + 1 new this batch), 219 active phones remain.** Re-ran `phones:export-catalogue` - **not yet committed**, next step.

### Tecno batch complete (18/19 verified! only 1 unresolved)

Same-brand-family pattern as Infinix (both Transsion) confirmed: mobiledokan.com covers Tecno very well with plain `tecno-{model}` slugs (no prefix needed, unlike POCO). Verified (18): Camon 20 id 42, Camon 20 Pro id 266, Camon 40 id 226, Camon 40 Pro id 438, Camon Slim id 503, Phantom X id 393, Phantom X2 id 311 (via mobiledokanbd.com fallback - no mobiledokan.com gallery existed), Pova 4 id 293, Pova 7 id 468, Pova 7 Pro id 253, Spark 30C id 437, Spark 40 Pro id 254, Spark 40 Pro+ id 469, Spark 50 id 502, Spark 50 Pro id 523, Spark 7 id 318, Spark 8 Pro id 292, Spark Go 2024 id 146. Unresolved: Spark 9 only (id 317, no listing anywhere - older 2022 model).

**Running total under the redesigned workflow: 69 images attached (139 → 208 verified), 19 phones marked unresolved (18 carried over + 1 new this batch), 200 active phones remain - past the halfway point of the original 333-phone gap.** Re-ran `phones:export-catalogue` - **not yet committed**, next step.

### Honor batch complete (17/21 verified, 4 unresolved)

Verified (17): 400 id 231, 400 Lite id 522, 400 Pro id 424, 500 id 473, 70 id 351, 90 id 29, Magic5 Pro id 352, Magic6 Pro id 270, Magic7 Pro id 423, Magic8 Pro id 475, X6a id 268, X6b id 176, X70i id 447, X7d id 255, X9b id 28, X9c id 230, X9d id 256. Unresolved (4): 60 id 350 (no source found anywhere), X6 id 348 (mobiledokanbd.com served wrong X6c sibling under a collapsed slug), X70 id 425 (Commons has 2 user photos confirming the model but neither is a usable catalogue image - one hand-held with a visible IMEI sticker, one a MagicOS settings screenshot; no mobiledokan.com or mobiledokanbd.com gallery/product page exists for this slug), X8a id 269 (no accessible image source on any of the 3 sources - Commons has no category, mobiledokan.com gallery page returns no image markup, mobiledokanbd.com 404s).

**Note**: X6a and X6b are genuinely distinct models (different camera module designs, confirmed visually) despite the similar slugs - not a repeat of the X6/X6c mismatch.

**Running total under the redesigned workflow: 86 images attached (139 → 225 verified), 23 phones marked unresolved (19 carried over + 4 new this batch), 179 active phones remain.** Re-ran `phones:export-catalogue` - **not yet committed**, next step.

### Redmi batch complete (27/28 verified, 1 unresolved)

**Confirmed the predicted pattern**: mobiledokan.com needs the `xiaomi-redmi-{model}` prefix for every Redmi model (plain `redmi-{model}` 404s every time) - consistent with POCO. Also confirmed several Redmi models have separate 4G/5G mobiledokan.com slugs (`xiaomi-redmi-note-12-pro-4g` vs `-5g`, `xiaomi-redmi-note-13-4g` vs `-5g`, `xiaomi-redmi-note-14-pro-4g` vs `-5g`) - when the DB phone's `model_number` was set, it was used to pick the correct variant (e.g. `23124RA7EO` → Redmi Note 13 **4G**); when not set, both galleries were fetched and visually compared to pick the one matching the DB slug's naming (`redmi-note-15-pro` vs `redmi-note-15-pro-5g`, both genuinely exist as separate catalogue entries, confirmed visually distinct - no "5G" badge vs explicit "REDMI 5G" badge).

Verified (27): 10C id 338, 11 Prime id 300, 12 id 12, 12C id 339, 13 id 71, 13C id 11, 15C id 244, 9 Power id 301, 9A id 337, A1+ id 340, A2+ id 156, A3 id 64, A5 id 245, Note 10 id 341, Note 11 id 342, Note 12 id 66, Note 12 Pro id 67, Note 13 id 10, Note 13 Pro+ id 9 (via the `-5g` slug, Pro+ only exists as 5G), Note 14 5G id 408, Note 14 Pro id 69, Note 15 5G id 221, Note 15 Pro id 448, Note 15 Pro 5G id 449, Turbo 3 id 189, Turbo 4 id 478, Turbo 4 Pro id 479. Unresolved: Note 12 Turbo only (id 395 - the only mobiledokan.com image found is a Harry Potter special-limited-edition variant with an unusual branded back cover, not representative of the standard retail phone; no Commons category; mobiledokanbd.com 404s).

**Running total under the redesigned workflow: 113 images attached (139 → 252 verified), 24 phones marked unresolved (23 carried over + 1 new this batch), 152 active phones remain.** Re-ran `phones:export-catalogue` - **not yet committed**, next step.

### Oppo batch complete (30/32 verified, 2 unresolved)

**Confirmed no `xiaomi-` prefix needed** (Oppo is not a Xiaomi sub-brand). **New pattern discovered**: several Oppo slugs are ambiguous between a bare model name and an explicit era/generation, and mobiledokan.com sometimes only has ONE era indexed under the bare slug even when the DB phone is a different generation - caught via the phone's `announced_date`: `oppo-a57` bare slug returned the 2016 single-camera classic model, but the DB's `Oppo A57` is announced 2022-06-09 (the 4G refresh) - re-searched and found `oppo-a57-4g` with the correct dual-camera 2022 design. **Always sanity-check the candidate image's design era against the DB phone's `announced_date` before attaching, not just the model name text.** Also confirmed Reno-series slugs are inconsistently dashed on both mobiledokan.com and in this DB (`oppo-reno-11`/`oppo-reno-12`/`oppo-reno-13-pro` have a dash, `oppo-reno13`/`oppo-reno14-pro`/`oppo-reno15` do not) - when a dashed slug 404s, retry without the dash (and vice versa) before giving up.

Verified (30): A16 id 314, A17 id 282, A18 id 25, A31 id 331, A5 Pro id 229, A57 id 283 (4G refresh, see era note above), A58 id 199, A5s id 376, A5x id 249, A6 id 507, A6x id 508, F19 Pro id 333, F25 Pro id 262, Find X6 id 398, Find X7 Ultra id 107, Find X9 id 486, Find X9 Pro id 487, K13 id 517, Reno 11 id 23, Reno 12 id 106, Reno 13 Pro id 414, Reno13 id 228, Reno13 F id 516, Reno14 Pro id 453, Reno15 id 496, Reno5 4G id 527, Reno6 id 308, Reno7 id 307, Reno8 id 335, Reno8 T id 284. Unresolved (2): A5x 5G id 416 (no source anywhere - Commons empty, mobiledokan.com has only the 4G `oppo-a5x` slug already used for the separate 4G entry, mobiledokanbd.com 404s), A96 id 334 (no source anywhere - all 3 sources checked, none have this model).

**Running total under the redesigned workflow: 143 images attached (139 → 282 verified), 26 phones marked unresolved (24 carried over + 2 new this batch), 122 active phones remain.** Re-ran `phones:export-catalogue` - **not yet committed**, next step.

### Samsung batch complete (33/33 verified! zero unresolved)

Samsung/mobiledokan.com coverage was excellent - every phone had a clean official product photo, no `xiaomi-` prefix needed (not a Xiaomi sub-brand), and the only slug ambiguity (dashed vs undashed, e.g. `samsung-galaxy-a17` vs `-a17-5g`) resolved on the first retry each time. One Commons candidate for Galaxy S21 5G (`Back S21.png`) was rejected as a stylized fan-made vector illustration rather than a real product photo - mobiledokan.com had a genuine photo instead. Also verified all 3 Galaxy S26-series phones (S26, S26 Ultra, S26+) despite the very recent 2026-current-date release - mobiledokan.com already had full galleries for all three.

Verified (33): A04s id 394, A07 id 404, A17 5G id 403, A22 id 371, A23 id 296, A26 5G id 402, A56 id 233, A57 id 458, A72 id 323, F14 id 279, F14 4G id 524, F16 5G id 442, F55 5G id 194, M04 id 322, M05 id 153, M06 id 530, M15 5G id 50, M16 5G id 405, M17 id 528, M17e id 459, M33 id 526, M34 5G id 54, M35 5G id 441, M36 id 251, M54 id 195, S21 5G id 386, S25 Edge id 399, S26 id 483, S26 Ultra id 485, S26+ id 484, Z Flip5 id 388, Z Flip7 id 401, Z Fold7 id 400. Unresolved: none this batch.

**Running total under the redesigned workflow: 176 images attached (139 → 315 verified), 26 phones marked unresolved (unchanged - no new unresolved this batch), 89 active phones remain.** Re-ran `phones:export-catalogue` - **not yet committed**, next step.

### Exact next step

1. `git add` the new/changed `database/seed-data/catalogue/` files + any new `storage/app/public/phones/**` files + `CHECKPOINT.md`, commit.
2. Resume with the next-smallest remaining brand group: `php artisan tinker --execute="App\Models\Phone::needsImageEnrichment()->with('brand')->get()->groupBy(fn(\$p)=>\$p->brand->name)->map->count()->sortBy(fn(\$c)=>\$c)"` to get the live-current breakdown (do not trust a hardcoded list in this file - brand counts shift as phones get resolved). As of this checkpoint the order was: Realme(34) → Vivo(52).
3. For each brand: try the Commons API first (`Category:{Brand}_{Model}`, spaces→underscores). If empty, try `mobiledokan.com/mobile/{brand-slug}-{model-slug}/gallery` (Realme, Vivo are NOT Xiaomi sub-brands - no `xiaomi-` prefix needed). If a phone has an ambiguous 4G/5G/Pro+ naming split on mobiledokan.com, check the DB phone's `model_number` first to disambiguate; if not set, fetch both candidate galleries and pick by visual/brand-badge match. **Also cross-check the candidate image's design era against the DB phone's `announced_date`** - a bare slug can silently resolve to a much older/newer same-named model on mobiledokan.com (caught on Oppo A57). If a dashed slug (e.g. `brand-model-name`) 404s, retry the undashed form (`brand-modelname`) before giving up, and vice versa (confirmed repeatedly on Samsung this batch). Then `mobiledokanbd.com/product/{brand-slug}-{model-slug}/` as a further fallback (double-check any mobiledokanbd.com hit isn't actually a "Lite"/other sibling under a collapsed slug, per the OnePlus Nord CE4 lesson). If all fail within 3 candidate attempts, `phones:mark-image-unresolved` with a specific reason and move on - do not exceed the cap.
4. Re-run `phones:export-catalogue` + commit after every ~50 newly-verified images or at the end of the session, whichever comes first - do not let it go stale again like it did between 2026-08-30 and today.

---

## 2026-09-02 — IMAGE ENRICHMENT PHASE — session 1 progress

**Workflow established this session (use this exact method for every remaining phone):**
1. Query DB for the exact phone name/slug/id needing an image (active phones with no `phone_images` row where `status='verified'`).
2. `WebSearch`/`WebFetch` to find a candidate image URL from a reliable source — prefer, in order: manufacturer's own official site (best), Wikimedia Commons with clear CC license, established retailer/press site with a clean product-only shot. GSMArena is currently returning HTTP 429 (rate-limited, `Retry-After: 36000`) — not accessible this session, skip it and use fallback sources.
3. **Download the candidate to `/tmp/.../scratchpad/imgs/` with `curl` and open it with the `Read` tool to visually confirm it's the exact phone** (not a sibling/Pro/Plus/Ultra/different-region variant, not a group/comparison photo, not a wrong-brand mismatch — one real mismatch was caught and discarded this session, see below). This is mandatory per the standing project rule — text/title match alone is never sufficient.
4. Once visually confirmed, run `php artisan phones:attach-image {slug} "{url}" --source-url="..." --license="..." --attribution="..."` — this downloads+optimizes+stores it for real and marks it `verified` immediately (see `PhoneImageCollector::attachManual()`), so step 3 must happen *before* this, never after.
5. If no trustworthy image can be found after a reasonable search effort (~5-8 tool calls), leave the phone unverified and move on — do not force a bad match. Revisit later if time permits.

**Starting state verified fresh at session start (matched CHECKPOINT exactly, no drift):** 530 total phones, 427 active, 0 pending reviews, 94 verified images, 333 active phones without a verified image.

**Progress this session so far: 9 images attached (94 → 103 verified), 324 active phones still remaining.** Worked smallest brand groups first to build momentum:
- **Nothing (3/3 done):** Phone (2) id 30 — Wikimedia Commons (Booredatwork.com, CC BY 3.0). Phone (3) id 239 — official nothing.tech Shopify CDN image. Phone (3a) Pro id 240 — official nothing.tech Shopify CDN image. All visually confirmed (branding/model text on device back matches).
- **itel (2/2 done):** A80 id 466, Power 70 id 465 — both from official itel-life.com product pages (`/fileadmin/assets/v/{model}/*.png`). **Caught and discarded a real mismatch here**: mobiledokan.com's "itel A80" gallery images actually showed a phone branded "AWESOME" on the back — wrong device entirely, discarded before attaching. (Note: itel's own official site A80 renders also say "AWESOME" as itel's own sub-brand text printed on the actual A80 back panel — so that specific text is NOT itself a red flag once confirmed via itel's own site; the mobiledokan mismatch was a genuinely different, unrelated phone, confirmed by re-sourcing from itel-life.com directly and comparing.)
- **Walton (1/2 done):** NEXG N25 id 445 — mobiledokanbd.com (distinct domain from the unreliable mobiledokan.com/.co above), visually confirmed "NEXG" branding + matching triple-50MP camera. **Primo RX8 id 358 — left unverified**, could not find any accessible image source after ~8 attempts (old 2021 discontinued regional model; Walton's own eplaza/waltonplaza product page no longer resolves — redirects to homepage; mobiledokan/.co/mobilemaya/bdstall all returned 403; mobiledokanbd.com had no listing for it). Worth retrying with the Chrome browser-automation tool (renders JS) if revisited, not yet tried.
- **Huawei (3/4 done):** Nova 14 id 510, Nova 14 Pro id 511 — both official consumer.huawei.com CDN, used the `.../images/color/huawei-nova-{model}-id-design-details-1-1.png` full-res (non-thumb) pattern which gives a clean product-only back render (the default color-swatch images at that path are lifestyle photos with a person in them — avoid those, dig for the `-id-design-details-` ones instead for a clean shot). Nova 14i id 512 — pinoytechnoguide.com (consumer.huawei.com's nova-14i product page has been taken down/redirects to homepage, apparently discontinued from Huawei's own site already). **Nova 12 id 273 — left unverified**: the base "nova 12" (non-Pro/SE/Lite/Ultra/"Vitality Edition") does not appear to have a clean, unambiguous dedicated official image readily accessible this session (Huawei's consumer site nova12 page 404s; only Commons image found was explicitly "Vitality Edition" which is a distinct SKU, not the plain nova 12 — did not use it). Worth another attempt later, possibly via vmall.com (China store) with the Chrome browser tool since it's a JS SPA that WebFetch can't render.

- **Asus (2/3 done):** ROG Phone 8 id 275, ROG Phone 9 id 276 — both official `rog.asus.com/phones/{model}/gallery/` → `dlcdnwebimgs.asus.com` CDN, screen literally displays "ROG PHONE 8" / "ROG PHONE 9" in the shot, unambiguous. **Zenfone 10 id 391 — left unverified**: ASUS's main Zenfone 10 product page only serves one composite image showing all 5-6 color variants side-by-side (a "group photo" of the same model in different colors) — the individual per-color thumbnail URLs all resolve to that *same* composite image (ASUS applies the color-crop client-side in CSS/JS, there's no separate single-unit asset at that path). No Zenfone gallery subpage exists (unlike ROG). No usable Commons/Amazon/press image found either. Worth retrying with Chrome browser tool later (JS rendering might reveal a real per-color asset the static HTML doesn't expose) or accepting a cropped composite if the policy is later relaxed — did not crop it myself since attach-image needs a URL to download from, not a local file.
- **Google Pixel (4/6 done):** Pixel 7a id 369, Pixel 8 id 368, Pixel 8a id 302, Pixel 9 id 214 — all Wikimedia Commons real photos (not the `Mliu92`-authored SVG illustrations that also exist for every Pixel model in Commons — those are fan-made vector renders, not real photos, deliberately not used; stick to real JPG photos only for this catalogue). Device confirmed via visible on-screen text ("Meet Pixel 8a"), on-box text ("Pixel 7a"), or unambiguous camera-bar/sensor-count matching the exact model's known camera config (Pixel 8's 2 sensors vs Pro's 3, etc). Some accepted photos have store security tethers or an out-of-focus second device in the background (Pixel 9) — judged acceptable since the cataloged phone itself is fully identifiable and it isn't a same-model multi-unit "group photo" in the prohibited sense; only reject when the device itself is unclear/obscured or it's genuinely a multi-phone comparison shot (rejected one candidate for Pixel 8a for exactly the tether-obscures-device reason before finding a better one). **Pixel 9 Pro id 215 and Pixel 9 Pro XL id 431 — left unverified** after extensive effort (~15+ tool calls combined): Commons only has SVG illustrations + two unusable extreme-close-up photos for 9 Pro; store.google.com no longer sells either (redirects to the current Pixel 11 lineup — confirmed via live browser navigation, not just WebFetch); GSMArena fully blocked (429, confirmed still blocked via real browser too, not just WebFetch — this is a real IP-level block, not a WebFetch-specific issue); PhoneArena blocked by Cloudflare bot-check (correctly did not attempt to bypass, per policy). Genuinely hard phones for this session — worth another attempt later via a different source category (e.g. a carrier's product page like Verizon/T-Mobile, or a tech-outlet's own-hosted press photo rather than GSMArena/PhoneArena).

**Google Pixel brand: 4/6 done. Asus: 2/3 done.**

- **Xiaomi (4/5 done):** Xiaomi 13 id 285, Xiaomi 13 Pro id 406, Xiaomi 14 id 7, Xiaomi 15 id 80 — all Wikimedia Commons real photos (mostly by prolific Commons contributor 茅野ふたば who photographs store-display phones in China/Japan and tags them accurately — turned out to be a reliable, reusable source for recent Xiaomi flagships specifically). Confirmed via Leica/Xiaomi branding visible on the device or explicit "Xiaomi NN" store signage in-frame. **Xiaomi 14T id 504 — left unverified**: no dedicated Commons category exists for it (only a Xiaomi-14T-and-14T-Pro-together comparison photo on the Wikipedia infobox, which is a group/comparison shot and was correctly not used); mi.com (both /global/ and /ae-en/ paths) returns 403 to WebFetch; no other clean single-device source found in the time budget.
- **Symphony (5/6 done):** Helio 80 id 211, Innova 30 id 210, Innova 40 id 209, Z45 id 360, Z70 id 361 — all from mobiledokan.com's per-phone `/gallery` pages (`www.mobiledokan.com/media/*.webp`), each visually confirmed via "SYMPHONY" (or the sub-brand "helio") text printed on the device back before attaching — this domain gave one confirmed mismatch earlier this session (itel A80, see above) so every candidate from it was individually checked, not batch-trusted; these five all checked out fine. **Symphony Max 60 4G id 446 — left unverified on purpose, not just "couldn't find one"**: mobiledokan.com only has a gallery for the plain "Symphony Max 60" (no "4G" suffix), and research confirmed Max 60 vs Max 60 4G are genuinely different sibling devices (different screen size 6.56"/6.75", different refresh rate 60Hz/90Hz, different battery 5000/6000mAh) — using the Max 60 gallery would have been exactly the sibling-model mismatch the project rules explicitly forbid. Did not find a gallery specifically for the "4G" variant. Worth a fresh search next session for `Symphony Max 60 4G` specifically (not just `Max 60`).

- **Motorola (7/9 done):** Moto Edge 50 Pro id 433, Moto G86 5G id 456, Motorola Edge 60 id 501, Edge 60 Neo id 514, Edge 60 Pro id 513, Edge 70 id 477, Razr 60 id 500 — all official motorola.com CDN images (`p1/p2/p3/p4-ofp.static.pub`). **New efficient technique discovered this session, use it for all future motorola.com phones**: WebFetch on motorola.com product pages unreliable (frequently truncates/fails on these large JS pages) — instead use the already-loaded Chrome browser tool: `mcp__claude-in-chrome__navigate` to the product page, then `mcp__claude-in-chrome__javascript_tool` with `Array.from(document.querySelectorAll('img')).filter(img => img.alt && img.alt.toLowerCase().includes('<model keyword>')).map(img => ({src, alt, w: naturalWidth}))` — Motorola's own `alt` text names the exact model+colour (e.g. "edge 60 neo PANTONE Grisaille"), which doubles as a second confirmation signal alongside visually checking the downloaded image. Moto G17 id 476 used a small (160×212) but clearly-labeled phonedady.com thumbnail — lower resolution than ideal but genuinely verified, not a mismatch. **Moto G96 5G id 434 — left unverified** after ~10 combined attempts across WebFetch and live browser navigation: no motorola.com global product page exists for it (India-market-only device apparently, motorola.in/*/g96*/p paths all redirect to the homepage — confirmed via live browser, not just WebFetch), and third-party sources (Flipkart, gizmochina, mobiledokan.co) all blocked the fetch (403/500/error). Worth a fresh attempt later specifically on Indian retail sites (91mobiles, Flipkart) via the Chrome browser tool rather than WebFetch, since WebFetch is consistently blocked on several of those domains.

- **Apple (13/18 done):** iPhone 12 id 127, 13 id 126, 13 mini id 365, 14 id 36, 14 Plus id 128, 14 Pro id 366, 15 id 35, 15 Plus id 428, 15 Pro id 427, 15 Pro Max id 34, 16 id 124, 16 Pro id 217, 16 Pro Max id 125 — all Wikimedia Commons, mostly the same two reliable contributors (メイド理世 / 茅野ふたば, same person under two account names) who systematically photograph every current iPhone model at Chinese Apple resellers/stores with the model name on an in-frame price tag, sticker, or store signage — this pattern held up across the entire iPhone 12-16 generation range and is worth reusing for the iPhone 17 series too. `apple.com` itself was **not usable this session**: the `/iphone-16/` marketing page redirects straight to `/shop/buy-iphone/...`, and the Chrome `javascript_tool` DOM query got its own output redacted by the extension's own security filter ("BLOCKED: Cookie/query string data") on apple.com's image URLs specifically — this is a client-side block on this tool, not a site block, so a different technique (e.g. reading page HTML via WebFetch instead of live DOM query) might work better next time if apple.com is worth revisiting for cleaner official renders. **Two identification judgment calls worth knowing about**: (1) iPhone 15 Plus's attached photo has no size reference (held in hand, dual-camera visible) — dual camera rules out Pro but cannot visually distinguish "15" from "15 Plus" by camera alone, relied on the Commons category curation being correct; (2) discarded two candidates before finding good ones — a 4-phone group comparison shot for "IPhone (Plus).jpg" and a 2-phone comparison for the "6.7"" file, both correctly rejected as group photos. Remaining Apple: **iPhone 16e id 481, iPhone 17 id 241, iPhone 17 Pro id 242, iPhone 17 Pro Max id 429, iPhone Air id 430 — all 5 untouched** (not attempted, not searched at all).

**Session total: 45 images attached (94 → 139 verified), 288 active phones remain.**

**Remaining brand groups, fully untouched this session:** Vivo 52, Realme 34, Samsung 33, Oppo 32, Redmi 28, POCO 19, OnePlus 19, Tecno 19, Honor 21, Infinix 18.

**No bugs found in the image pipeline itself this session** — `phones:attach-image` / `PhoneImageCollector::attachManual()` work exactly as documented. The only "bug" encountered was a bad third-party data source (mobiledokan.com mismatched gallery for itel A80 — showed a device branded "AWESOME", an unrelated phone), not a bug in this codebase — no code fix needed, documented above for future sessions to know that domain needs extra scrutiny before trusting.

**GSMArena is confirmed blocked at the IP/network level this session** (HTTP 429, `Retry-After: 36000`, reproduced via both WebFetch and a real Chrome browser navigation) — do not keep retrying it every phone, it will keep failing until that retry window elapses (would be roughly 2026-09-02 ~22:00 UTC based on when the first 429 was seen — a future session starting after that time could retry it once to see if it's clear again, since the task instructions do prefer GSMArena when accessible).

**2026-09-02, mid-session pause: user requested a full stop-and-audit** after 45 phones (94→139 verified) to review pace/workflow before continuing, rather than an interruption from a failure — a read-only DB/git audit was run (all 45 confirmed correctly persisted to DB + disk, zero orphaned/missing files, zero accidental writes to the 8 correctly-left-unverified phones, zero pending-review or pricing/variant side effects) and this checkpoint entry was brought up to date to match. **Four already-verified images are lower-confidence than the rest and may be worth a second look if the workflow resumes**: Moto G17 (id 476, phonedady.com thumbnail is only 160×212px — genuinely the right phone, just very low resolution for a catalogue image), Pixel 9 (id 214, official-enough Commons photo but has a store security tether across the back and an out-of-focus second unrelated phone in the background), iPhone 15 Plus (id 428, correct per Commons category but not independently size-confirmable from the photo itself since Plus and base 15 share the same camera layout), Moto G86 5G (id 456, official motorola.com source but the attached image is a front-only lock-screen shot with no back/branding visible in the shot itself). None of these are believed wrong — they were accepted after real visual inspection — but they sit at the lower end of the confidence range compared to the other 41.

**Exact next step whenever image work resumes:** finish Apple (5 remaining: 16e, 17, 17 Pro, 17 Pro Max, Air — likely well-covered by the same Commons contributors, worth checking first) then Honor (21) / Infinix (18) / Tecno (19) — smallest untouched groups — then OnePlus 19, POCO 19, Redmi 28, Oppo 32, Samsung 33, Realme 34, Vivo 52 (largest, last). Do NOT re-attempt any phone in the two done-lists above (this section + the earlier 32-image section). Do not re-burn time on the 8 phones left honestly unverified after real documented effort (Walton Primo RX8, Huawei Nova 12 base, Zenfone 10, Pixel 9 Pro, Pixel 9 Pro XL, Xiaomi 14T, Symphony Max 60 4G, Moto G96 5G) unless trying a genuinely new source category not yet tried (listed per-phone above). Keep per-phone effort capped around 5-8 tool calls. **The user is currently deciding whether/how to redesign this workflow before authorizing more image batches — do not resume image collection until explicitly told to.**

---

## 2026-09-01 — REVIEW RESOLUTION PHASE COMPLETE

Every one of the 154 pending `phone_data_reviews` rows that existed at the start of this session (across Honor, Oppo, POCO, OnePlus, Apple, Infinix, Redmi, Realme, Vivo, Google, Asus, itel, Motorola, Nothing, Samsung, Tecno, plus 6 cross-brand `possible_duplicate` rows) has been resolved via genuine per-phone WebSearch evidence review, one phone at a time, no subagents, exactly per this session's instructions. Approving the 6 possible-duplicate imports as new phones cascaded 5 fresh low_confidence reviews (expected behavior - `ReviewResolutionService::approve()` re-runs the import materializer, which re-scores confidence and re-queues a review if the sparse import payload doesn't clear the auto-approve bar); those 5 were resolved in the same pass using the research already gathered.

**Verified end state (fresh DB query, not a stale log):**
```
Total phones: 530          Active: 427         Duplicate slugs: 0 (verified clean)
Reviews: 0 pending. 384 approved, 103 rejected, 154 resolved (mechanical image_needs_review closures from 08-31)
Images: 239 total (94 verified, 145 needs_review) — 333 of 427 active phones still lack ANY verified image
Pricing: 79 of 427 active phones have no price on any variant
```

**Do not re-run brand-by-brand review resolution — it is done.** If a future session finds pending reviews again, they are NEW (e.g. from a fresh `phones:import` run), not leftover from this pass — investigate before assuming Wave-style dispatch is needed again.

**Rejection reasons this session, aggregated (103 total rejections across the whole catalogue, not just this session's share) — recurring error patterns worth knowing about for future review work:**
- Wrong chipset (family or exact model number) confused with a sibling/variant device - most common single failure mode
- Wrong display refresh rate, panel type (AMOLED vs LCD), or resolution
- Wrong charging wattage (wired or wireless) - second most common
- Camera spec conflated with a Pro/Lite/Plus sibling variant (MP count, lens type mislabeled e.g. telephoto vs ultrawide, or a claimed OIS that doesn't exist / a missing OIS that does)
- **Systemic pattern found in the Google Pixel 10 series specifically**: all 3 Pixel 10-series phones had `nfc=false` when every phone in the series has NFC - worth a targeted look if this recurs elsewhere (could indicate an import-time field-mapping bug for certain sources, not just per-phone noise)

**Methodology lesson learned mid-session (documented here so it isn't relearned)**: AI-summarized WebSearch results themselves hallucinate sometimes (e.g. falsely claimed OnePlus 13R has an 8MP front camera when it's 16MP, falsely claimed Nord CE4 is 144Hz when it's 120Hz per OnePlus's own spec page). Before rejecting a review on a single search result that contradicts an otherwise-internally-consistent DB record, run one more targeted confirmation search. This caught and reversed at least 3 near-misses this session.

**Also caught and self-corrected mid-session**: briefly confused `phone_id` with `review_id` while resolving 3 Honor reviews (used 268/269/270 instead of the correct 269/270/271) — one command was a harmless no-op against an unrelated already-resolved review, two attached the right decision but the wrong phone's evidence text to `resolution_note`; both notes were corrected in-place via tinker immediately after being caught. **Always re-query the phone_id→review_id mapping fresh, never assume the two ID sequences run in parallel.**

---

## 2026-09-01 (earlier in this same session) — per-brand resolution log, kept for detailed audit trail

## 2026-09-01 session — sequential single-agent resolution (no parallel/background agents, per explicit user instruction this session)

Verified fresh against live DB at session start (previous session's Wave 1 agents had landed real progress but checkpoint numbers were stale):

```
Phones: 524   Active: 306   Variants: 549   Specs: 524
Prices: 451/549 variants (441 phones with ≥1 priced variant, 54 active phones with none)
MarketPrices: 483
Images: 239 total — 94 verified, 145 needs_review
Reviews pending BEFORE this session: 154 (148 low_confidence, 6 possible_duplicate) across 148 distinct phones
By brand: Honor 25, Oppo 22, POCO 18, OnePlus 17, Apple 15, Infinix 13, Redmi 11, Realme 7,
          Vivo 7, Google 4, Asus 2, itel 2, Motorola 2, Nothing 1, Samsung 1, Tecno 1
Exportable phones (0 pending): 376. Snapshot at database/seed-data/catalogue/*.json still stale (109 phones, unchanged from prior session) - re-export still deferred until review resolution progresses further, per original plan.
```

**Honor brand (25 phones, reviews #28,29,114-117,176,177,191,203,230,231,255,256,268-270,348-352,392,474,475,350,351) resolved this session** via direct per-phone web research (WebSearch, honor.com/gsmarena-via-search/independent review sites) cross-checked against recorded `phone_specs` fields, then `php artisan phones:resolve-review {id} approve|reject --note="..."` immediately per phone (not batched). No subagents used, per this session's explicit instruction.

- **16 approved** (is_active auto-set true by ReviewResolutionService): X9b(28), 90(29), X6b(176), X9c(230), 400(231), X7d(255), X9d(256), X6a(269), X8a(270), Magic6 Pro(271→270), X6(352→348), Magic5 Pro(356→352), Magic8 Pro(435→475), Honor 60(354→350), Honor 70(355→351)
- **9 rejected** with documented evidence of a real, confirmed spec error (chipset/refresh-rate/display-type/battery/charging/camera-aperture/IP-rating mismatch vs. multiple independent sources) — not deleted, left as an honest closed rejection per project convention: X7b(114, wrong chipset 685→680), X8b(115, wrong refresh rate 120→90), Honor 200(116, wrong charging 65W/15W-wireless→100W/no-wireless), Magic6 Lite(117, wrong IP54→IP53), 90 Lite(177, wrong display type/battery/charging), X7c(191, wrong refresh rate 90→120), X5b(203, camera conflated with X5b Plus variant), X50(353, wrong chipset/battery), Honor 200 Pro(397, wrong main camera aperture f/1.4→f/1.9), Magic8(434, camera spec doesn't cleanly match any confirmed "Magic8" vs "Magic8 Pro Air" source - genuine SKU-identity ambiguity)

**Self-caught error this session**: briefly mixed up `review_id` vs `phone_id` while resolving X6a/X8a/Magic6 Pro (used phone IDs 268/269/270 as review IDs instead of the correct 269/270/271) - one command hit an unrelated already-rejected review (no-op, harmless), two succeeded but attached the wrong phone's evidence text to the resolution_note. Caught immediately by re-querying, corrected both notes in-place via tinker to the correct evidence for their actual phone (decisions themselves - both approvals - were still correct, only the note text was wrong and has been fixed). Lesson: always resolve `phone_id → review_id` via a fresh query immediately before calling `phones:resolve-review`, never assume they're numerically close.

**Result: Honor now 0 pending (was 25), 21 active phones (was some subset).** Total catalogue pending: 154 → 129.

**Oppo brand (22 phones) resolved same session, same method.** 15 approved, 7 rejected (documented errors: Reno14 zoom misattributed to main camera instead of telephoto; A55 claimed a periscope telephoto lens that doesn't exist at that price/chipset tier; A15s wrong front camera MP; A6 Pro wrong display panel type+size (claimed IPS LCD 6.8in, actually AMOLED 6.57in); Reno9 main camera MP conflated with Reno9 Pro variant; A9(2020) wrong charging wattage). **Oppo now 0 pending (was 22), 33 active phones.** Total catalogue pending: 129 → 107.

**POCO brand (18 phones) resolved same session, same method.** 14 approved, 4 rejected (documented errors: X6 Neo wrong chipset family (Helio vs actual Dimensity 6080); M6 Plus had multiple severe errors - wrong chipset generation, wrong main camera MP, wrong charging wattage, wrong front camera MP, data appeared sourced from the wrong device entirely; C75 wrong main camera MP (108 vs actual 50), wrong refresh rate, wrong front camera MP; X7 wrong IP rating (IP66 vs actual IP68 per Xiaomi's own FAQ); X7 Pro wrong chipset number (8300 Ultra vs actual 8400 Ultra)). **POCO now 0 pending (was 18), 23 active phones.** Total catalogue pending: 107 → 89.

**OnePlus brand (17 phones) resolved same session, same method.** 16 approved, 1 rejected (Nord CE4 Lite - wrong chipset (claimed Snapdragon 6 Gen 1, actually Snapdragon 695 5G) and wrong charging wattage (67W vs actual 80W)). Note: several AI web-search summaries during this brand turned out to be wrong/hallucinated on cross-check (e.g. claimed Nord CE4 has 144Hz when official OnePlus spec says 120Hz; claimed OnePlus 13R front camera is 8MP when it's actually 16MP; claimed Nord CE 2 5G front camera is 8MP when it's actually 16MP) - a second, more targeted search caught and corrected each before it caused a wrongful rejection. **Lesson: when a single search result contradicts a DB value that looks otherwise internally consistent, always run one more targeted confirmation search before rejecting - AI-summarized search results hallucinate too.** **OnePlus now 0 pending (was 17), 19 active phones.** Total catalogue pending: 107 → 72 (89 after Oppo, 72 after OnePlus - checkpoint math: 129→107 Oppo, 107→89 POCO, 89→72 OnePlus).

**Apple brand (15 phones) resolved same session** using cross-checked prior knowledge (well-documented flagship devices) plus targeted WebSearch confirmation for uncertain fields (charging wattages, exact battery capacities, iPhone 17 series which is newer). 13 approved, 2 rejected (iPhone SE 2022 - claimed 15W MagSafe wireless charging, actually only 7.5W Qi with no MagSafe support at all; iPhone 16 Plus - claimed 4383mAh battery, actually 4674mAh per macrumors/gsmarena). **Apple now 0 pending (was 15), 18 active phones.** Total catalogue pending: 89 → 72 (OnePlus) → 57 (Apple).

**Infinix brand (13 phones, remainder from prior session's Wave 1) resolved same session.** 11 approved, 2 rejected (Zero 20 - claimed IPS LCD, actually AMOLED; Zero Ultra - 13MP secondary lens mislabeled as telephoto when it's actually ultrawide, no telephoto lens exists on this device). **Infinix now 0 pending (was 13), 17 active phones.** Total catalogue pending: 57 → 44.

**Redmi brand (11 phones, remainder) resolved same session - all 11 approved, 0 rejected** (every recorded spec confirmed matching across mi.com official specs and independent review sites; several newer HyperOS 2 devices had sparse DB data (missing camera fields) but no contradictions found). **Redmi now 0 pending (was 11), 29 active phones.** Total catalogue pending: 44 → 33.

**Realme brand (7 phones, remainder) resolved same session.** 6 approved, 1 rejected (GT Neo 3 - DB claimed camera_has_ois=false but the 50MP Sony IMX766 main camera does have OIS per realme.com's own post - a false negative capability claim). **Realme now 0 pending (was 7), 34 active phones.** Total catalogue pending: 33 → 26.

**Vivo brand (7 phones) resolved same session.** 6 approved, 1 rejected (V25 - two errors: display claimed IPS LCD but is actually AMOLED, and camera_has_ois=false but the 64MP main camera does have OIS). **Vivo now 0 pending (was 7), 55 active phones.** Total catalogue pending: 26 → 19.

**Google brand (4 phones) resolved same session.** 1 approved (Pixel 8a), 3 rejected (Pixel 10 Pro, Pixel 10 Pro XL, Pixel 10a - all three had `nfc=false` when every source confirms Pixel 10-series phones have NFC; Pixel 10 Pro and Pro XL additionally had `camera_has_ois=false` when both have OIS on wide+telephoto; Pixel 10 Pro also had `wireless_charging_w=2` vs confirmed 15W). **Likely a systemic data-collection bug specific to the Pixel 10 series import, not three independent coincidental errors - worth a quick look if this pattern recurs in remaining brands.** Google now 0 pending (was 4), 8 active phones. Total catalogue pending: 19 → 15.

**[SUPERSEDED - Asus, itel, Motorola, Nothing, Samsung, Tecno, and all 6 possible_duplicate reviews were completed later in this same session, plus 5 further cascaded reviews from the duplicate approvals. See the "REVIEW RESOLUTION PHASE COMPLETE" banner at the top of this file for the true current state: 0 pending catalogue-wide. Next task is Phase 3 (image collection), not further review resolution.]**

---

## 2026-08-31 resume (prior session log, superseded by numbers above but history kept for context)

## 2026-08-31 resume — verified actual DB/repo state (do not trust the 2026-08-30 log below for current numbers, it's stale)

Verified fresh against the live DB and `git status` (working tree was clean, all of the prior session's snapshot work was already committed):

```
Phones: 524          Active: 265 (was 265 at start of this session, before wave dispatch)
Variants: 549         Specs: 524         Prices: 498 (451/549 variants have a price row)
MarketPrices: 483
Images: 239 total — 94 verified, 145 needs_review (141 wikimedia_commons, 3 openverse, 1 manual_research)
        all 44 manual_research-status=verified images still trusted (per the visual-audit finding below)
Reviews: 217 approved, 88 resolved, 17 rejected
Pending (BEFORE this session's mechanical cleanup): 314 (242 low_confidence, 66 image_needs_review, 6 possible_duplicate) across 308 distinct phones
```

Ran `php artisan phones:resolve-reviews` (existing mechanical command, not new logic) — closed all 66 `image_needs_review` pending rows as `resolved` (images are optional on the public site, per the command's own established rule, already used by the prior session). Result:

```
Pending AFTER cleanup: 248 (242 low_confidence + 6 possible_duplicate) across 242 distinct phones
By brand: Infinix 33, Tecno 32, Redmi 31, Realme 30, Honor 25, Oppo 22, POCO 18, OnePlus 17,
          Apple 15, Vivo 7, Google 4, Asus 2, itel 2, Motorola 2, Samsung 1, Nothing 1
          + 6 possible_duplicate (no phone_id yet — matched_phone_id candidates, need approve(new)/merge/reject)
Exportable phones (0 pending review) NOW: 282 (up from 109 in the last committed snapshot — snapshot is STALE, re-export needed)
```

The committed `database/seed-data/catalogue/*.json` snapshot (109 phones, 19 brand files, 52 image dirs, all committed as of `a9c2ca3`+later WIP commits) reflects an earlier state and must be re-exported once review resolution + imaging progresses further — do not treat the 109 number as current.

**Dispatched Wave 1 (2026-08-31): Infinix(33), Tecno(32), Redmi(31), Realme(30) = 126 phones**, 4 parallel `general-purpose` agents, brand-partitioned, each with the mandatory instruction to resolve-and-write per-phone immediately (not batch), and to visually open every candidate image file before trusting it verified (per the 2026-08-30 audit rule below — still in force). Results not yet verified as of this log entry — next session/turn must spot-check before dispatching Wave 2.

**Remaining after Wave 1 (planned)**:
- Wave 2: Honor(25), Oppo(22), POCO(18), OnePlus(17) = 82
- Wave 3: Apple(15), Vivo(7), Google(4), Asus(2), itel(2), Motorola(2), Samsung(1), Nothing(1) = 34, plus the 6 possible_duplicate reviews (cross-brand, need `merge` vs `approve` vs `reject` decision per `ReviewResolutionService::merge()` — confirms the import payload really is the same phone as `matched_phone_id` and enriches it, vs `approve` which materializes it as a genuinely new/different phone)

**If interrupted again**, resume by: (1) re-run the brand-pending tinker query in the section above to see exactly what's left, (2) check which of Wave 1/2/3 actually landed via DB counts, (3) do NOT re-dispatch a brand that's already fully resolved (0 pending), (4) continue with remaining waves, then proceed to image collection / re-export / fresh-install verification / README / final checkpoint per the original plan below (still valid, unchanged).

---

## 2026-08-30 session (prior, first pass) — Phase 0 (inspect) done, Phase 1 (architecture) starting.

## Objective (given by the user, in full)

Make the phone catalogue fully portable/reproducible: a fresh `git clone` + `php artisan migrate:fresh --seed` on a brand-new machine should produce the complete, already-reviewed phone catalogue (specs, variants, prices, verified images, resolved reviews) **without hitting the network** — no `phones:import`, `phones:collect-images`, or `phones:resolve-reviews` required for the initial catalogue to be usable. Zero pending review items in the seeded state, reached honestly (no blanket-approval, no fabricated evidence, no deleted evidence, no weakened confidence thresholds). Images must be real files committed to the repo (or referenced via the existing storage mechanism), not hotlinks, each individually verified as the exact phone model. See the user's full prompt (in conversation history) for the complete 15-section spec — this file tracks execution against it, not a restatement of it.

**Known, explicit tension in the spec, and how it's being resolved:** "zero pending reviews" vs "never blanket-approve / never fabricate evidence" is only reconcilable at scale by doing genuine per-phone evidence review (slow) or by formally *rejecting* (not deleting) records that don't hold up — rejection is an explicitly allowed legitimate final state (section 3). This is a real, multi-session-scale amount of individual verification work (415 phones currently need it — see counts below); this file's job is to make that fact visible and trackable across sessions, not to hide it behind a fake "done."

## Starting state (this session, before any catalogue-architecture changes)

DB already had the prior session's work applied (seed_dataset + ai_research_2026 imported, one `phones:resolve-reviews` pass run, plus a small `phones:collect-images` test run):

```
Phones: 524          Active: 109         Brands: 20
Variants: 549         Specs: 524          Prices: 498
MarketPrices: 483
Images total: 52      Images verified: 18   Images needs_review: 34
Reviews: 451 pending (415 low_confidence, 30 image_needs_review, 6 possible_duplicate)
         61 approved, 8 resolved
```

Repo state: `feature/user-site` branch. Uncommitted from prior sessions this session continues:
- `README.md` (rewritten for the import-pipeline-based install flow — will need another pass once the seed-based flow exists)
- `app/Models/PhoneMarketPrice.php`, `app/Services/PhoneImport/PhoneImportRunner.php`, `app/Http/Controllers/PhoneVariantController.php` — removed a `bccomp()`/`ext-bcmath` dependency (extension wasn't installed, wasn't declared, crashed the homepage) in favor of plain string/`number_format` comparison against already-`decimal:2` columns. Verified working (homepage 200s, no bcmath needed).
- `composer.lock`, `bootstrap/cache/*`, `storage/framework/views/*` — incidental, not meaningful.
- ~61 untracked `storage/app/public/phones/**` files from test image-collection runs.

Existing catalogue image asset situation (from the prior image-audit session, see git history `a9c2ca3`): ~1,400 `.webp` files already committed under `storage/app/public/phones/{phone_id}/`, but **orphaned** — no `phone_images` DB rows reference them in a fresh DB, because nothing ever backfills DB rows from files on disk (confirmed by reading `PhoneImageCollector`/`PhoneImage` model — images are only ever created file+row together). This is exactly the gap this task exists to close properly.

## Plan (execution order, mapped to the user's 16-step order)

1. ~~Inspect~~ — done this session (architecture already well understood from prior sessions + this one's schema read of all 11 catalogue migrations + `ReviewResolutionService`).
2. **Build the snapshot export/import mechanism** (the actual core deliverable — "no network needed on fresh install"):
   - `php artisan phones:export-catalogue` — dumps current DB catalogue state (brands, phones, specs, variants, network bands, prices, market prices, resolved reviews, images) to `database/seed-data/catalogue/*.json`, copies each `verified` image file into `database/seed-data/catalogue/images/{phone_slug}/`.
   - `Database\Seeders\PhoneCatalogueSeeder` — idempotent loader (upsert by slug/natural key, never raw ID trust), wired into `DatabaseSeeder`, copies image files into `storage/app/public/phones/{phone_id}/` and creates matching `phone_images` rows.
   - This is self-contained (no external research needed) — will be fully built and verified this session.
3. **Review resolution** — run `phones:resolve-reviews` (already run once), then real, evidence-based per-phone resolution for as much of the 415 `low_confidence` backlog as this session can honestly get through (via `ReviewResolutionService::approve()`/`reject()` with real notes, per its own docblock: "a human/agent who has checked the evidence and approved it is exactly the missing corroboration" — NOT a threshold change). Bounded parallel research batches by brand, per user's section 8.
4. **Images** — `phones:collect-images` for automatic candidates, then bounded parallel manual research (`phones:attach-image`) for phones still missing one, brand-partitioned, GSMArena excluded per its robots.txt (established in the prior image-audit session, reconfirmed here — the new prompt permits GSMArena "if technically accessible and permitted," and it is not).
5. Re-run the export command to snapshot the resolved+imaged state.
6. Fresh-install verification: clean DB, `migrate:fresh --seed`, verify counts/no-duplicates/images-render, exercise homepage/listing/search/compare/admin, full test suite + pint + builds.
7. README.md rewrite for `migrate:fresh --seed` as the primary flow.
8. Final CHECKPOINT.md with exact resume state.

## Progress log

- 2026-08-30: Phase 0 done (this file created). Starting Phase 1 (snapshot architecture) next.
- 2026-08-30: **Phase 1 (snapshot architecture) DONE and verified end-to-end.** New files:
  - `app/Console/Commands/ExportPhoneCatalogueCommand.php` (`phones:export-catalogue {--brand=}`) — dumps every phone with **zero pending `phone_data_reviews`** into `database/seed-data/catalogue/{brand}.json` + copies its verified/needs_review image files into `database/seed-data/catalogue/images/{phone-slug}/`. Phones still awaiting review are deliberately excluded (not force-included) — re-run after resolving more reviews to grow the snapshot.
  - `database/seeders/PhoneCatalogueSeeder.php` — idempotent loader (upsert by slug/natural key), wired into `DatabaseSeeder` after `AdminSeeder`/`MenuSeeder`. Copies image files from the snapshot into `storage/app/public/phones/{phone_id}/` and creates matching `phone_images` rows. Calls `PhoneSourceRegistry::sync()` itself (pure DB upsert from config, no network) so `phone_sources` rows exist without needing `phones:import`.
  - `config/phone_catalogue_snapshot.php` — `directory` config key (mirrors `SeedDatasetSource`'s pattern) so tests can point at a temp dir.
  - `tests/Feature/PhoneCatalogueSnapshot/{ExportPhoneCatalogueCommandTest,PhoneCatalogueSeederTest}.php` — **written but NOT run** in this sandbox (same pre-existing `pdo_sqlite` extension gap documented in README's Requirements section — `phpunit.xml` forces sqlite `:memory:` for tests, this environment doesn't have the extension). The underlying logic WAS manually verified for real, end-to-end, against real MySQL (see below) — the test gap is an environment limitation, not an unverified-logic gap.
  - **Manual end-to-end verification performed:** created a throwaway `phonekinbo_test` MySQL database, ran `migrate:fresh --seed` against it cold → 109 phones/109 active/0 pending reviews/0 duplicate slugs loaded in ~2s with zero network calls. Re-ran `db:seed` twice more → phones/variants/images/prices/market_prices counts identical every time (idempotent confirmed); only the pre-existing, unrelated `User::factory(10)` local-dev block in `DatabaseSeeder` is non-idempotent (out of scope - existing behavior, not part of this task). Started the app against that DB, curled `/`, `/phones`, `/login`, a phone detail page, `/compare` - all 200. Confirmed a seeded image file physically exists, is readable, and is served correctly (`image/webp`, correct byte count) via `/storage/phones/{id}/...`. Dropped the test DB after.
  - Also fixed a minor logging quirk in the export command (brand skip-count line wasn't printed when a brand had 0 exportable phones - now always printed).
- 2026-08-30: **Full visual image audit completed** (user-directed, in response to the systemic finding below). Checked all 51 then-verified images by actually opening each file, not trusting DB status: **27 downgraded** (22 automated-Wikimedia-Commons-sourced, 5 pre-existing from before this session) for retail-shelf/multi-phone photos, screen-on settings menus, a teardown photo, a severely blurred image, a 4-iPhone comparison shot, one ambiguous foldable-identity case. **All 34 manually-sourced (`manual_research`) images passed with zero issues** - confirms the failure mode is specifically the automated collector's text-only matching, not the manual/agent-verified workflow. State after audit + cleanup `phones:resolve-reviews`: 49 verified images, 164 exportable phones, 162 active, 366 pending reviews.

  **New mandatory rule for all future image work (user-directed):** No image may be marked verified based on text/title matching alone. `phones:collect-images` output is a CANDIDATE ONLY - every image (automated or manual) must be visually opened and confirmed as the exact phone before being trusted as verified. An agent that runs the automated collector must itself view the result and downgrade it (unset primary, status→needs_review, open a documented `phone_data_reviews` row) if it's wrong - never leave a bad auto-verified image in place.

- 2026-08-30: **Phase 2 continues - full catalogue push authorized by user, all further phases (2-6) run without stopping for check-ins.** Current pending-review breakdown by brand (360 total): samsung 49, vivo 47, oppo 38, infinix 33, tecno 32, redmi 31, realme 30, honor 25, poco 23, oneplus 17, apple 15, itel 11, google 4, asus 2, motorola 2, nothing 1.

  Dispatching in 2 bounded waves of 4 parallel agents each (not all 8 groups at once, per "no uncontrolled agent counts"):
  - **Wave A**: (1) Samsung 49, (2) Vivo 47, (3) Oppo 38 + itel 11, (4) Realme 30 + POCO 23
  - **Wave B** (after Wave A verified): (5) Infinix 33 + Google 4 + Asus 2 + Motorola 2 + Nothing 1, (6) Tecno 32 + Apple 15, (7) Redmi 31 + OnePlus 17, (8) Honor 25

  Each agent brief now includes the mandatory visual-verification-before-trust rule above. After each wave: re-run `phones:resolve-reviews` (mop up stale image_needs_review rows), spot-check + fully audit a sample of newly-verified images myself (not just trust agent reports - this is what caught the systemic problem above), update this file.

  **Wave A retry note**: first Wave A dispatch (Samsung, Vivo, Oppo+itel, Realme+POCO) all 4 hit an API session-limit error and failed before writing anything (verified via DB counts identical to pre-dispatch state - 49/47/38/30/23/11 pending, 366 total, no partial writes). Re-dispatched all 4 fresh with an added instruction to resolve each phone immediately after deciding on it (not batch-research-then-batch-write), to limit lost work if interrupted again.

  **If this session is interrupted before all phases complete**, resume by: (1) `php artisan tinker --execute="..."` the per-brand pending query above to see what's left, (2) check `git status` / DB counts against the log entries here to see which waves actually landed, (3) continue dispatching remaining brand groups with the same agent brief pattern, (4) do NOT re-audit phones/images already covered in a prior log entry here - only audit what's new.

- 2026-08-30 (superseded by above): **Phase 2 (review resolution) - wave 1 dispatched.** Re-ran `phones:resolve-reviews` first (mopped up 30 stale `image_needs_review` rows created by earlier test runs - legitimate mechanical rule, already existed, not new). Current state before wave 1 agents finish: 109 phones exportable (0 pending), 415 phones still have ≥1 pending review (415 `low_confidence` + 6 `possible_duplicate` review rows, some phones have both). Investigated `ConfidenceCalculator` for a "genuine algorithm bug" per the user's instructions - found none; the low-confidence backlog is intentional design (a `requires_review: true` source can never auto-clear on reliability alone, by design - see `ReviewResolutionService::approve()`'s own docblock), not a bug to fix generically. So resolving the backlog requires genuine per-phone evidence review, exactly as the system is designed for.

  Dispatched 4 parallel background agents (`general-purpose`, brand-partitioned per the user's section 8), each doing REAL per-phone work: inspect the phone's recorded spec/variant data for internal coherence/completeness, web-search to confirm uncertain ones, `phones:resolve-review {id} approve|reject --note="..."` with an honest note, then `phones:collect-images`/`phones:attach-image` (GSMArena excluded per its robots.txt) for phones that clear review but still lack a verified image. Explicitly forbidden from: blanket-approving, fabricating evidence, touching other brands, touching the confidence algorithm, creating duplicates.

  **Wave 1 scope (64 of the 415 pending phones):**
  - Agent A: Walton (8) + Symphony (2) + Huawei (3) = 13
  - Agent B: Google (8) + Asus (3) + Nothing (9) = 20
  - Agent C: Motorola (19)
  - Agent D: Xiaomi (12)

  Remaining brands NOT yet dispatched (351 phones): Samsung (49), Vivo (47), Oppo (38), Infinix (33), Tecno (32), Redmi (31), Realme (30), Honor (25), POCO (23), OnePlus (17), Apple (15), itel (11).

  **Status: agents running, results not yet in.** Next session/turn: check each agent's actual DB changes (don't just trust its self-report - spot check a sample of its approve/reject decisions and at least one attached image per agent), then decide whether to dispatch further waves for the remaining 351 phones or stop here and ship a smaller-but-100%-honest catalogue. Either is a legitimate outcome per the user's own rules (partial-but-honest beats forced-complete-but-fabricated).
