# Task 2 — MO2 installation documentation

Updated `README.md` only within the install/update section. The MO2 ZIP is now the single recommended download and is described as already containing the versioned DWPkg. The instructions say to install and enable it as an ordinary MO2 mod, keep `CHIM` at the data root, load a save with CHIM running and connected, then refresh Automatic Game Plugin Sync status to verify the installed server version.

The README distinguishes the unchanged published 0.1.16 ZIP from a rebuild using the metadata fix. It explains that `validated=true` overrides MO2's installed invalid-content flag for CHIM-only data but does not prove sync; MO2 may overwrite the displayed version and warning-free UI behavior is unverified. It points to the Automatic Game Plugin Sync status refresh after loading a save with CHIM connected, and restores the DWPkg filename rename needed for version detection. DWPkg and repository TAR are labeled as alternatives; checksum verification is optional. The stale “Install 0.1.10” instruction now refers to 0.1.16.

## Verification and limits

- Reviewed the README diff and checked that the 0.1.10 upgrade instruction is gone.
- No automated test was run for this prose-only change. The package builder/archive behavior belongs to Task 1; MO2 UI, installation, game, and server sync were not tested here.
- No release asset, manifest, version pin, or Plugin Manager contract was changed.

Changed files for this task: `README.md` and this report.
