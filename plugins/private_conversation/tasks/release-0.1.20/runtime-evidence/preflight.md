# 0.1.20 runtime preflight receipt

Date: 2026-10-16. Scope: Windows-only registered distro/VHD metadata and one test-distro identity/PHP/temp-path check. No service or database was started.

## WSL list before test entry

Command:
    wsl.exe --list --verbose

Exit code: 0

Output (NUL padding from the Windows tool's UTF-16 display removed for readability):
    NAME                    STATE    VERSION
    kali-linux              Stopped  2
    docker-desktop           Stopped  2
    DwemerAI4Skyrim3         Stopped  2
    DwemerAI4Skyrim3-test    Stopped  2

## Registered test/live VHD metadata

Command (PowerShell):
    $base = 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Lxss'
    $wanted = @('DwemerAI4Skyrim3-test','DwemerAI4Skyrim3')
    $entries = @(Get-ChildItem -LiteralPath $base | ForEach-Object { $entry = Get-ItemProperty -LiteralPath $_.PSPath; if ($wanted -contains $entry.DistributionName) { [pscustomobject]@{ Name = $entry.DistributionName; BasePath = $entry.BasePath; VhdPath = Join-Path $entry.BasePath 'ext4.vhdx' } } })
    $entries | Format-List Name,BasePath,VhdPath
    $metadata = @()
    foreach ($entry in $entries) { $file = Get-Item -LiteralPath $entry.VhdPath -ErrorAction Stop; $metadata += [pscustomobject]@{ Name = $entry.Name; FullName = $file.FullName; Length = $file.Length; LastWriteTimeUtc = $file.LastWriteTimeUtc.ToString('o'); Attributes = $file.Attributes } }
    $metadata | Format-List

Exit code: 0

Output:
    DwemerAI4Skyrim3-test: E:\WSL\DwemerAI4Skyrim3-test\ext4.vhdx
      Length 148942880768; LastWriteTimeUtc 2026-10-16T22:33:17.0839833Z; Attributes Archive
    DwemerAI4Skyrim3: D:\DwemerAI4Skyrim3\ext4.vhdx
      Length 184905891840; LastWriteTimeUtc 2026-10-16T17:33:22.4859084Z; Attributes numeric 1703968

## Test-distro identity preflight

Command (PowerShell invocation; doubled apostrophes quote shell single quotes):
    wsl.exe --distribution DwemerAI4Skyrim3-test --exec /bin/bash -lc 'set -eu; if [ "${WSL_DISTRO_NAME-}" != "DwemerAI4Skyrim3-test" ]; then printf "DISTRO_ASSERT_FAILED=%s\\n" "${WSL_DISTRO_NAME-<unset>}"; exit 97; fi; printf "WSL_DISTRO_NAME=%s\\n" "$WSL_DISTRO_NAME"; id; printf "TMPDIR=%s\\n" "${TMPDIR:-unset}"; php -r ''printf("PHP_TMPDIR=%s\\n", sys_get_temp_dir());''; php -v | sed -n ''1p''; source=/mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev; if [ -d "$source" ]; then printf "SOURCE_PATH_EXISTS=yes\\nSOURCE_PATH="; realpath "$source"; else printf "SOURCE_PATH_EXISTS=no\\n"; exit 98; fi; printf "TEST_DISTRO_PREFLIGHT=PASS\\n"'

Exit code: 0

Output:
    WSL_DISTRO_NAME=DwemerAI4Skyrim3-test
    uid=0(root) gid=0(root) groups=0(root)
    TMPDIR=unset
    PHP_TMPDIR=/tmp
    PHP 8.2.29 (cli) (built: Jul  3 2025 16:16:05) (NTS)
    SOURCE_PATH_EXISTS=yes
    SOURCE_PATH=/mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev
    TEST_DISTRO_PREFLIGHT=PASS

The VHD metadata above reports Windows file size/time/attributes only. It is not a hash or a content-preservation result. The live distro was not started.
