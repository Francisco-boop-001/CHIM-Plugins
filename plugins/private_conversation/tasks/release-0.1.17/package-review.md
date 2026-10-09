# Independent package review — Private Conversation 0.1.17

## Result

**Source-candidate gate: PASS; no blocking findings.** The accepted MO2 metadata fix is preserved. The 0.1.17 change advances the release identity and installation documentation while the package version is sourced from the manifest. This review covers local candidate artifacts only; clean-tag rebuilding and draft-download byte checks remain for the next release gate.

## Spec and quality review

Compared the current diff with `accepted-fix.diff`. The builder/test changes match the accepted fix: the deterministic MO2 ZIP has only root `meta.ini` and the manifest-versioned CHIM DWPkg; it checks exact members, CRC, metadata bytes and embedded DWPkg before replacing the destination. The regression parses the generated metadata and retains the existing release-consumer checks. The README uses 0.1.17 for current PCV links and routes, preserves Mind Poisoning 0.1.16 references, and documents the old 0.1.16 assets separately. The README and release note identify `validated=true` as MO2 metadata and make no native install/sync or gameplay claim.

`server/manifest.json` changes version only; schema, identity, channel fields, compatibility reference and maturity remain as before. Per the lead's independent comparison of `runtime-before.json`, the other 19 captured server files are unchanged and the manifest JSON differs only by `0.1.16` → `0.1.17`. No product changes were made by this reviewer.

The builder's failure boundary remains suitable for the release flow: each output is kept under the project root, the release directory must be new or empty, and the MO2 ZIP is verified before `os.replace`. A failed multi-asset release build can leave a partial candidate directory; use a fresh directory for a retry and keep all candidates out of `dist/release-v0.1.16`.

## Candidate artifact audit

The source owner reported four passing package checks and two fresh builds in `source-report.md`; I did not rerun the suite or rebuild. I performed a read-only Python audit from PowerShell (`$code | python -`) on both existing candidates. It inspected the actual DWPkg, MO2 ZIP, repository TAR and checksum file.

Exact output:

```text
candidate-a: release-members=PASS; sums=PASS; dwpkg-members=22/PASS; dwpkg-crc+manifest+checksums=PASS; mo2-members+crc+meta+embedded-bytes=PASS; tar-members+manifest=PASS
  SHA256SUMS.txt sha256=48c718b421143728f9e6a09be42e32c6c313f81a34db6a26a8c7e61606ca8e9a
  private_conversation-0.1.17-mo2.zip sha256=c338af8b21a2fe82f762f773ba88f7a60b04e9043684038373500c81107b8121
  private_conversation-0.1.17.dwpkg sha256=9a8784473844a47e03494de8837d6e00de21d8de91cc9029804503c7ef4c15f7
  private_conversation.tar.gz sha256=90d5b48fa887c731bbfb8fbf147fe129dbf4998877f99edf68f5cc6711615d5b
candidate-b: release-members=PASS; sums=PASS; dwpkg-members=22/PASS; dwpkg-crc+manifest+checksums=PASS; mo2-members+crc+meta+embedded-bytes=PASS; tar-members+manifest=PASS
  SHA256SUMS.txt sha256=48c718b421143728f9e6a09be42e32c6c313f81a34db6a26a8c7e61606ca8e9a
  private_conversation-0.1.17-mo2.zip sha256=c338af8b21a2fe82f762f773ba88f7a60b04e9043684038373500c81107b8121
  private_conversation-0.1.17.dwpkg sha256=9a8784473844a47e03494de8837d6e00de21d8de91cc9029804503c7ef4c15f7
  private_conversation.tar.gz sha256=90d5b48fa887c731bbfb8fbf147fe129dbf4998877f99edf68f5cc6711615d5b
candidate-a == candidate-b for all 4 files: PASS
```

The actual inner manifests report version `0.1.17`; the DWPkg has 22 members, and its checksums cover the 21 non-checksum entries. The MO2 ZIP contains exactly `meta.ini` and `CHIM/server-plugins/private_conversation/0.1.17.dwpkg`; parsed metadata is exactly `[General]`, `version=0.1.17`, `validated=true`, with LF bytes. Its embedded DWPkg matches the standalone candidate. The repository TAR has the expected single wrapper and versioned manifest. All listed release checksums match the files, and both candidate directories are byte-identical.

## Remaining release gate

This does not prove a clean-tag rebuild or uploaded draft bytes; those checks await the lead's scoped commit/tag handoff. No MO2 UI/install, live CHIM sync, provider, database or Skyrim runtime check was performed.
