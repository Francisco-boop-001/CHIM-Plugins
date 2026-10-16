# Private Conversation 0.1.19 — ALPHA

This is an ALPHA release prepared for a controlled live-installation test. CHIM Plugin Manager discovery reads normal published GitHub releases: publish this as non-draft with `prerelease=false`. That technical release flag does not make the ALPHA a beta or stable build. The llama can now reject one more bad target, but it still cannot certify your whole modlist.

## What changed

Solo scenes now apply the existing partner-action target requirement. Partner actions with a missing, malformed, self, or out-of-scene target are rejected; the existing self-directed action exceptions remain available. The action allowlist is unchanged.

Diagnostic writers and readers now both accept `_` in the bounded plugin-version value, so an accepted value is not discarded by either end of the logs path. This is a source compatibility correction; it does not claim that diagnostic logging was exercised on a live server.

The release adds a flat `chim-plugin.tar.gz` asset for CHIM Plugin Manager repository discovery. The legacy candidate-manifest TAR remains wrapped, and the MO2 ZIP still embeds the DWPkg.

The focused serialized-action fixture failed before the one-expression source change and passed after it; PHP lint passed. This checks PCV's post-filter contract, not downstream CHIM/SHARMAT execution or an in-game action. The diagnostic version-parser change is not presented as a runtime logging test.

## Evidence and limits

| Evidence | What it supports | What it does not establish |
| --- | --- | --- |
| Solo action-target regression | RED/GREEN serialized post-filter cases for missing, malformed, self, outsider, valid scene-member, and self-directed action targets. | Model-produced action flow through CHIM/SHARMAT, an installed server, or Skyrim behavior. |
| Diagnostic version parsing | The writer and reader now accept the same bounded plugin-version syntax, including `_`. | Live log creation, retention, display, or server diagnostics behavior. |
| Plugin Manager archive | The builder adds a flat repository archive for discovery alongside the retained wrapped legacy TAR and MO2 ZIP. | The archive alone does not establish an installed version or live server behavior. |
| Clean helper/API fixture | In the isolated test distro, one MP 0.1.20 store effect changed the synthetic affinity 10→13; a fresh duplicate caused no second model call/effect; the helper END read back `off`. | This used a local 0.1.18 validation candidate containing the same patch, not the 0.1.19 release artifact. It manually assembled scope and stubbed the model callback; public hook scheduling and full CHIM dispatch were not exercised. |
| Earlier clone and Skyrim sessions | Specific selected flows remain recorded for their tested versions, including the 0.1.17 pair/solo trace. | New 0.1.19 gameplay, a complete acceptance run, native playback, or universal privacy. The separate client-delivery and MP subject follow-ups remain open. |

For 0.1.19, full HTTP pair/END/ordinary routing, wrap-up, supported removal, public ACK scheduling, and native timeout/recovery are **NOT RUN**. No playback result is claimed. The [operator checklist](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.19/docs/operator-acceptance.md) keeps evidence tied to its exact version and route.

## Install one source

Choose one PCV source at a time. Disable/remove the old source before switching; do not stack the MO2/DWPkg sync source with CHIM's Plugin Manager source.

- **CHIM Plugin Manager discovery:** use the flat [`chim-plugin.tar.gz`](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.19/chim-plugin.tar.gz) asset from the normal, non-draft public ALPHA release (`prerelease=false`). In CHIM, open **Server Plugins**, click **Refresh Plugins**, then choose **Install Plugin** on the Private Conversation repository row. For an installed row, choose `Update <channel label>`, confirm **Install Plugin** or **Update Plugin** in the installer, return with **Back to Plugin Manager**, and open **Plugin Page** on the PCV row. A fresh discovery install normally uses `ext/CHIM-PrivateConversation`; an update can preserve an existing `ext/private_conversation` folder, so use the generated page link rather than assuming its path. MO2 is not required for this server-plugin route. Discovery's release flag does not certify beta readiness.
- **MO2:** install the [MO2 ZIP](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.19/private_conversation-0.1.19-mo2.zip) as one mod. It embeds `CHIM/server-plugins/private_conversation/0.1.19.dwpkg`; do not install the embedded DWPkg separately. Its `validated=true` metadata is not proof of MO2 UI behavior or successful CHIM sync.
- **Advanced manual sync:** download the [DWPkg](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.19/private_conversation-0.1.19.dwpkg), rename it to `0.1.19.dwpkg`, and place it under `Data/CHIM/server-plugins/private_conversation/`. Do not unpack it.
- **Legacy candidate-manifest route:** [`private_conversation.tar.gz`](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.19/private_conversation.tar.gz) is the wrapped repository archive. CHIM's repository installer consumes it and strips the configured single `private_conversation/` wrapper (`archive_strip_components: 1`); users should not unpack it by hand. It is distinct from the flat `chim-plugin.tar.gz` discovery asset; do not apply the wrapped archive's strip setting to the flat asset. Existing legacy installs may keep the `ext/private_conversation` folder; use the Plugin Page link for the resolved install path.
- Verify downloaded assets with the published [`SHA256SUMS.txt`](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.19/SHA256SUMS.txt); checksums are taken from the built release, not guessed here.

See [README — Install and update](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.19/README.md#install-and-update) for route details. Package verification, a successful download, or an earlier-version gameplay session does not establish a 0.1.19 installation or live result.
