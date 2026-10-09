# Private Conversation 0.1.16 — PRE-ALPHA candidate

Actions inside scenes, opt-in. Before, every scene was talk only. Design: `docs/superpowers/specs/2026-10-05-pcv-0.1.16-scene-actions-design.md`.

## New

- **Three boxes on the page, all off by default.** END turns them off again, so a new scene never inherits them.
  - **Personal:** drink, toast, eat or drink from inventory, take a seat, relax. A reflecting NPC can now actually drink alone.
  - **Physical:** a bare-fisted brawl between scene members, or surrender. Not for solo.
  - **Intimate:** SHARMAT's intimacy actions between scene members, or self-directed moments in solo. SHARMAT's consent and safety rules still decide what each NPC may do; PCV only narrows them.
- **Members only.** Every action stays inside the scene. An action aimed at the excluded player or a bystander is dropped after the reply and logged as `routing.action_dropped`.
- **Never in scenes:** Attack, KillTarget, gifts, travel, crime, spawning and SHARMAT's role actions.
- **CHIM's own switch wins.** If actions are off in CHIM, ticking a box does not turn them on.

## Verification

- 56 offline PHP fixtures, 13 browser-refresh cases and the package check pass.
- Clone check (5 AI calls in total):
  - **Solo, Personal:** Lidia chose Drink. CHIM ran it server-side, so the drinking idle plays.
  - **Pair, Physical:** the model was offered only Talk, Brawl and Surrender. It chose to taunt and did not brawl; that was the model's choice.
  - **Pair, Intimate:** the model was offered SHARMAT's gated set (kiss, hug, hold hands, dress, refuse; no sex actions at that relationship level) plus Personal. A retry delivered `Bruce Wayne|command|ExtCmdHoldHands@Lidia Sobieska` to the game.
- **Not explained:** on the first Intimate run the model chose Hold_Hands, but CHIM never processed the action. A probe on the retry showed actions enabled at every stage, and the miss did not repeat.
- **Not checked in game:** whether drinking from inventory raises a SHARMAT drunk stage. The clone has no game inventory.

## Installation

Replace the old enabled PCV package; do not stack versions. See the [0.1.16 installation guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.16/README.md).
