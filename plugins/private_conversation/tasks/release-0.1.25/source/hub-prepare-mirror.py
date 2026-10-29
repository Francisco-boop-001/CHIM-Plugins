from __future__ import annotations

import argparse
import hashlib
import json
import subprocess
from datetime import datetime, timezone
from pathlib import Path, PurePosixPath


HUB = Path(r"K:\ActorwrightExchange\projects\CHIM-Plugins-pcv-0.1.19-hub")
SOURCE = Path(r"K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev")
EVIDENCE = SOURCE / "tasks" / "release-0.1.25" / "source"
HUB_BRANCH = "publish/private-conversation-0.1.25"
HUB_BASE = "2cf4d1740052e09b379a81acdd96c432f7054477"
SOURCE_BASE = "ce0a998773237a9fc91f6914725a327d9e621718"
SOURCE_TAG = "private_conversation-v0.1.25"
SOURCE_COMMIT = "886a54967db7c6c08725c8bec8854ea3919ea532"
TODO_PATH = "tasks/todo.md"
README_LINES = (34, 40, 42, 67, 72, 90)


def git(repo: Path, *args: str, binary: bool = False) -> bytes | str:
    result = subprocess.run(
        ["git", "-c", f"safe.directory={repo.as_posix()}", "-C", str(repo), *args],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    if result.returncode:
        raise RuntimeError(result.stderr.decode("utf-8", "replace").strip())
    return result.stdout if binary else result.stdout.decode("utf-8").rstrip("\r\n")


def git_tree(repo: Path, revision: str) -> dict[str, dict[str, str]]:
    raw = git(repo, "ls-tree", "-r", "-z", "--full-tree", revision, binary=True)
    entries: dict[str, dict[str, str]] = {}
    for record in raw.split(b"\0"):
        if not record:
            continue
        metadata, raw_path = record.split(b"\t", 1)
        mode, kind, oid = metadata.decode("ascii").split()
        if kind != "blob":
            raise RuntimeError(f"Unexpected tree object: {raw_path!r}")
        entries[raw_path.decode("utf-8")] = {"mode": mode, "blob": oid}
    return entries


def git_blob(data: bytes) -> str:
    header = f"blob {len(data)}\0".encode("ascii")
    return hashlib.sha1(header + data).hexdigest()


def write_json(path: Path, value: object) -> None:
    path.write_text(json.dumps(value, indent=2, sort_keys=True, ensure_ascii=False) + "\n", encoding="utf-8")


def safe_relative(path: str) -> PurePosixPath:
    result = PurePosixPath(path)
    if result.is_absolute() or any(part in ("", ".", "..") for part in result.parts):
        raise RuntimeError(f"Unsafe source path: {path!r}")
    return result


def readme_content(line: bytes) -> tuple[bytes, bytes]:
    if line.endswith(b"\r\n"):
        return line[:-2], b"\r\n"
    if line.endswith(b"\n"):
        return line[:-1], b"\n"
    return line, b""


def prepared_readme(before: bytes) -> tuple[bytes, list[dict[str, object]]]:
    lines = before.splitlines(keepends=True)
    if max(README_LINES) > len(lines):
        raise RuntimeError("Hub README is shorter than the pinned PCV line map")
    expected = {
        34: "### Private Conversation 0.1.24 — BETA",
        40: "The public 0.1.24 BETA release contains 21 server payload files and changes runtime code.",
        42: "[Release and checksums]",
        67: "| Private Conversation 0.1.24 BETA |",
        72: "Private Conversation’s discovery asset is the flat",
        90: "For Mind Poisoning, open CHIM Plugin Manager",
    }
    for number, prefix in expected.items():
        if not readme_content(lines[number - 1])[0].decode("utf-8").startswith(prefix):
            raise RuntimeError(f"README line {number} no longer matches the approved PCV anchor")

    updates = {
        34: "### Private Conversation 0.1.25 — BETA",
        40: (
            "The public 0.1.25 BETA release contains 21 server payload files and changes runtime code. "
            "Four focused PHP behavior fixtures and a 16/16 Node UI run passed; the POSIX-guarded broad page fixture "
            "is unperformed on the review host. A clean build from the immutable tag produced five assets byte-identical "
            "to the tested candidate. No provider request, installation, or Skyrim gameplay is claimed. See "
            "[current beta evidence and limits](plugins/private_conversation/docs/operator-acceptance.md) and the "
            "[0.1.25 release note](plugins/private_conversation/distribution/private_conversation-v0.1.25.md)."
        ),
        42: readme_content(lines[41])[0].decode("utf-8").replace("private_conversation-v0.1.24", "private_conversation-v0.1.25"),
        67: readme_content(lines[66])[0].decode("utf-8").replace("0.1.24", "0.1.25"),
        72: readme_content(lines[71])[0].decode("utf-8").replace("private_conversation-v0.1.24", "private_conversation-v0.1.25"),
        90: readme_content(lines[89])[0].decode("utf-8").replace("private_conversation-v0.1.24", "private_conversation-v0.1.25"),
    }

    changes = []
    updated = list(lines)
    for number in README_LINES:
        old, newline = readme_content(lines[number - 1])
        new_text = updates[number]
        if old.decode("utf-8") == new_text:
            raise RuntimeError(f"README line {number} did not need a versioned update")
        updated[number - 1] = new_text.encode("utf-8") + newline
        changes.append({"line": number, "before": old.decode("utf-8"), "after": new_text})
    return b"".join(updated), changes


def status_paths() -> set[str]:
    raw = git(HUB, "status", "--porcelain=v1", "-z", "--untracked-files=all", binary=True)
    paths = set()
    for record in raw.split(b"\0"):
        if record:
            paths.add(record[3:].decode("utf-8"))
    return paths


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--verify-prepared",
        action="store_true",
        help="Verify an already prepared mirror without copying files or replacing before snapshots.",
    )
    args = parser.parse_args()
    verify_prepared = args.verify_prepared
    EVIDENCE.mkdir(parents=True, exist_ok=True)
    if git(HUB, "branch", "--show-current") != HUB_BRANCH:
        raise RuntimeError("The hub checkout is not on the assigned publication branch")
    if git(HUB, "rev-parse", "HEAD") != HUB_BASE or git(HUB, "rev-parse", "origin/main") != HUB_BASE:
        raise RuntimeError("The new publication branch no longer matches the freshly fetched origin/main")
    if not verify_prepared and git(HUB, "status", "--porcelain=v1", "--untracked-files=all"):
        raise RuntimeError("The hub worktree is not clean before mirroring")
    if git(SOURCE, "rev-parse", f"{SOURCE_TAG}^{{commit}}") != SOURCE_COMMIT:
        raise RuntimeError("The source tag no longer resolves to the authorized 0.1.25 commit")
    if git(SOURCE, "rev-parse", "private_conversation-v0.1.24^{commit}") != SOURCE_BASE:
        raise RuntimeError("The 0.1.24 source base no longer matches the released source commit")

    hub_tree = git_tree(HUB, HUB_BASE)
    source_tree = git_tree(SOURCE, SOURCE_COMMIT)
    source_tree_id = git(SOURCE, "rev-parse", f"{SOURCE_COMMIT}^{{tree}}")
    hub_tree_id = git(HUB, "rev-parse", f"{HUB_BASE}^{{tree}}")
    if hub_tree_id != "cc27fee101ed3f241a85cfdf04503e94f475400e":
        raise RuntimeError("Fresh hub main tree differs from the accepted baseline")
    if source_tree_id != "c97ea63536365c6a27c3a5bc2a470bd4fcd27468":
        raise RuntimeError("Canonical source tag tree differs from the accepted release")

    delta = git(SOURCE, "diff", "--name-status", SOURCE_BASE, SOURCE_COMMIT).splitlines()
    changes: dict[str, str] = {}
    for line in delta:
        fields = line.split("\t")
        status = fields[0]
        if status.startswith(("R", "C")):
            changes[fields[1]] = "D"
            changes[fields[2]] = "A"
        else:
            changes[fields[1]] = status
    excluded_source_changes = {name: changes.pop(name) for name in list(changes) if name == TODO_PATH}

    server_inventory = json.loads(git(
        SOURCE, "show", f"{SOURCE_COMMIT}:tasks/release-0.1.25/package/source-sha256.json", binary=True
    ).decode("utf-8"))
    server_paths = sorted(f"server/{path}" for path in server_inventory["files"])
    if len(server_paths) != 21 or not all(path.startswith("server/") for path in server_paths):
        raise RuntimeError("The tagged package receipt did not identify exactly 21 server payload files")

    source_entries: dict[str, str] = dict(changes)
    for path in server_paths:
        source_entries.setdefault(path, "server-payload-unchanged")

    mapped = []
    for source_path, status in sorted(source_entries.items()):
        safe_relative(source_path)
        target_path = f"plugins/private_conversation/{source_path}"
        source_entry = source_tree.get(source_path)
        if status == "D":
            if source_entry is not None:
                raise RuntimeError(f"Deleted source path remains in release tree: {source_path}")
            target_after_mode = target_after_blob = None
        else:
            if source_entry is None:
                raise RuntimeError(f"Mapped source path is absent from release tree: {source_path}")
            if source_entry["mode"] != "100644":
                raise RuntimeError(f"Unexpected file mode for mapped release path: {source_path}")
            target_after_mode = source_entry["mode"]
            target_after_blob = source_entry["blob"]
        prior = hub_tree.get(target_path)
        mapped.append({
            "source_path": source_path,
            "target_path": target_path,
            "source_status": status,
            "source_mode": source_entry["mode"] if source_entry else None,
            "source_blob": source_entry["blob"] if source_entry else None,
            "target_before_mode": prior["mode"] if prior else None,
            "target_before_blob": prior["blob"] if prior else None,
            "target_after_mode": target_after_mode,
            "target_after_blob": target_after_blob,
        })

    if len(changes) != 150 or len(mapped) != 165:
        raise RuntimeError(f"Unexpected source/mirror map sizes: changes={len(changes)}, mapped={len(mapped)}")
    if len([p for p in server_paths if p in changes]) != 6:
        raise RuntimeError("The source diff no longer overlaps the expected six changed server files")

    readme_path = HUB / "README.md"
    readme_before = git(HUB, "show", f"{HUB_BASE}:README.md", binary=True)
    readme_after, readme_changes = prepared_readme(readme_before)
    protected_before = [
        {"mode": entry["mode"], "blob": entry["blob"], "path": path}
        for path, entry in sorted(hub_tree.items())
        if not path.startswith("plugins/private_conversation/") and path != "README.md"
    ]
    pcv_paths_before = sorted(path for path in hub_tree if path.startswith("plugins/private_conversation/"))
    previous_branch = git(HUB, "rev-parse", "refs/heads/publish/private-conversation-0.1.24")
    previous_branch_tree = git(HUB, "rev-parse", f"{previous_branch}^{{tree}}")
    old_todo_path = "plugins/private_conversation/tasks/todo.md"
    old_todo = hub_tree.get(old_todo_path)

    baseline_receipt = {
        "recorded_at_utc": datetime.now(timezone.utc).isoformat(),
        "hub_repository": "Francisco-boop-001/CHIM-Plugins",
        "checkout": str(HUB),
        "branch": HUB_BRANCH,
        "branch_head": HUB_BASE,
        "verified_fetched_origin_main": HUB_BASE,
        "base_commit_tree": hub_tree_id,
        "worktree_and_index_clean_before_mirror": True,
        "pcv_subtree": "plugins/private_conversation/",
        "pcv_subtree_tree": git(HUB, "rev-parse", f"{HUB_BASE}:plugins/private_conversation"),
        "pcv_tracked_path_count": len(pcv_paths_before),
        "pcv_path_manifest": "hub-pcv-paths-before.txt",
        "preserved_previous_0_1_24_branch": previous_branch,
        "preserved_previous_0_1_24_tree": previous_branch_tree,
        "source_commit": SOURCE_COMMIT,
        "source_tree": source_tree_id,
    }
    protected_before_receipt = {
        "captured_at_utc": datetime.now(timezone.utc).isoformat(),
        "hub_base": HUB_BASE,
        "hub_base_tree": hub_tree_id,
        "protected_scope": "All tracked paths outside plugins/private_conversation/** and root README.md",
        "root_readme_before_blob": hub_tree["README.md"]["blob"],
        "protected_path_count": len(protected_before),
        "protected_entries": protected_before,
    }
    if verify_prepared:
        saved_baseline = json.loads((EVIDENCE / "hub-baseline.json").read_text(encoding="utf-8"))
        saved_protected_before = json.loads((EVIDENCE / "hub-protected-tree-before.json").read_text(encoding="utf-8"))
        saved_pcv_paths = (EVIDENCE / "hub-pcv-paths-before.txt").read_text(encoding="utf-8").splitlines()
        if saved_baseline["branch_head"] != HUB_BASE or saved_baseline["source_commit"] != SOURCE_COMMIT:
            raise RuntimeError("Saved hub baseline receipt does not match this source/base pin")
        if saved_protected_before["protected_entries"] != protected_before:
            raise RuntimeError("Saved protected before-snapshot does not match the pinned hub base")
        if saved_pcv_paths != pcv_paths_before:
            raise RuntimeError("Saved PCV before-path manifest does not match the pinned hub base")
    else:
        write_json(EVIDENCE / "hub-baseline.json", baseline_receipt)
        write_json(EVIDENCE / "hub-protected-tree-before.json", protected_before_receipt)
        (EVIDENCE / "hub-pcv-paths-before.txt").write_text("\n".join(pcv_paths_before) + "\n", encoding="utf-8")

    expected_paths = {item["target_path"] for item in mapped} | {"README.md"}
    allowlist = ["# Source diff since ce0a998 plus all 21 tagged server files; source tasks/todo.md is excluded.", *sorted(expected_paths)]
    if verify_prepared:
        saved_allowlist = (EVIDENCE / "hub-mirror-allowlist.txt").read_text(encoding="utf-8").splitlines()
        if saved_allowlist != allowlist:
            raise RuntimeError("Saved mirror allowlist does not match the pinned source map")
    else:
        (EVIDENCE / "hub-mirror-allowlist.txt").write_text("\n".join(allowlist) + "\n", encoding="utf-8")

    if not verify_prepared:
        for item in mapped:
            source_path = item["source_path"]
            target = HUB.joinpath(*PurePosixPath(item["target_path"]).parts)
            if item["source_status"] == "D":
                if target.exists() or target.is_symlink():
                    target.unlink()
                continue
            content = git(SOURCE, "show", f"{SOURCE_COMMIT}:{source_path}", binary=True)
            if git_blob(content) != item["source_blob"]:
                raise RuntimeError(f"Tagged source blob mismatch before copy: {source_path}")
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(content)
        readme_path.write_bytes(readme_after)

    after_files = []
    for item in mapped:
        target = HUB.joinpath(*PurePosixPath(item["target_path"]).parts)
        if item["source_status"] == "D":
            after_mode = after_blob = None
            matches = not target.exists() and not target.is_symlink()
        else:
            if target.is_symlink() or not target.is_file():
                raise RuntimeError(f"Mapped target is not a regular file: {item['target_path']}")
            data = target.read_bytes()
            after_mode = item["source_mode"]
            after_blob = git_blob(data)
            matches = after_mode == item["source_mode"] and after_blob == item["source_blob"]
        after_files.append({**item, "target_after_mode": after_mode, "target_after_blob": after_blob, "matches_source": matches})

    # Compare the protected tracked tree through Git's normalized diff/index
    # view. Raw checkout bytes can differ from Git blobs under attributes/EOL
    # filters even when the protected worktree is unchanged.
    protected_after_tree = git_tree(HUB, "HEAD")
    protected_after = [
        {"mode": entry["mode"], "blob": protected_after_tree[path]["blob"], "path": path}
        for entry in protected_before
        for path in (entry["path"],)
    ]

    readme_after_lines = readme_path.read_bytes().splitlines(keepends=True)
    readme_before_lines = readme_before.splitlines(keepends=True)
    current_readme_matches = readme_path.read_bytes() == readme_after
    non_pcv_lines_unchanged = len(readme_before_lines) == len(readme_after_lines) and all(
        readme_before_lines[index] == readme_after_lines[index]
        for index in range(len(readme_before_lines)) if index + 1 not in README_LINES
    )
    actual_paths = status_paths()
    staged_clean = not git(HUB, "diff", "--cached", "--quiet", binary=True)
    protected_diff = git(HUB, "diff", "--name-only", HUB_BASE, "--", ".", ":!plugins/private_conversation/**", ":!README.md").splitlines()
    todos_after = HUB / "plugins" / "private_conversation" / "tasks" / "todo.md"
    todo_unchanged = old_todo is not None and todos_after.is_file() and git_blob(todos_after.read_bytes()) == old_todo["blob"]

    map_ok = all(item["matches_source"] for item in after_files)
    protected_ok = protected_before == protected_after and not protected_diff and staged_clean
    readme_ok = (
        current_readme_matches
        and non_pcv_lines_unchanged
        and [entry["line"] for entry in readme_changes] == list(README_LINES)
    )
    server_ok = all(
        next(item for item in after_files if item["source_path"] == path)["matches_source"]
        for path in server_paths
    )
    all_changes_allowlisted = actual_paths <= expected_paths
    write_json(EVIDENCE / "hub-mirror-source-map.json", {
        "canonical_source_baseline": SOURCE_BASE,
        "canonical_source_tag": SOURCE_TAG,
        "canonical_source_commit": SOURCE_COMMIT,
        "canonical_source_tree": source_tree_id,
        "hub_base": HUB_BASE,
        "source_changed_path_count_raw": len(delta),
        "source_changed_path_count_mirrored": len(changes),
        "excluded_source_paths": [{"path": path, "status": status, "reason": "Preserve historical hub-specific task-list adaptations."} for path, status in excluded_source_changes.items()],
        "server_payload_file_count": len(server_paths),
        "source_mirror_file_count": len(mapped),
        "files": after_files,
    })
    write_json(EVIDENCE / "hub-README-line-change-map.json", {
        "hub_base": HUB_BASE,
        "source_commit": SOURCE_COMMIT,
        "changed_lines": readme_changes,
        "non_pcv_lines_unchanged": non_pcv_lines_unchanged,
    })
    write_json(EVIDENCE / "hub-protected-tree-after.json", {
        "captured_at_utc": datetime.now(timezone.utc).isoformat(),
        "hub_base": HUB_BASE,
        "protected_scope": "All tracked paths outside plugins/private_conversation/** and root README.md",
        "protected_path_count": len(protected_after),
        "matches_before_snapshot": protected_before == protected_after,
        "protected_entries": protected_after,
    })
    git_diff = git(HUB, "diff", "--", "README.md")
    (EVIDENCE / "hub-root-readme.diff").write_text(git_diff + "\n", encoding="utf-8")
    write_json(EVIDENCE / "hub-mirror-verification.json", {
        "result": "PASS" if map_ok and protected_ok and readme_ok and server_ok and all_changes_allowlisted and staged_clean and todo_unchanged else "FAIL",
        "hub_branch": HUB_BRANCH,
        "hub_base": HUB_BASE,
        "canonical_source_commit": SOURCE_COMMIT,
        "canonical_source_tree": source_tree_id,
        "mapped_file_count": len(mapped),
        "server_payload_count": len(server_paths),
        "server_payload_matches_source_tag": server_ok,
        "source_changed_path_count_raw": len(delta),
        "source_changed_path_count_mirrored": len(changes),
        "source_tasks_todo_excluded_and_target_preserved": todo_unchanged,
        "mapped_blob_mode_matches": map_ok,
        "protected_tracked_path_count": len(protected_before),
        "protected_tracked_tree_exact": protected_ok,
        "readme_changed_lines": [entry["line"] for entry in readme_changes],
        "root_readme_matches_expected_after": current_readme_matches,
        "readme_non_pcv_lines_unchanged": readme_ok,
        "all_worktree_changes_within_allowlist": all_changes_allowlisted,
        "actual_worktree_changed_path_count": len(actual_paths),
        "actual_worktree_paths": sorted(actual_paths),
        "index_clean_no_staged_changes": staged_clean,
        "unexpected_paths": sorted(actual_paths - expected_paths),
        "readme_special_scope": "Only PCV release/version/download/discovery/support lines were changed.",
    })

    if not (map_ok and protected_ok and readme_ok and server_ok and all_changes_allowlisted and staged_clean and todo_unchanged):
        raise RuntimeError("One or more mirror verification gates failed; inspect hub-mirror-verification.json")
    print(json.dumps({
        "status": "PASS",
        "mode": "verify-prepared" if verify_prepared else "prepare-and-verify",
        "hub_branch": HUB_BRANCH,
        "hub_base": HUB_BASE,
        "source_commit": SOURCE_COMMIT,
        "mapped_file_count": len(mapped),
        "source_changes": len(changes),
        "server_payloads": len(server_paths),
        "protected_paths": len(protected_before),
        "readme_lines": [entry["line"] for entry in readme_changes],
        "staged": False,
    }, sort_keys=True))


if __name__ == "__main__":
    main()
