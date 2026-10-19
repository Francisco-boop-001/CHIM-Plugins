<?php

declare(strict_types=1);

function updateCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function tokenText(array|string $token): string
{
    return is_array($token) ? $token[1] : $token;
}

function loadSourceFunction(string $source, string $name): void
{
    $tokens = token_get_all($source);
    foreach ($tokens as $index => $token) {
        if (!is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }
        $nameIndex = $index + 1;
        while (isset($tokens[$nameIndex]) && is_array($tokens[$nameIndex]) && in_array($tokens[$nameIndex][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $nameIndex++;
        }
        if (($tokens[$nameIndex] ?? null) === '&') {
            $nameIndex++;
        }
        while (isset($tokens[$nameIndex]) && is_array($tokens[$nameIndex]) && $tokens[$nameIndex][0] === T_WHITESPACE) {
            $nameIndex++;
        }
        if (!isset($tokens[$nameIndex]) || !is_array($tokens[$nameIndex]) || $tokens[$nameIndex][0] !== T_STRING || $tokens[$nameIndex][1] !== $name) {
            continue;
        }

        $open = $nameIndex;
        while (isset($tokens[$open]) && tokenText($tokens[$open]) !== '{') {
            $open++;
        }
        updateCheck(isset($tokens[$open]), "Could not find body for {$name}.");
        $depth = 0;
        $declaration = '';
        for ($cursor = $index; isset($tokens[$cursor]); $cursor++) {
            $text = tokenText($tokens[$cursor]);
            $declaration .= $text;
            if ($text === '{') {
                $depth++;
            } elseif ($text === '}' && --$depth === 0) {
                eval($declaration);
                return;
            }
        }
        updateCheck(false, "Could not finish extracting {$name}.");
    }
    updateCheck(false, "Installed source does not define {$name}.");
}

function getPluginManagerManifestVersionFromUrl(string $url): string
{
    updateCheck($url === $GLOBALS['expectedManifestUrl'], 'Manager requested an unexpected manifest URL.');
    return $GLOBALS['remoteManifest']['version'];
}

function chimPluginInstallerFetchUrl(string $url): string|false
{
    updateCheck($url === $GLOBALS['expectedManifestUrl'], 'Installer fetch escaped the fixture manifest URL.');
    return json_encode($GLOBALS['remoteManifest'], JSON_THROW_ON_ERROR);
}

function managerShowsUpdate(array $manifest): bool
{
    $gitRepo = $manifest['git_repo'] ?? '';
    return isset($manifest['schema_version']) && $manifest['schema_version'] == 2 && !empty($gitRepo);
}

function installedSource(string $path): string
{
    $contents = file_get_contents($path);
    updateCheck(is_string($contents), "Could not read installed consumer source: {$path}");
    return $contents;
}

try {
    $projectRoot = realpath(dirname(__DIR__));
    updateCheck(is_string($projectRoot), 'Could not resolve project root.');
    $serverRoot = getenv('CHIM_HERIKASERVER_ROOT') ?: '/var/www/html/HerikaServer';
    $managerSource = installedSource($serverRoot . '/ui/server_plugins.php');
    $installerSource = installedSource($serverRoot . '/ui/server_plugin_installer.php');

    foreach (['findPluginRepositoryEntry', 'normalizePluginManagerChannels', 'buildPluginInstallerUrl', 'getPluginManagerChannelVersion'] as $name) {
        loadSourceFunction($managerSource, $name);
    }
    foreach (['chimPluginInstallerFindRepositoryEntry', 'chimPluginInstallerReplaceTokens', 'chimPluginInstallerNormalizeChannels', 'chimPluginInstallerGetRemoteManifest'] as $name) {
        loadSourceFunction($installerSource, $name);
    }

    $manifest = json_decode((string)file_get_contents($projectRoot . '/server/manifest.json'), true, 64, JSON_THROW_ON_ERROR);
    $catalog = json_decode((string)file_get_contents($projectRoot . '/distribution/plugin_repository_entry.json'), true, 64, JSON_THROW_ON_ERROR)['plugins'];
    $entry = $catalog['mind-poisoning'];
    $repo = 'Francisco-boop-001/CHIM-MindPoisoning';
    $legacyRepo = 'Francisco-boop-001/CHIM-Plugins';
    updateCheck($manifest['name'] === 'mind_poisoning', 'Unexpected plugin package name.');
    updateCheck(is_string($manifest['version']) && preg_match('/^([0-9]+)\.([0-9]+)\.([0-9]+)$/D', $manifest['version'], $versionParts) === 1, 'Manifest version must remain a three-part numeric version.');
    updateCheck(($manifest['schema_version'] ?? null) === 2 && $manifest['git_repo'] === $repo, 'Plugin manifest lacks its unique update-flow identity.');
    updateCheck($entry['git_repo'] === $repo && $entry['github_url'] === 'https://github.com/' . $repo, 'Catalog entry does not use the unique Mind Poisoning repository.');
    updateCheck($manifest['channels']['candidate']['manifest_url'] === 'https://raw.githubusercontent.com/' . $repo . '/main/server/manifest.json', 'Candidate manifest URL does not point to the deployment repository root.');
    updateCheck($manifest['channels']['candidate']['package_urls'] === ['https://github.com/' . $repo . '/releases/download/mind_poisoning-v<version>/mind_poisoning.tar.gz'], 'Candidate package URL must use the versioned deployment release template.');
    updateCheck($manifest['server_compatibility_reference'] === '57f292415b008cfaf1a4531f0654d2563362ce70', 'Compatibility reference changed.');

    $preSchema2Installed = ['name' => 'mind_poisoning', 'version' => '0.1.3', 'git_repo' => $legacyRepo];
    $legacyInstalled = ['name' => 'mind_poisoning', 'version' => '0.1.13', 'schema_version' => 2, 'git_repo' => $legacyRepo];
    $gate = '/if\s*\(\s*isset\(\$manifest\[[\'\"]schema_version[\'\"]\]\)\s*&&\s*\$manifest\[[\'\"]schema_version[\'\"]\]\s*==\s*2\s*&&\s*!empty\(\$gitRepo\)\s*\)\s*\{/';
    updateCheck(preg_match($gate, $managerSource) === 1, 'Installed manager update button gate changed; review this regression check.');
    updateCheck(!managerShowsUpdate($preSchema2Installed) && managerShowsUpdate($legacyInstalled) && managerShowsUpdate($manifest), 'Schema-2 update gate does not distinguish legacy, bridge and deployment manifests.');
    updateCheck(str_contains($installerSource, '$manifest["channel"] = $channel["id"];') && str_contains($installerSource, 'if (!isset($manifest["git_repo"]))'), 'Installer no longer records channel and repository identity on install.');

    $GLOBALS['expectedManifestUrl'] = $manifest['channels']['candidate']['manifest_url'];
    $GLOBALS['remoteManifest'] = $manifest;
    $remoteVersion = $versionParts[1] . '.' . $versionParts[2] . '.' . ((int)$versionParts[3] + 1);
    $GLOBALS['remoteManifest']['version'] = $remoteVersion;
    $repositoryEntry = findPluginRepositoryEntry($catalog, $manifest, 'mind_poisoning');
    updateCheck(is_array($repositoryEntry) && $repositoryEntry['_plugin_id'] === 'mind-poisoning', 'Manager did not resolve the package-specific catalog entry.');
    $managerChannels = normalizePluginManagerChannels($repositoryEntry, $manifest['name'], $repo);
    $managerChannel = $managerChannels['candidate'] ?? null;
    updateCheck(is_array($managerChannel) && $managerChannel['manifest_url'] === $GLOBALS['expectedManifestUrl'], 'Manager candidate channel does not read the per-plugin manifest on main.');
    updateCheck(getPluginManagerChannelVersion($repo, $managerChannel) === $remoteVersion, 'Manager did not read the fixture remote version.');
    updateCheck(version_compare($remoteVersion, $manifest['version'], '>'), 'Remote version comparison did not detect the next release.');

    $updateUrl = buildPluginInstallerUrl($repositoryEntry['_plugin_id'], $manifest['name'], $repo, 'candidate', $managerChannel['allow_force']);
    parse_str((string)parse_url($updateUrl, PHP_URL_QUERY), $updateQuery);
    updateCheck(($updateQuery['CHANNEL'] ?? null) === 'candidate' && !isset($updateQuery['FORCE']), 'Candidate update URL should use normal version comparison.');

    $installerChannels = chimPluginInstallerNormalizeChannels($repositoryEntry, $manifest['name'], $repo);
    $installerChannel = $installerChannels['candidate'] ?? null;
    updateCheck(is_array($installerChannel), 'Installer did not normalize the candidate channel.');
    $remote = chimPluginInstallerGetRemoteManifest($installerChannel, $repo);
    updateCheck(is_array($remote) && $remote['version'] === $remoteVersion, 'Installer did not read the fixture remote manifest.');
    updateCheck(str_contains($installerSource, 'return strtr($url, ["<version>" => $remoteVersion]);'), 'Installer version-token expansion changed; review the test.');
    $resolvedUrls = array_map(static fn(string $url): string => strtr($url, ['<version>' => $remote['version']]), $installerChannel['package_urls']);
    updateCheck($resolvedUrls === ['https://github.com/' . $repo . '/releases/download/mind_poisoning-v' . $remoteVersion . '/mind_poisoning.tar.gz'], 'Candidate package URL did not resolve to the matching plugin version.');
    updateCheck(!str_contains($resolvedUrls[0], 'releases/latest'), 'Unqualified latest-release URL is not allowed.');

    updateCheck(findPluginRepositoryEntry([], $manifest, 'mind_poisoning') === false, 'An absent catalog should not resolve an install entry.');
    $localChannels = normalizePluginManagerChannels($manifest, $manifest['name'], $repo);
    updateCheck(isset($localChannels['candidate']), 'Installed manifest cannot supply its own update channel when catalog lookup misses.');
    updateCheck(chimPluginInstallerFindRepositoryEntry([], '', $manifest['name'], $repo) === false, 'An absent catalog should not resolve an installer entry.');
    $fallbackChannels = chimPluginInstallerNormalizeChannels($manifest, $manifest['name'], $repo);
    updateCheck(isset($fallbackChannels['candidate']), 'Installer cannot fall back to channels in an installed manifest.');
    updateCheck(str_contains($installerSource, '$channelSource = is_array($repositoryEntry) ? $repositoryEntry : (is_array($localManifest) ? $localManifest : []);'), 'Installer local-manifest fallback changed; review this regression check.');

    $pcvManifest = json_decode((string)file_get_contents($projectRoot . '/plugins/private_conversation/server/manifest.json'), true, 64, JSON_THROW_ON_ERROR);
    $pcvRepo = 'Francisco-boop-001/CHIM-PrivateConversation';
    updateCheck($pcvManifest['name'] === 'private_conversation' && $pcvManifest['git_repo'] === $pcvRepo, 'Private Conversation does not have its separate deployment identity.');
    updateCheck(is_string($pcvManifest['version']) && preg_match('/^([0-9]+)\.([0-9]+)\.([0-9]+)$/D', $pcvManifest['version'], $pcvVersionParts) === 1, 'Private Conversation manifest version must remain a three-part numeric version.');
    updateCheck($pcvManifest['channels']['candidate']['manifest_url'] === 'https://raw.githubusercontent.com/' . $pcvRepo . '/main/server/manifest.json', 'Private Conversation manifest URL does not point to its deployment repository root.');
    updateCheck($pcvManifest['channels']['candidate']['package_urls'] === ['https://github.com/' . $pcvRepo . '/releases/download/private_conversation-v<version>/private_conversation.tar.gz'], 'Private Conversation package URL must use the versioned deployment release template.');
    $twoPluginCatalog = [
        'private-conversation' => $pcvManifest,
        'mind-poisoning' => $entry,
    ];
    foreach ([$twoPluginCatalog, array_reverse($twoPluginCatalog, true)] as $orderedCatalog) {
        $currentMp = findPluginRepositoryEntry($orderedCatalog, $manifest, 'mind_poisoning');
        updateCheck(is_array($currentMp) && $currentMp['_plugin_id'] === 'mind-poisoning', 'Current catalog lookup selected the wrong plugin for the MP deployment manifest.');
        $currentPcv = findPluginRepositoryEntry($orderedCatalog, $pcvManifest, 'private_conversation');
        updateCheck(is_array($currentPcv) && $currentPcv['_plugin_id'] === 'private-conversation', 'Current catalog lookup selected the wrong plugin for the PCV deployment manifest.');

        $legacyMp = findPluginRepositoryEntry($orderedCatalog, $legacyInstalled, 'mind_poisoning');
        updateCheck(is_array($legacyMp) && $legacyMp['_plugin_id'] === 'mind-poisoning', 'Legacy MP CHIM-Plugins identity did not fall back to the unique package name.');
        $legacyMpChannels = normalizePluginManagerChannels($legacyMp, $legacyInstalled['name'], $legacyRepo);
        updateCheck(($legacyMpChannels['candidate']['manifest_url'] ?? null) === $manifest['channels']['candidate']['manifest_url'], 'Legacy MP update lookup did not resolve the deployment manifest from its catalog entry.');
        $legacyMpInstaller = chimPluginInstallerFindRepositoryEntry($orderedCatalog, $legacyMp['_plugin_id'], 'mind_poisoning', $legacyRepo);
        updateCheck(is_array($legacyMpInstaller) && $legacyMpInstaller['name'] === 'mind_poisoning', 'Installer did not retain the uniquely selected legacy MP catalog identity.');

        $legacyPcv = ['name' => 'private_conversation', 'version' => '0.1.5', 'schema_version' => 2, 'git_repo' => $legacyRepo];
        $legacyPcvEntry = findPluginRepositoryEntry($orderedCatalog, $legacyPcv, 'private_conversation');
        updateCheck(is_array($legacyPcvEntry) && $legacyPcvEntry['_plugin_id'] === 'private-conversation', 'Legacy PCV CHIM-Plugins identity did not fall back to the unique package name.');
        $legacyPcvChannels = normalizePluginManagerChannels($legacyPcvEntry, $legacyPcv['name'], $legacyRepo);
        updateCheck(($legacyPcvChannels['candidate']['manifest_url'] ?? null) === $pcvManifest['channels']['candidate']['manifest_url'], 'Legacy PCV update lookup did not resolve the deployment manifest from its catalog entry.');
        $legacyPcvInstaller = chimPluginInstallerFindRepositoryEntry($orderedCatalog, $legacyPcvEntry['_plugin_id'], 'private_conversation', $legacyRepo);
        updateCheck(is_array($legacyPcvInstaller) && $legacyPcvInstaller['name'] === 'private_conversation', 'Installer did not retain the uniquely selected legacy PCV catalog identity.');
    }
    $singleMpCatalog = ['mind-poisoning' => $entry];
    $singlePcvCatalog = ['private-conversation' => $pcvManifest];
    updateCheck(findPluginRepositoryEntry($singleMpCatalog, $pcvManifest, 'private_conversation') === false, 'A single-entry MP catalog must not resolve the PCV package.');
    updateCheck(findPluginRepositoryEntry($singlePcvCatalog, $manifest, 'mind_poisoning') === false, 'A single-entry PCV catalog must not resolve the MP package.');
    $singleMpEntry = findPluginRepositoryEntry($singleMpCatalog, $manifest, 'mind_poisoning');
    $singlePcvEntry = findPluginRepositoryEntry($singlePcvCatalog, $pcvManifest, 'private_conversation');
    updateCheck(is_array($singleMpEntry) && $singleMpEntry['_plugin_id'] === 'mind-poisoning', 'Single-entry MP catalog lookup changed for its deployment identity.');
    updateCheck(is_array($singlePcvEntry) && $singlePcvEntry['_plugin_id'] === 'private-conversation', 'Single-entry PCV catalog lookup changed for its deployment identity.');

    $GLOBALS['expectedManifestUrl'] = $pcvManifest['channels']['candidate']['manifest_url'];
    $GLOBALS['remoteManifest'] = $pcvManifest;
    $pcvRemoteVersion = $pcvVersionParts[1] . '.' . $pcvVersionParts[2] . '.' . ((int)$pcvVersionParts[3] + 1);
    $GLOBALS['remoteManifest']['version'] = $pcvRemoteVersion;
    $pcvManagerChannels = normalizePluginManagerChannels($pcvManifest, $pcvManifest['name'], $pcvRepo);
    updateCheck(getPluginManagerChannelVersion($pcvRepo, $pcvManagerChannels['candidate']) === $pcvRemoteVersion, 'PCV new deployment channel did not resolve its next-version manifest.');
    updateCheck(version_compare($pcvRemoteVersion, $pcvManifest['version'], '>'), 'PCV remote version comparison did not detect the next release.');
    $pcvInstallerChannels = chimPluginInstallerNormalizeChannels($pcvManifest, $pcvManifest['name'], $pcvRepo);
    $pcvInstallerChannel = $pcvInstallerChannels['candidate'] ?? null;
    updateCheck(is_array($pcvInstallerChannel), 'PCV installer did not normalize its deployment channel.');
    $pcvRemote = chimPluginInstallerGetRemoteManifest($pcvInstallerChannel, $pcvRepo);
    updateCheck(is_array($pcvRemote) && $pcvRemote['version'] === $pcvRemoteVersion, 'PCV installer did not resolve its deployment manifest without network access.');
    $pcvResolvedUrls = array_map(static fn(string $url): string => strtr($url, ['<version>' => $pcvRemote['version']]), $pcvInstallerChannel['package_urls']);
    updateCheck($pcvResolvedUrls === ['https://github.com/' . $pcvRepo . '/releases/download/private_conversation-v' . $pcvRemoteVersion . '/private_conversation.tar.gz'], 'PCV package URL did not resolve to its versioned deployment release.');

    echo "PASS: actual installed manager helpers resolve the candidate manifest and next-version update URL.\n";
    echo "PASS: actual installer helpers fetch the fixture manifest and resolve the plugin-specific release asset without network access.\n";
    echo "PASS: legacy v0.1.3 remains outside schema-2 update eligibility; MP 0.1.13 and PCV 0.1.5 resolve their new deployment entries by unique package name.\n";
    echo "PASS: MP and PCV identities resolve correctly in both two-entry orders and reject the other plugin in single-entry catalogs.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
