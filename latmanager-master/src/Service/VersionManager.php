<?php

namespace App\Service;

use Exception;

class VersionManager
{
    private string $versionFile;
    private string $changelogFile;
    private array $versions;

    public function __construct(string $projectDir)
    {
        $this->versionFile = $projectDir.'/versionning/versions.json';
        $this->changelogFile = $projectDir.'/CHANGELOG.md';
        $this->loadVersions();
    }

    private function loadVersions(): void
    {
        if (file_exists($this->versionFile)) {
            $this->versions = json_decode(file_get_contents($this->versionFile), true);
        } else {
            $this->versions = [
                'manager_version' => '1.0.0',
                'Praxedo_articles' => '1.0.0',
                'Praxedo_activities' => '1.0.0',
                'Wavesoft_currency' => '1.0.0',
            ];
            $this->saveVersions();
        }
    }

    private function saveVersions(): void
    {
        file_put_contents($this->versionFile, json_encode($this->versions, JSON_PRETTY_PRINT));
    }

    /**
     * @throws Exception
     */
    public function incrementVersion(string $type = 'patch', string $component = 'manager_version'): string
    {
        if (!isset($this->versions[$component])) {
            throw new Exception("Component $component not found");
        }

        $version = explode('.', $this->versions[$component]);

        switch ($type) {
            case 'major':
                $version[0]++;
                $version[1] = 0;
                $version[2] = 0;
                break;
            case 'minor':
                $version[1]++;
                $version[2] = 0;
                break;
            case 'patch':
                $version[2]++;
                break;
            default:
                throw new Exception('Type de version invalide');
        }

        $this->versions[$component] = implode('.', $version);
        $this->saveVersions();

        return $this->versions[$component];
    }

    public function addChangelogEntry(string $version, string $type, string $message): void
    {
        $date = date('Y-m-d');
        $content = file_exists($this->changelogFile) ? file_get_contents($this->changelogFile) : "# Changelog\n";

        preg_match('/\[(.*?)\]/', $message, $matches);
        $component = $matches[1] ?? 'global';
        $cleanMessage = trim(str_replace("[$component]", '', $message));

        $entry = "\n## $component v$version - $date\n\n### $type\n- $cleanMessage\n";

        $pos = strpos($content, "\n## ") ?: strlen($content);
        $newContent = substr_replace($content, $entry, $pos, 0);

        file_put_contents($this->changelogFile, $newContent);
    }

    public function getVersions(): array
    {
        return $this->versions;
    }
} 