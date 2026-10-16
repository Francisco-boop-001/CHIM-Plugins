# Independent package review — Private Conversation 0.1.17

## Result

**Source-candidate, clean-commit, remote-tag and draft-download gates: PASS; no blocking findings.** The accepted MO2 metadata fix is preserved. The 0.1.17 change advances the release identity and installation documentation while the package version is sourced from the manifest. The remote annotated tag peels to the clean commit already rebuilt, so no redundant tag rebuild was needed.

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

## Clean commit export gate

Exported immutable commit `d8ef51c199a7be90415893e10c190c19e63024ad` with `git archive` into `tasks/release-0.1.17/evidence/clean-commit-d8ef51c/commit.tar`, extracted it to the adjacent `source/` directory, and ran the builder once from that clean export:

```text
python scripts\build-package.py --format release --release-dir .review-output\release
Built and verified .review-output\release\SHA256SUMS.txt (296 bytes, sha256 48c718b421143728f9e6a09be42e32c6c313f81a34db6a26a8c7e61606ca8e9a)
Built and verified .review-output\release\private_conversation-0.1.17-mo2.zip (3054196 bytes, sha256 c338af8b21a2fe82f762f773ba88f7a60b04e9043684038373500c81107b8121)
Built and verified .review-output\release\private_conversation-0.1.17.dwpkg (3463704 bytes, sha256 9a8784473844a47e03494de8837d6e00de21d8de91cc9029804503c7ef4c15f7)
Built and verified .review-output\release\private_conversation.tar.gz (3051189 bytes, sha256 90d5b48fa887c731bbfb8fbf147fe129dbf4998877f99edf68f5cc6711615d5b)
```

The clean export's `server/manifest.json` hashes to the selected commit's blob `cfc19cea9a3933868bdc6b64b12540b73f7d67d3`. A read-only byte comparison against `candidate-a` returned:

```text
SHA256SUMS.txt: BYTE_MATCH; bytes=296; sha256=48c718b421143728f9e6a09be42e32c6c313f81a34db6a26a8c7e61606ca8e9a
private_conversation-0.1.17-mo2.zip: BYTE_MATCH; bytes=3054196; sha256=c338af8b21a2fe82f762f773ba88f7a60b04e9043684038373500c81107b8121
private_conversation-0.1.17.dwpkg: BYTE_MATCH; bytes=3463704; sha256=9a8784473844a47e03494de8837d6e00de21d8de91cc9029804503c7ef4c15f7
private_conversation.tar.gz: BYTE_MATCH; bytes=3051189; sha256=90d5b48fa887c731bbfb8fbf147fe129dbf4998877f99edf68f5cc6711615d5b
clean commit export build == candidate-a for all four release files: PASS
manifest_blob_match=True (cfc19cea9a3933868bdc6b64b12540b73f7d67d3)
```

No package suite was rerun. The build and comparison wrote only under this task's review scratch; no commit, tag, push, upload or publication was performed by this reviewer.

## Remote tag and draft download gate

Read-only `git ls-remote --tags origin` confirmed the pushed annotated tag, and authenticated `gh release view` confirmed the draft's tag, prerelease state and exact four-asset list:

```text
tag=private_conversation-v0.1.17
tag_object=1f5bc72fe61ed07b62d8a4b6a6772474806e1db8
peeled_commit=d8ef51c199a7be90415893e10c190c19e63024ad
release_is_draft=True; prerelease=True
assets=private_conversation-0.1.17-mo2.zip,private_conversation-0.1.17.dwpkg,private_conversation.tar.gz,SHA256SUMS.txt
```

Downloaded the draft with `gh release download private_conversation-v0.1.17 --repo Francisco-boop-001/CHIM-PrivateConversation --dir tasks/release-0.1.17/evidence/draft-downloads` into a fresh directory. Exact output from the read-only byte comparison against `candidate-a`:

```text
SHA256SUMS.txt: DOWNLOAD_BYTE_MATCH; bytes=296; sha256=48c718b421143728f9e6a09be42e32c6c313f81a34db6a26a8c7e61606ca8e9a
private_conversation-0.1.17-mo2.zip: DOWNLOAD_BYTE_MATCH; bytes=3054196; sha256=c338af8b21a2fe82f762f773ba88f7a60b04e9043684038373500c81107b8121
private_conversation-0.1.17.dwpkg: DOWNLOAD_BYTE_MATCH; bytes=3463704; sha256=9a8784473844a47e03494de8837d6e00de21d8de91cc9029804503c7ef4c15f7
private_conversation.tar.gz: DOWNLOAD_BYTE_MATCH; bytes=3051189; sha256=90d5b48fa887c731bbfb8fbf147fe129dbf4998877f99edf68f5cc6711615d5b
draft download == candidate-a for all four files: PASS
downloaded SHA256SUMS validates all three release assets: PASS
```

## Remaining release gate

The release remains a draft; publication was not performed by this reviewer. The tag points to the clean commit whose rebuild matches the candidate, and the authenticated draft downloads match those candidate bytes. No MO2 UI/install, live CHIM sync, provider, database or Skyrim runtime check was performed.
