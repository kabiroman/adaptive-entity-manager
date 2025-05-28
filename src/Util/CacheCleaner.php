<?php

namespace Kabiroman\AEM\Util;

use Kabiroman\AEM\ConfigInterface;

class CacheCleaner
{
    public function __construct(private readonly ConfigInterface $config)
    {
    }

    public function clearAll(): void
    {
        $this->clearEntityMetadata();
    }

    public function clearEntityMetadata(): void
    {
        $cacheMetadataFile = $this->config->getCacheFolder() . '/entity_metadata.cache';
        if (file_exists($cacheMetadataFile)) {
            unlink($cacheMetadataFile);
        }
        $entityProxyFolder = $this->config->getCacheFolder() . '/proxy/entity';
        if (is_dir($entityProxyFolder)) {
            $this->deleteFolder($entityProxyFolder);
        }
    }

    private function deleteFolder(string $dir): void
    {
        $d = opendir($dir);
        while (($entry = readdir($d)) !== false) {
            if ($entry != "." && $entry != "..") {
                if (is_dir($dir . "/" . $entry)) {
                    $this->deleteFolder($dir . "/" . $entry);
                } else {
                    unlink($dir . "/" . $entry);
                }
            }
        }
        closedir($d);
        rmdir($dir);
    }
}
