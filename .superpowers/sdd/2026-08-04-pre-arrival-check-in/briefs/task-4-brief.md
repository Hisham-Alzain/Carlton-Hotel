# Task 4: Icon audit and extraction

**Files:**
- Create: `assets/icons/chk_*.svg` (only the gaps)
- Create: `assets/images/demo_passport.png`
- Modify: none — `assets/icons/` is already a directory glob in `pubspec.yaml:52`

**Interfaces:**
- Produces: asset paths referenced by Tasks 6–14.

**Audit result.** The existing 96 icons already cover most of this feature. Reuse these directly:

| Need | Existing asset |
|---|---|
| Bed type options | `king_bed.svg`, `kingbed.svg`, `queenbed.svg`, `singlebed.svg`, `doublebed.svg`, `twinbeds.svg`, `extrabed.svg` |
| Pillow options | `firmpillow.svg`, `softpillow.svg`, `featherpillow.svg` |
| Mattress options | `firmmattress.svg`, `mediummattress.svg`, `softmattress.svg`, `hotelstandardmattress.svg`, `memoryfoammattress.svg`, `orthopedicmattress.svg` |
| Checklist: completed tick | `check.svg` |
| Checklist: arrival time | `clock.svg` |
| Checklist: special requests | `act_request.svg` |
| Security note, info rows | `info.svg` |
| Booking ref / room tiles | `calendar.svg`, `bed.svg` |
| Concierge card | `concierge_spark.svg` |
| Dining / experiences cards | `clock.svg`, `location.svg`, `star.svg`, `rating.svg`, `cuisine.svg` |
| Dropdown chevron | `date_chevron.svg` |

**Gaps to extract** — these have no existing equivalent:

| New asset | Used by |
|---|---|
| `chk_id_card.svg` | Identity tab header chip, scanner Scan control |
| `chk_scan_frame.svg` | "Tap to scan your ID" card |
| `chk_flash.svg` | Scanner Flash control |
| `chk_upload.svg` | Scanner Upload control, checklist ID row |
| `chk_key.svg` | Room Key tab header chip |
| `chk_phone.svg` | Digital key button |
| `chk_hamburger.svg` | Home header (only if `home_view.dart` has no existing one) |
| `chk_airport_transfer.svg` | Airport Transfer card (only if the design uses a vector, not the raster) |

- [ ] **Step 1: Confirm which gaps are real**

Run: `ls assets/icons | grep -iE "key|flash|phone|upload|scan|id_|menu|burger"`
Any hit means that row is not a gap — reuse the existing file and drop it from the extraction list. `download.svg` is a *download* arrow; do not reuse it for upload unless it is direction-agnostic when opened.

- [ ] **Step 2: Extract the remaining gaps**

Use the Figma MCP `download_assets` tool against file `cpln3bQzXRnpkItKQkJCVs` for the node containing each glyph, taking the `svgAssets` entries. Node references: Identity header chip is inside `75:463`; the scanner control row is inside `75:653`; the Room Key header chip is inside `75:928`; the digital key button is `75:1218`.

- [ ] **Step 3: Inline the `var()` fallbacks — this step is mandatory**

Figma returns these SVGs with fills like `fill="var(--stroke-0, #08414D)"`. **`flutter_svg` renders nothing for a `var()`** — the icon will silently draw as empty space. Substitute the fallback hex in every downloaded file:

```bash
cd assets/icons
sed -i -E 's/var\(--[a-zA-Z0-9_-]+, *(#[0-9a-fA-F]{3,8})\)/\1/g' chk_*.svg
grep -l "var(--" chk_*.svg   # must print nothing
```

- [ ] **Step 4: Add the demo passport image**

Export node `75:757`'s captured-ID card image (or the passport inside `75:653`) as PNG at scale 2 via `download_assets`, and save it to `assets/images/demo_passport.png`. This single asset backs all three scanner stages.

- [ ] **Step 5: Verify every asset loads**

Run: `flutter analyze`
Expected: 0 issues

Then confirm each new file is non-empty and well-formed:

```bash
for f in assets/icons/chk_*.svg; do echo "$f $(wc -c < "$f")"; done
```

Expected: every file > 100 bytes.

- [ ] **Step 6: Commit** *(only with authorization)*

```bash
git add assets/icons assets/images/demo_passport.png
git commit -m "feat(check-in): extract check-in icons and demo passport asset"
```

---

