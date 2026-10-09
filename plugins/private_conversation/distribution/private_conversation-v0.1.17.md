# Private Conversation 0.1.17 — PRE-ALPHA

Packaging-only update. The MO2 ZIP now contains a root `meta.ini` with `[General]`, `version=0.1.17`, and `validated=true` beside `CHIM/server-plugins/private_conversation/0.1.17.dwpkg`. No PCV runtime code changed. The existing 0.1.16 release assets remain intact.

`validated=true` is MO2 metadata intended to suppress its invalid-content flag for this CHIM-only package. It adds no Skyrim game data and does not prove warning-free UI behavior, successful installation, CHIM sync, or gameplay.

## Installation

For MO2, use the single [0.1.17 MO2 ZIP](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.17/private_conversation-0.1.17-mo2.zip). It already includes the DWPkg. Install and enable it as an ordinary MO2 mod, keeping `CHIM` directly under the game's data root; replace the old enabled PCV package instead of stacking versions.

With CHIM running and connected, load into a save to trigger sync. Check **Server Plugins → Automatic Game Plugin Sync → Refresh Status** and the client `SERVER_PLUGIN_SYNC` log for installed server version `0.1.17`. MO2 may overwrite the displayed version during import. Native MO2 installation and sync were not tested for this packaging update.

The standalone [DWPkg](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.17/private_conversation-0.1.17.dwpkg) and [repository TAR](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.17/private_conversation.tar.gz) are advanced alternatives for their respective CHIM consumers; do not unpack the DWPkg into Data. [SHA256SUMS.txt](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.17/SHA256SUMS.txt) is optional download verification.

## Verification

The existing package-check file passes all four tests. Two fresh 0.1.17 release builds are byte-identical. The packaged server files match the accepted source except for the manifest version; no runtime code changed. These are packaging checks only. This remains PRE-ALPHA; no native MO2 UI/install, live server sync, provider, database, or Skyrim gameplay check was performed for 0.1.17.
