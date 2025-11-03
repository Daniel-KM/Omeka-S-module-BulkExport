<?php declare(strict_types=1);

namespace BulkExport\Traits;

trait ShaperTrait
{
    /**
     * Get the settings via a shapper id or label.
     */
    protected function shaperSettings(?string $shaper): array
    {
        static $shapers = [
            '' => [],
        ];

        if (isset($shapers[$shaper])) {
            return $shapers[$shaper];
        }

        $shaperId = is_numeric($shaper) ? (int) $shaper : (string) $shaper;

        try {
            /** @var \BulkExport\Api\Representation\ShaperRepresentation $shaper */
            $shaper = $this->api->read('bulk_shapers', is_numeric($shaperId)
                ? ['id' => $shaperId]
                : ['label' => $shaperId],
            )->getContent();
            $shapers[$shaperId] = $shaper->config();
        } catch (\Exception $e) {
            $shapers[$shaperId] = [];
        }

        return $shapers[$shaperId];
    }

    protected function shapeValues(array $values, array $shaperParams): array
    {
        if (!$values || !$shaperParams) {
            return $values;
        }

        $normalizations = $shaperParams['normalization'] ?? [];
        $maxLength = empty($shaperParams['max_length']) ? 0 : (int) $shaperParams['max_length'];

        $result = [];
        foreach ($values as $value) {
            if ($value === '' || $value === null) {
                $result[] = $value;
                continue;
            }

            if ($normalizations) {
                if (is_bool($value)) {
                    $value = (int) $value;
                }

                if (in_array('html_escaped', $normalizations)) {
                    // New default for php 8.1.
                    // @link https://forum.omeka.org/t/solr-text-field-length/13430
                    $value = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);
                }

                if (in_array('strip_tags', $normalizations)) {
                    $value = strip_tags((string) $value);
                }

                if (in_array('lowercase', $normalizations)) {
                    $value = mb_strtolower((string) $value);
                }

                if (in_array('uppercase', $normalizations)) {
                    $value = mb_strtoupper((string) $value);
                }

                if (in_array('ucfirst', $normalizations)) {
                    $value = mb_ucfirst((string) $value);
                }

                if (in_array('remove_diacritics', $normalizations)) {
                    if (extension_loaded('intl')) {
                        $transliterator = \Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;');
                        $value = $transliterator->transliterate((string) $value);
                    } elseif (extension_loaded('iconv')) {
                        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $value);
                    }
                }

                if (in_array('alphanumeric', $normalizations)) {
                    // Remove space recursively.
                    $value = str_replace('  ', ' ', preg_replace('~[^\p{L}\p{N}-]++~u', ' ', (string) $value));
                }

                if (in_array('alphabetic', $normalizations)) {
                    // Remove space recursively.
                    $value = str_replace('  ', ' ', preg_replace('~[^\p{L}\p{N}-]++~u', ' ', (string) $value));
                    // Remove digits.
                    $value = preg_replace('~\p{N}+~u', '', (string) $value);
                }

                if (in_array('max_length', $normalizations)) {
                    $maxLength = !empty($this->settings['max_length'])
                        ? (int) $this->settings['max_length']
                        : 0;
                    if ($maxLength) {
                        $value = mb_substr((string) $value, 0, $maxLength);
                    }
                }

                if (in_array('integer', $normalizations)) {
                    $value = (int) $value;
                }

                if (in_array('year', $normalizations)) {
                    $value = (int) $value ?: null;
                }

                if (in_array('table', $normalizations)) {
                    $value = $this->formatTable($value);
                }
            }

            if ($maxLength) {
                $value = mb_substr((string) $value, 0, $maxLength);
            }

            $result[] = $value;
        }

        return $result;
    }
}
