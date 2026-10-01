# Private Conversation 0.1.5 package-check review

Updated only the explicit release-candidate expectations in `tests/package_check.py`: DWPkg and MO2 ZIP names, manifest version, versioned GitHub release URL, and inner MO2 DWPkg path now pin `0.1.5`. Existing structural, allowlist, checksum, determinism, and extraction assertions remain intact. The core compatibility reference and `private_conversation.tar.gz` name remain unchanged.

The metadata gate was cleared by the lead, then the existing check passed:

```text
Command: python tests/package_check.py
Working directory: plugins/private_conversation
....
----------------------------------------------------------------------
Ran 4 tests in 2.443s

OK
Exit code: 0
```

The test builds deterministic package files only inside temporary test directories, which are cleaned by the test harness. It verifies the 0.1.5 names, manifest values and candidate URL, archive membership and CRC, SHA-256 sums, extraction contents, MO2 wrapper path, and relative-output CLI behavior. No canonical upload assets were built, and no installation or runtime behavior is verified.
