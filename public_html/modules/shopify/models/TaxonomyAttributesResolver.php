<?php
declare(strict_types=1);

namespace app\modules\shopify\models;

class TaxonomyAttributesResolver
{
    public static function resolveValue(string $label, string $valueGid, $filePath) 
    {
        // $file = 'attribute_values.json';

        // $localAttributesJsonPath = __DIR__ . '/taxonomy/' . $lang . '/' . $file;

        if (!is_file($filePath) || !is_readable($filePath)) {
            echo 'Plik nie istnieje lub brak uprawnień do odczytu: ' . $filePath . PHP_EOL;
            return null;
        }

        static $cache = []; // cache per-plik
        $cacheKey = realpath($filePath) ?: $filePath;

        if (!isset($cache[$cacheKey])) {
            echo '>--------- cache load - value' . PHP_EOL;

            $json = file_get_contents($filePath);

            if ($json === false) {
                echo 'Nie mogę odczytać: ' . $filePath . PHP_EOL;
                return null;
            }

            $decoded = json_decode($json, true);

            if (!is_array($decoded)) {
                echo 'Błędny format attributes.json (spodziewana tablica atrybutów).' . PHP_EOL;
                return null;
            }

            $cache[$cacheKey] = $decoded;
        }

        /** @var array<int,array<string,mixed>> $allAttributes */
        $values = $cache[$cacheKey]['values'];

        $value = null;

        foreach ($values as $val) {
            if (($val['id'] ?? null) === $valueGid) {
                $value = $val;
                break;
            }
        }

        if (!$value || !is_array($values)) {
            echo 'Nie znaleziono atrybutu o handle: ' . $valueGid . PHP_EOL;
            return null;
        }

        return [
            'NAME' => $label,
            'VALUE' => $value['name']
        ];
    }

    public static function resolveValues(string $attributeHandle, array $valueGids, $filePath) 
    {
        if ($attributeHandle === '' || $valueGids === []) {
            return [];
        }

        if (!is_file($filePath) || !is_readable($filePath)) {
            echo 'Plik nie istnieje lub brak uprawnień do odczytu: ' . $filePath . PHP_EOL;
            return null;
        }

        static $cache = []; // cache per-plik
        $cacheKey = realpath($filePath) ?: $filePath;

        if (!isset($cache[$cacheKey])) {
            echo '>--------- cache load - values' . PHP_EOL;

            $json = file_get_contents($filePath);

            if ($json === false) {
                echo 'Nie mogę odczytać: ' . $filePath . PHP_EOL;
                return null;
            }

            $decoded = json_decode($json, true);

            if (!is_array($decoded)) {
                echo 'Błędny format attributes.json (spodziewana tablica atrybutów).' . PHP_EOL;
                return null;
            }

            $cache[$cacheKey] = $decoded;
        }

        /** @var array<int,array<string,mixed>> $allAttributes */
        $allAttributes = $cache[$cacheKey]['attributes'];

        $attribute = null;

        foreach ($allAttributes as $attr) {
            if (($attr['handle'] ?? null) === $attributeHandle) {
                $attribute = $attr;
                break;
            }
        }

        if ($attribute === null) {
            echo 'Nie znaleziono atrybutu o handle: ' . $attributeHandle . PHP_EOL;
            return null;
        }

        $values = $attribute['values'] ?? [];

        if (!is_array($values)) {
            $values = [];
        }

        $params = [];

        foreach ($values as $value) {
            $params[] = [
                'NAME' => $attribute['name'],
                'VALUE' => $value['name']
            ];
        }
        
        return $params;
    }
}