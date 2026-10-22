# Private Conversation 0.1.23 — BETA

PCV gives a scene its cast and stage direction. CHIM and the model still have to write the line, and I still have to resist calling a prompt a contract.

## Install one copy

This walkthrough uses the CHIM Server Plugins route. Open **Server Plugins**, click **Refresh Plugins**, then choose **Install Plugin** for the Private Conversation row. To update, use **Update** on that same installed row and confirm. Keep one active PCV copy; adding MO2 or a manual package alongside it creates a second installation, not extra reliability. The [README install and recovery guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.23/README.md#install-and-update) explains the other routes if you need them.

Open PCV's **Plugin Page** and wait for the eligibility check. Choose a scene mode, set its cast and player participation, add a one-line Scene card if useful, then click **Arm or update on next input**. ARM stages settings; it does not start dialogue. Send one ordinary **Standard** input in Skyrim to let the next eligible request use them.

| Mode | Setup |
| --- | --- |
| **Pair** | Choose two eligible NPCs. Include the player for ordinary in-character speech, or exclude the player to make the input scene direction. |
| **Group** | Choose two to four members. Unavailable members, including A or B, may be dropped; at least two eligible members must remain. |
| **Free scene** | Choose a cap from two to six. PCV selects up to that many eligible nearby NPCs; the player is excluded. |
| **Solo reflection** | Choose one NPC. Each eligible Standard input requests one reflection; it does not start an endless monologue. The player is excluded. |

Other-NPC and action settings guide what the model is asked to do. “Present but silent” is not a physical privacy barrier, and an offered action is not a promise that CHIM or SHARMAT will execute it.

## Replacements and wrap-up

If a replacement cannot be verified while a scene is active—including when the strict eligibility map is unavailable—PCV refuses that one input and clears the failed replacement from the queue. The existing scene remains in place, and the refused request is not redirected to either cast. A fresh ordinary Standard input can recheck the existing scene; stage the replacement again if needed. Initial ARM with no active scene remains pending and retryable. If the current playthrough identity is unavailable, a safely readable store containing only expired scenes no longer blocks an input; unsafe or unreadable state still fails closed, and PCV does not rewrite or delete scene-state data under an unknown identity.

In Pair, Group, or Free, `wrap up: <how they close>` is a stage direction even when the player is included. PCV validates the player-prefixed direction before clearing the scene; an invalid prefix, such as one made ambiguous by a colon-bearing identity, is refused without ending the scene. A valid PCV closing sentinel takes precedence over a valid external listener pin for that response. It closes the PCV scene turn, but does not end OStim or cancel independent SHARMAT requests. CHIM still controls generation and playback. **End now** clears PCV settings after a successful write; wait for the page's state readback before assuming it is off. It cannot stop speech already queued.

## Operational logs

This access rule locks only the diagnostics view and log export; it does not lock scene controls. Direct loopback requests are allowed only without forwarding headers. An existing trusted web server may instead supply `REMOTE_USER` after authentication. A reverse proxy must authenticate the upstream client and set `REMOTE_USER` server-side, or mark proxied traffic so PCV rejects it as direct loopback; never pass a client-supplied `REMOTE_USER` header through as the authenticated server identity. Preserve trustworthy source-address information too: forwarded identity/address headers alone are not authentication. A private default gateway is not caller identity, so PCV no longer grants automatic gateway access. Depending on the WSL and host network setup, a Windows browser may not reach the service through localhost. If localhost cannot reach it and no authenticated route already exists, the server operator must provide authenticated access or inspect the logs locally. PCV does not configure authentication or ship a login page.

## Verification status

The source gate records 19 selected PHP fixture files passing in the test distro, 14/14 host-only Node DOM checks, and syntax checks for the five changed PHP files. Those focused checks are not a full CHIM request, provider run, installation, or Skyrim gameplay test. The [current verification record](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.23/tasks/critique-fixes-2026-10-22/verification.md) preserves original failures and corrected fixture receipts. The earlier 0.1.20 Pair/Solo playback record remains evidence about 0.1.20; 0.1.21's payload comparison does not extend that gameplay evidence to runtime-changing 0.1.22 or 0.1.23. The [historical 0.1.20 acceptance evidence](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.22/tasks/beta-2026-10-17/acceptance-evidence.md) retains its original version boundary. CHIM core, SHARMAT, and Mind Poisoning are unchanged and outside this PCV-only release.
