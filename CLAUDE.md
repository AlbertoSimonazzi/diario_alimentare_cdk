# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Italian-language **diario alimentare carnivoro** (carnivore-diet food diary). Plain PHP app served via XAMPP at `http://localhost/Mondo/`. No framework, no composer, no database, no build step. UI labels, code identifiers, and storage are all in Italian — keep that convention.

## Commands

- **Serve**: start Apache from the XAMPP Control Panel, open `http://localhost/Mondo/`.
- **Lint PHP**: `& "C:\xampp\php\php.exe" -l <file>` (PowerShell). This is the only available check — there are no tests.

## Architecture

Three source files plus a JSON data store:

- [index.php](index.php) — entire app: POST handler, page render, inline JS for the cascading category→food dropdown and live macro preview. Follows **POST-Redirect-GET**: every POST ends with `header('Location: ...')` so refresh never resubmits. Read this before changing form behavior.
- [alimenti.php](alimenti.php) — pure-data: `return` of a nested array `[categoria => [nome => ['kcal' => N, 'p' => N, 'g' => N]]]` with **macros per 100 g**. The same structure is shipped to the browser via `json_encode($LIBRERIA)` and consumed by the inline JS — keys (`kcal`, `p`, `g`) and category/food names must match exactly between PHP and JS. To add foods, edit this file only; no other code change needed.
- [style.css](style.css) — all styling. `index.php` has no `<style>` block.
- `data/diario.json` — entry list, auto-created on first save by `salva_dati()`. Each entry stores its **computed** kcal/proteine/grassi (not just a reference to the library), so editing values in [alimenti.php](alimenti.php) never retroactively changes historical entries.

### Conventions worth knowing

- **`PASTI` constant** in [index.php](index.php) defines both the allowed meal types and their display order (used via `array_flip` to sort entries within a day).
- **Schema tolerance**: the display path uses `?? ''` / `?? 0` everywhere because earlier test entries in `data/diario.json` may have an older schema (`alimenti` plural, no `categoria`/`grammi`/macros). Don't tighten these without migrating the file.
- **Server is authoritative for macros**: the JS preview is cosmetic; `index.php` recomputes `kcal / proteine / grassi` from `grammi × library[categoria][alimento] / 100` on save. Don't accept client-supplied macro values.
- **IDs**: `bin2hex(random_bytes(6))` — short opaque IDs, used only as a delete key.
