<?php

namespace App\Support;

final class MachineDesignFormConfig
{
    /** @return array<string, bool> */
    public static function defaults(): array
    {
        return [
            'machineType' => true,
            'category' => true,
            'designTypes' => true,
            'designSpecifications' => true,
            'sequenceType' => true,
            'designFileType' => true,
            'previewImages' => true,
            'stitchFiles' => true,
            'pricing' => true,
        ];
    }

    /** @return array<string, bool> */
    public static function normalize(?array $config): array
    {
        $normalized = self::defaults();

        foreach ($normalized as $key => $default) {
            if (array_key_exists($key, $config ?? [])) {
                $normalized[$key] = (bool) $config[$key];
            }
        }

        if (! $normalized['category']) {
            $normalized['designTypes'] = false;
            $normalized['designSpecifications'] = false;
        }

        if (! $normalized['designTypes']) {
            $normalized['designSpecifications'] = false;
        }

        return $normalized;
    }
}
