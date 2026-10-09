# Lead judgment — MO2 packaging investigation

## Accepted findings

The current package-only ZIP uses CHIM's documented native sync path. Public CHIM-Custom and CHIM-iNeed ZIPs contain genuine SKSE/Papyrus content and no metadata/FOMOD; Promise Engine uses the same package-only layout and also lacks both. These comparisons do not prove warning-free installation.

The smallest supported candidate for the reported MO2 warning is a purpose-built root meta.ini alongside the unchanged DWPkg, including release identity and validated=true. The archive checker accepts root .ini files, while the installed-mod tree excludes meta.ini and consults the explicit validated override for the invalid-data flag. No FOMOD or new game component is needed for that candidate. This is source-level evidence, not runtime proof of a clean install or upgrade.

MO2 can overwrite the packaged version during installation; displayed version is a separate remaining verification question. CHIM does not consume meta.ini; it discovers a versioned package on save load and probes/uploads to the server. Metadata therefore cannot diagnose the separately reported menu-read error.

## Review and limits

Lead listed all three downloaded public ZIPs and the current PCV ZIP, reviewed the builder and README, traced manual validation, the Skyrim checker, installed-root exclusion, metadata override and version handling, and checked the pinned native sync caller and scanner. A mistaken initial inference that meta.ini cannot affect the installer was corrected after tracing extension acceptance. Broken rendered-page source anchors in the delegated MO2 report were returned to the same owner for correction.

No product files, package bytes, release pins, live installation or server state changed. Task evidence and lessons only. Native MO2 checks were replaced with online research at the user's direction. No warning-free UI or CHIM sync runtime result is claimed. The current README's stale 0.1.10 upgrade instruction is an additional confirmed documentation finding.
Final review: the same owner corrected all misleading source anchors and clarified the known MO2 2.5.2 file version versus untested binary behavior. Lead inspected the corrected report and accepts its bounded findings. Tracked diff contains only tasks/todo.md and tasks/lessons.md; other new current-task files are investigation evidence. git diff --check passed; HEAD remains 1de11b22b7d8603143070f0f884e3c41eb28d054.
