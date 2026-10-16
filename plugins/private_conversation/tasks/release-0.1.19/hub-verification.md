# 0.1.19 CHIM-Plugins hub verification

## Result

The frozen hub staging matches the approved PCV-only change set. This is a source and package-identity review; it does not establish installation, runtime, client-sync, or gameplay behavior.

## Scope and identity

- Hub base: `3feee95f9754b05902da8b77efc7d8454f4fb754`.
- PCV release source: `c7ec6cde8231ebb103ef7d443b09789eb4b29691`, tree `dc76ae7ac1551622767135b47487b6b6118aaa43`.
- The staged index contains 37 changed paths: the 256-file PCV source snapshot and only `README.md`, `docs/deployment-migration.md`, and `docs/development.md` outside it. The staging gate reported full index tree `9012b1ecae0d4d07af9627a225bb611303ba4532`.
- All 256 staged PCV paths have the same path, mode, and Git blob ID as the release source tree; no PCV path is missing or extra. The 20 `server/` payload files also match the accepted DWPkg and flat `chim-plugin.tar.gz` members by Git blob identity.
- All 239 tracked paths outside the PCV subtree and the three shared documents retain their base path, mode, and Git blob IDs. This includes the hub-root Mind Poisoning source. No protected path is staged.

## Package and documentation checks

The accepted DWPkg contains 20 server files plus its manifest and checksum file (22 files total). The flat discovery tar contains exactly the 20 flat server files. Their archive SHA-256 values are `d78a969480375d448afe347142ddd320c66a201ed01ab1e43d858aa490d2ccf6` and `2453de55c50c04c6a167432bb00d02eaa2e42ccf353ba64e71e3f3ebe99d141f`, respectively.

The staged migration guide distinguishes the plain file/legacy manifest version `0.1.19` from discovery's `version=private_conversation-v0.1.19` and `release_tag=private_conversation-v0.1.19`. The README adds the PCV discovery route while preserving the Mind Poisoning 0.1.21 row/discovery paragraph and the Sworn & Scorned 0.1.5 row. The legacy PCV archive remains described as installer-wrapped; the guide tells users not to unpack it manually.

Staged shared-document SHA-256 values:

- `README.md`: `f4ca6a61eee3fbebeafb9b4b797b6201659df2ed213948791928d53c782c2bde`
- `docs/deployment-migration.md`: `9754eb909b1c647823256737d44b56d08d57f5380c04d97c10d3bf038b89231b`
- `docs/development.md`: `b3df8a12210cd167acdb4f67fac30547f343b5d531615e4de033cca4698386d2`

The PCV subtree has no additional files beyond the canonical source snapshot; the hub status scan reported no ignored output. Release evidence files are part of the reviewed snapshot, but no beta-only harness or private build directory was added.

## Limits

The comparison uses Git path/mode/blob identities, which are the release and staging boundary. A preliminary source-ZIP byte comparison was not used as acceptance evidence: that archive is repository-wide, and working-tree line-ending conversion makes it a different comparison. The staged Git-object check does not claim that every pre-stage working-tree byte matched the base. No runtime or test command was run for this review.

Detailed machine-readable counts and hashes are in [hub-evidence.json](hub-evidence.json).
