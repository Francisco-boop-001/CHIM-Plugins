#!/usr/bin/env python3
"""One-shot private Unix-socket PostgreSQL proxy for COMMIT ACK tests."""

import argparse
import json
import os
import re
import select
import socket
import stat
import struct
import sys
import time
from pathlib import Path


ROOT_RE = re.compile(r"\A/tmp/mp-native-db-[A-Za-z0-9_-]{8,32}\Z")
MAX_PACKET = 16 * 1024 * 1024


def fail(code: str) -> int:
    print(code, file=sys.stderr)
    return 2


def checked_dir(path: Path, expected: Path) -> bool:
    try:
        resolved = path.resolve(strict=True)
        info = resolved.stat()
    except OSError:
        return False
    return (
        resolved == expected
        and stat.S_ISDIR(info.st_mode)
        and info.st_uid == os.geteuid()
        and (info.st_mode & 0o077) == 0
    )


def checked_report(path: Path, root: Path) -> bool:
    try:
        parent = path.parent.resolve(strict=True)
    except OSError:
        return False
    return parent == root and path.name in {"proxy-before.json", "proxy-after.json"}


def recv_exact(sock: socket.socket, length: int) -> bytes:
    chunks = bytearray()
    while len(chunks) < length:
        part = sock.recv(length - len(chunks))
        if not part:
            raise EOFError
        chunks.extend(part)
    return bytes(chunks)


def read_startup(sock: socket.socket) -> bytes:
    header = recv_exact(sock, 4)
    length = struct.unpack("!I", header)[0]
    if length < 8 or length > 65536:
        raise ValueError("startup-length")
    return header + recv_exact(sock, length - 4)


def startup_values(packet: bytes) -> dict[str, str]:
    if len(packet) < 8 or struct.unpack("!I", packet[4:8])[0] != 196608:
        raise ValueError("startup-protocol")
    raw = packet[8:]
    values: dict[str, str] = {}
    parts = raw.split(b"\x00")
    if not parts or parts[-1] != b"":
        raise ValueError("startup-fields")
    fields = parts[:-1]
    if fields and fields[-1] == b"":
        fields = fields[:-1]
    if len(fields) % 2:
        raise ValueError("startup-fields")
    for index in range(0, len(fields), 2):
        key = fields[index].decode("ascii")
        value = fields[index + 1].decode("utf-8")
        if key in values:
            raise ValueError("startup-duplicate-field")
        values[key] = value
    return values


def parse_frames(buffer: bytearray):
    frames = []
    offset = 0
    while len(buffer) - offset >= 5:
        length = struct.unpack("!I", buffer[offset + 1:offset + 5])[0]
        if length < 4 or length > MAX_PACKET:
            raise ValueError("message-length")
        total = 1 + length
        if len(buffer) - offset < total:
            break
        frames.append(bytes(buffer[offset:offset + total]))
        offset += total
    if offset:
        del buffer[:offset]
    return frames


def backend_ready(server: socket.socket, client: socket.socket) -> tuple[int | None, bool]:
    pid = None
    saw_ready = False
    while not saw_ready:
        frame_type = recv_exact(server, 1)
        length = struct.unpack("!I", recv_exact(server, 4))[0]
        if length < 4 or length > MAX_PACKET:
            raise ValueError("startup-response-length")
        body = recv_exact(server, length - 4)
        frame = frame_type + struct.pack("!I", length) + body
        client.sendall(frame)
        if frame_type == b"K" and len(body) == 8:
            pid = struct.unpack("!I", body[:4])[0]
        if frame_type == b"Z":
            saw_ready = True
    return pid, saw_ready


def write_report(path: Path, payload: dict) -> None:
    temp = path.with_name(path.name + ".tmp")
    encoded = json.dumps(payload, sort_keys=True, separators=(",", ":")).encode("ascii")
    with open(temp, "xb") as stream:
        stream.write(encoded + b"\n")
        stream.flush()
        os.fsync(stream.fileno())
    os.replace(temp, path)
    os.chmod(path, 0o600)


