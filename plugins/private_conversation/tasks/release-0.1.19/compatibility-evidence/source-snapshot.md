# Compatibility source evidence

Read-only source inspection on 2026-10-16. Hashes are SHA-256 unless specified.

## CHIM Plugin Manager snapshot

Local snapshot directory: `C:\Users\Francisco Estaba\AppData\Local\Temp\chim-plugin-review-f9cb8eb-b22979c4aedd4a0fb5389b62942a67b7`. The folder name labels the snapshot `f9cb8eb`; it does not contain `.git` metadata, so this review records exact files rather than asserting a full commit identity.

| File | SHA-256 | Relevant anchors |
| --- | --- | --- |
| `lib/plugin_discovery.php` | `A39CE2F099E9A659EA0F175208B9319833B7AEAE7E58B767E0970ED714007E0C` | package name validation 41-47; standard release channel strip 0 at 419-440; existing-folder owner/resolution at 923-968; generated version/config URL at 1029-1068 |
| `ui/server_plugin_installer.php` | `CAD1AFF04813DBA8122D4888D4223BF579D4DFB5FBDA6C133378D240711225D5` | extraction and generated manifest at 182-209; repository metadata at 217-229; backup/swap at 233-250; resolved install directory at 297-323 |
| `main.php` in discovery snapshot | `3619049E6D54493A77B19C09F8B70420E3C35427545A8BB6E95E93C6FE57EBAC` | hashed for snapshot identity; not used for the extension load-order claim |

The retained pinned CHIM core source is `K:\ActorwrightExchange\artifacts\chim-drama-encounter-20261005\main.php`. Its GitHub contents blob SHA-1 `16ba1c72203539dc781cc788da43ab429b44f215` was independently matched by the lead; `main.php:61` calls `requireFilesRecursively(__DIR__."/ext/","globals.php")` before request processing. This is static source order, not a running server observation.

## Current PCV and Mind Poisoning source

| File | SHA-256 | Relevant anchors |
| --- | --- | --- |
| PCV `server/manifest.json` | `4F23CEED13B7216D3B4AF398924131220F04E719C90C6C2A31797BE490D141E9` | current source version `0.1.19`, repository identity, legacy wrapped asset/strip settings |
| PCV `server/log.php` | `56362C3A9F7940320F4D824B4D112AF7B8FF0FB1287F62FC6B5C879C5CF0EAC7` | version allowlist at 597-615; private path keyed by extension directory at 742-751 |
| PCV `server/log_reader.php` | `9FBE50D46ECBC9064DF80D258439D77CAFCBADF38B0D9B4B6EA83577F2498C97` | projection/version validation at 192-223 |
| PCV `server/state.php` | `AA8D7C4E44D76B8503C81CE5DE636C94D9A31A6EBFB048779C1D54C56B5CC519` | state directory is nested under the private log root at 382-390 |
| PCV `server/reflection.php` | `D33A41331638BE653D7C94119043672D7142E73AC752F8ADB7B7CFC090670540` | MP API-first check and fixed-path fallback at 308-328 |
| PCV `scripts/build-package.py` | `510003AC52BB7239C47D5AF8F69A53D591E45D2626E1895497920BDFD08C38E6` | flat-root archive builder and verifier at 178-267; release builder calls `flat_root=True` at 339 |
| MP `server/manifest.json` | `4DDD4B84A8A8E295A2C6505BD2654676712199272DC10A2753E95977226BFC1D` | local authoring source reports 0.1.21; install identity is unverified |
| MP `server/globals.php` | `D036D5F97E27A21970121677109069D60669BDA20037CE293D60167066C9BE63` | line 5 requires `reflection.php` before request hooks |
| MP `server/reflection.php` | `79C32CE4BACF2A9CF71410E7F87D1B6C5DB34E241936FEAD5E219AA536B56F6E` | exposes namespaced reflection API; this review did not execute it |

## Before/after for the approved whitelist edit

| File | Pre-edit SHA-256 | Reviewed SHA-256 |
| --- | --- | --- |
| PCV `server/log.php` | `60FD05ED1A71E8A45447D0E4D2A29658711491089995DBA4485A977EB2844C89` | `56362C3A9F7940320F4D824B4D112AF7B8FF0FB1287F62FC6B5C879C5CF0EAC7` |
| PCV `server/log_reader.php` | `20E43E24C6AE51CD552AA447DE99454B89D2AF4B8E55AD0DC879E3F151B315A0` | `9FBE50D46ECBC9064DF80D258439D77CAFCBADF38B0D9B4B6EA83577F2498C97` |

The source diff is one character-class addition (`_`) at each existing version validator, retaining each `{0,31}` bound. Static diff/hash inspection only; no PHP fixture or runtime verification was performed.
