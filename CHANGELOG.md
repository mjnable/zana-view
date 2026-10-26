# Changelog

All Zana packages share one version number and are released together. A release that did
not change this package is not listed.

## 2.2.0 — 2026-10-26

### Added

- `Assets` and the `asset()` helper: versioned asset URLs by modification time, content hash or
  a build manifest (`view.assets.*`, FE-19).
- `PageMeta`: title, description, canonical URL, `hreflang` alternates, Open Graph and JSON-LD,
  printed by layouts as the `meta` global (FE-34).
- The `picture()` helper, when `mj-zana/media` is installed (FE-31).
- `csrf_field()` prints the anti-spam honeypot when it is configured (FE-22).

### Changed

- Requires `mj-zana/http` and `mj-zana/routing` ^2.2.

## 2.1.0 — 2026-10-26

First release. Template folders per module and application, a native PHP engine with escaping
by default, layouts, sections and partials, view helpers and globals, `View` route results, and
templated error pages.