def main() -> int:
    parser = argparse.ArgumentParser(add_help=False)
    parser.add_argument("--root", required=True)
    parser.add_argument("--mode", choices=("before", "after"), required=True)
    args = parser.parse_args()

    root_text = args.root
    if not ROOT_RE.fullmatch(root_text):
        return fail("scratch-root-shape")
    root = Path(root_text)
    try:
        resolved_root = root.resolve(strict=True)
    except OSError:
        return fail("scratch-root-missing")
    if str(resolved_root) != root_text:
        return fail("scratch-root-not-canonical")
    root_info = resolved_root.stat()
    if not stat.S_ISDIR(root_info.st_mode) or root_info.st_uid != os.geteuid() or (root_info.st_mode & 0o077):
        return fail("scratch-root-permissions")

    backend_dir = root / "socket"
    data_dir = root / "data"
    proxy_dir = root / ("proxy-before" if args.mode == "before" else "proxy-after")
    report = root / ("proxy-before.json" if args.mode == "before" else "proxy-after.json")
    if not checked_dir(backend_dir, root / "socket") or not checked_dir(data_dir, root / "data"):
        return fail("scratch-backend-path")
    if not proxy_dir.exists():
        try:
            proxy_dir.mkdir(mode=0o700)
        except OSError:
            return fail("proxy-directory-create")
    if not checked_dir(proxy_dir, root / proxy_dir.name) or not checked_report(report, root):
        return fail("proxy-path-safety")

    backend_path = backend_dir / ".s.PGSQL.5432"
    proxy_path = proxy_dir / ".s.PGSQL.5432"
    if len(str(proxy_path).encode("utf-8")) >= 100:
        return fail("proxy-socket-path-too-long")
    try:
        backend_info = backend_path.lstat()
    except OSError:
        return fail("backend-socket-missing")
    if not stat.S_ISSOCK(backend_info.st_mode) or backend_info.st_uid != os.geteuid():
        return fail("backend-socket-type")
    if proxy_path.exists() or proxy_path.is_symlink():
        return fail("proxy-socket-already-exists")
    if report.exists() or report.is_symlink():
        return fail("proxy-report-already-exists")

    listener = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
    client = None
    server = None
    report_payload = {
        "mode": args.mode,
        "database": "mp_native_b1",
        "user": "postgres",
        "backend_pid": None,
        "commit_forwarded": False,
        "server_command_complete": False,
        "server_ready_for_query": False,
        "ack_withheld": False,
    }
    try:
        listener.bind(str(proxy_path))
        os.chmod(proxy_path, 0o600)
        listener.listen(1)
        listener.settimeout(20)
        client, _ = listener.accept()
        client.settimeout(20)
        startup = read_startup(client)
        values = startup_values(startup)
        if values.get("database") != "mp_native_b1" or values.get("user") != "postgres":
            return fail("startup-identity")

        server = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
        server.settimeout(20)
        server.connect(str(backend_path))
        server.sendall(startup)
        report_payload["backend_pid"], ready = backend_ready(server, client)
        if not ready or report_payload["backend_pid"] is None:
            return fail("backend-startup-incomplete")
        server.settimeout(None)
        client.settimeout(None)

        client_buffer = bytearray()
        server_buffer = bytearray()
        pending_commit = False
        held_commit_response = bytearray()
        saw_commit_complete = False
        deadline = time.monotonic() + 60
        while time.monotonic() < deadline:
            readable, _, _ = select.select([client, server], [], [], 0.5)
            for source in readable:
                chunk = source.recv(65536)
                if not chunk:
                    return fail("relay-peer-closed")
                if source is client:
                    client_buffer.extend(chunk)
                    try:
                        frames = parse_frames(client_buffer)
                    except ValueError:
                        return fail("frontend-frame-invalid")
                    for frame in frames:
                        if frame[:1] == b"Q":
                            query = frame[5:-1].decode("utf-8", errors="strict").strip().rstrip(";").strip().lower()
                            if query == "commit":
                                if pending_commit:
                                    return fail("multiple-commit-requests")
                                if args.mode == "before":
                                    report_payload["commit_forwarded"] = False
                                    write_report(report, report_payload)
                                    return 0
                                pending_commit = True
                                report_payload["commit_forwarded"] = True
                        server.sendall(frame)
                else:
                    server_buffer.extend(chunk)
                    try:
                        frames = parse_frames(server_buffer)
                    except ValueError:
                        return fail("backend-frame-invalid")
                    for frame in frames:
                        if pending_commit:
                            held_commit_response.extend(frame)
                            if frame[:1] == b"C" and frame[5:] == b"COMMIT\x00":
                                saw_commit_complete = True
                            if frame[:1] == b"Z":
                                report_payload["server_command_complete"] = saw_commit_complete
                                report_payload["server_ready_for_query"] = True
                                report_payload["ack_withheld"] = saw_commit_complete
                                if not saw_commit_complete:
                                    client.sendall(held_commit_response)
                                    held_commit_response.clear()
                                    pending_commit = False
                                else:
                                    write_report(report, report_payload)
                                    return 0
                        else:
                            client.sendall(frame)
        return fail("relay-timeout")
    except (EOFError, OSError, ValueError, UnicodeError, struct.error):
        return fail("proxy-protocol-error")
    finally:
        for sock in (server, client, listener):
            if sock is not None:
                try:
                    sock.close()
                except OSError:
                    pass


if __name__ == "__main__":
    raise SystemExit(main())
