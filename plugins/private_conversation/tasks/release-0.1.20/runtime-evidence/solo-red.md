# Solo action fixture RED receipt

This is a prose receipt from root's tool run. Separate raw RED stdout/stderr files were not retained; the output below is recorded from root's report, not reconstructed from a local rerun.

Command:

```text
wsl.exe --distribution DwemerAI4Skyrim3-test --exec /bin/sh -lc 'test "$WSL_DISTRO_NAME" = DwemerAI4Skyrim3-test || exit 97; cd /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev || exit 98; php tests/scene_actions_hook_check.php'
```

Exit code: `1`

Reported output:

```text
FAIL: Solo offers personal actions and the three self-directed intimate actions only, even if handed a physical group.
```

Root reported that the command ran against the unchanged b7030f259bf25101303f56c03090a060290992a5 product baseline before the solo source edit. The baseline Git blob for `server/scope.php` is `244f4ac854378170129344a269d2b465311d3cef`. This expected RED was followed by the focused GREEN receipts named in `../release-report.md`.
