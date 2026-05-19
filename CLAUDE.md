# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Italian-language **diario alimentare carnivoro** (carnivore-diet food diary). Plain PHP app served via XAMPP at `http://localhost/Mondo/`. No framework, no composer, no database, no build step. UI labels, code identifiers, and storage are all in Italian — keep that convention.

## Commands

- **Serve**: start Apache from the XAMPP Control Panel, open `http://localhost/Mondo/`.
- **Lint PHP**: `& "C:\xampp\php\php.exe" -l <file>` (PowerShell). This is the only available check — there are no tests.

## Architecture

Three pages + a loader + a JSON store:

- [index.php](index.php) — day view: lists entries grouped by `PASTI`, shows daily totals (kcal/proteine/grassi/carboidrati/P-G ratio) and a delete button per entry. Handles `azione=elimina` only. POST-Redirect-GET.
- [nuova.php](nuova.php) — form to add a new entry. Loads `$LIBRERIA`, ships it to the browser as JSON for the cascading categoria→alimento dropdown and the live preview. Handles `azione=aggiungi`. On success redirects to `index.php?giorno=...`.
- [gestisci.php](gestisci.php) — CRUD on categorie/alimenti (add/rename/delete category; add/edit/delete food). Each row of the library uses HTML5 `form="..."` attribute to share a non-nested form. POST-Redirect-GET.
- [alimenti.php](alimenti.php) — thin loader: returns `json_decode` of `data/alimenti.json`. Also defines `salva_libreria()` used by `gestisci.php`. The returned array has shape `[categoria => [nome => ['kcal' => N, 'p' => N, 'g' => N, 'c' => N]]]` with **macros per 100 g** (`p` proteine, `g` grassi, `c` carboidrati). Keys and category/food names must match exactly between PHP and the inline JS in [nuova.php](nuova.php).
- [style.css](style.css) — all styling. No `<style>` block in any PHP page.
- `data/alimenti.json` — library (committed). Source of truth, edited via [gestisci.php](gestisci.php).
- `data/diario.json` — entry list (gitignored, auto-created by `salva_dati()` in [nuova.php](nuova.php)). Each entry stores its **computed** `kcal/proteine/grassi/carboidrati` so editing the library never retroactively changes historical entries.

### Conventions worth knowing

- **`PASTI` constant** is duplicated in [index.php](index.php) and [nuova.php](nuova.php) — change in both. It defines allowed meal types and their display order (used via `array_flip` to sort entries within a day).
- **Schema tolerance**: the display path uses `?? ''` / `?? 0` everywhere because older entries in `data/diario.json` may lack newer keys (e.g. `carboidrati` was added later, and the very first test entries had `alimenti` plural, no `categoria`/`grammi`/macros). Don't tighten these without migrating the file.
- **Server is authoritative for macros**: the JS preview in [nuova.php](nuova.php) is cosmetic; the PHP handler recomputes `kcal / proteine / grassi / carboidrati` from `grammi × library[categoria][alimento] / 100` on save. Don't accept client-supplied macro values.
- **IDs**: `bin2hex(random_bytes(6))` — short opaque IDs, used only as a delete key.
- **Header nav**: every page has a `<nav class="header-nav">` block linking to the other two pages. Keep it consistent if you add a 4th page.
