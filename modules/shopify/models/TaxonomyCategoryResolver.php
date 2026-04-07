<?php
declare(strict_types=1);

namespace app\modules\shopify\models;

use RuntimeException;
use SplFileObject;

/**
 * TaxonomyCategoryResolver (PHP 7.3)
 *
 * File format (each line):
 * gid://shopify/TaxonomyCategory/sg-4-17-2-17    : A > B > ... > Z
 */
final class TaxonomyCategoryResolver
{
    /** @var string */
    private $path;
    /** @var string */
    private $cacheKey;
    /** @var bool */
    private $loaded = false;

    /**
     * Cache shared between instances:
     * key: cacheKey (realpath::mtime)
     * value: array<string,string>  map: gid => "A > B > ... > Z"
     * @var array<string,array<string,string>>
     */
    private static $cache = [];

    /**
     * @param string $txtPath Path to the local TXT file with categories
     * @throws RuntimeException
     */
    public function __construct($txtPath)
    {
        if (!is_file($txtPath) || !is_readable($txtPath)) {
            throw new RuntimeException('Plik nie istnieje lub brak dostępu: ' . $txtPath);
        }
        $real = realpath($txtPath);
        $this->path = $real ? $real : $txtPath;
        $mtime = (string) filemtime($txtPath);
        $this->cacheKey = $this->path . '::' . $mtime;
    }

    /**
     * Returns the leaf (last segment) for the specified category GID.
     * @param string $categoryGid
     * @return string|null
     */
    public function getLeaf($categoryGid)
    {
        $breadcrumb = $this->getBreadcrumb($categoryGid);
        if ($breadcrumb === null) {
            return null;
        }
        return self::leafFromBreadcrumb($breadcrumb);
    }

    /**
     * Returns the full breadcrumb for the specified category GID.
     * @param string $categoryGid
     * @return string|null
     */
    public function getBreadcrumb($categoryGid)
    {
        $map = $this->load();
        return isset($map[$categoryGid]) ? $map[$categoryGid] : null;
    }

    /**
     * Batch: [gid => leaf|null]
     * @param string[] $categoryGids
     * @return array<string,string|null>
     */
    public function getLeafMany(array $categoryGids)
    {
        $map = $this->load();
        $out = [];
        foreach ($categoryGids as $gid) {
            $breadcrumb = isset($map[$gid]) ? $map[$gid] : null;
            $out[$gid] = $breadcrumb !== null ? self::leafFromBreadcrumb($breadcrumb) : null;
        }
        return $out;
    }

    /**
     * Batch: [gid => breadcrumb|null]
     * @param string[] $categoryGids
     * @return array<string,string|null>
     */
    public function getBreadcrumbMany(array $categoryGids)
    {
        $map = $this->load();
        $out = [];
        foreach ($categoryGids as $gid) {
            $out[$gid] = isset($map[$gid]) ? $map[$gid] : null;
        }
        return $out;
    }

    /**
     * Forces the file to be parsed (e.g., after it has been replaced).
     * Note: if the mtime has changed, a new cache entry will be created anyway.
     * @return void
     */
    public function refresh()
    {
        $this->loaded = false;
        // we do not clear the global cache; a new mtime will generate a new key
        $this->load();
    }

    /**
     * Parses TXT line-by-line and returns a gid => breadcrumb map.
     * Uses cache per file (realpath::mtime).
     * @return array<string,string>
     */
    private function load()
    {
        if ($this->loaded && isset(self::$cache[$this->cacheKey])) {
            return self::$cache[$this->cacheKey];
        }

        // If we already have it in the cache (e.g., another instance with the same file)
        if (isset(self::$cache[$this->cacheKey])) {
            $this->loaded = true;
            return self::$cache[$this->cacheKey];
        }

        $map = [];
        $file = new SplFileObject($this->path, 'r');
        $file->setFlags(SplFileObject::DROP_NEW_LINE | SplFileObject::SKIP_EMPTY);

        // Regex: [GID] : [breadcrumb]
        $re = '/^\s*(gid:\/\/shopify\/TaxonomyCategory\/\S+)\s*:\s*(.+?)\s*$/u';

        foreach ($file as $line) {
            if (!is_string($line) || $line === '') {
                continue;
            }
            if (preg_match($re, $line, $m)) {
                $gid = $m[1];
                $breadcrumb = $m[2];
                $map[$gid] = $breadcrumb;
            }
        }

        self::$cache = [$this->cacheKey => $map];
        $this->loaded = true;
        return $map;
    }

    /**
     * Extracts the last segment from the breadcrumb "A > B > ... > Z" => "Z".
     * @param string $breadcrumb
     * @return string
     */
    public static function leafFromBreadcrumb($breadcrumb)
    {
        $parts = explode('>', $breadcrumb);
        $clean = [];
        foreach ($parts as $p) {
            $t = trim($p);
            if ($t !== '') {
                $clean[] = $t;
            }
        }
        if (empty($clean)) {
            return '';
        }
        return $clean[count($clean) - 1];
    }
}
