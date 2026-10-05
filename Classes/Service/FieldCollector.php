<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Service;

use Supertext\Typo3Translation\Configuration\ExtensionSettings;

/**
 * Decides which fields of a record carry translatable text, based on TCA.
 */
final class FieldCollector
{
    private const SYSTEM_FIELDS = ['l10n_diffsource', 'l18n_diffsource', 't3ver_label', 'TSconfig', 'tsconfig_includes', 'slug'];
    /**
     * Technical fields that TCA declares as plain text inputs. Translating them breaks
     * records: e.g. sys_file_reference.fieldname "image" -> "Bild" detaches the image.
     */
    private const TECHNICAL_FIELDS = [
        'sys_file_reference' => ['tablenames', 'fieldname', 'table_local'],
        'pages' => ['target'],
        'tt_content' => ['target'],
    ];
    private const SKIPPED_RENDER_TYPES = ['codeEditor', 't3editor', 'colorpicker', 'belayoutwizard'];

    public function __construct(private readonly ExtensionSettings $settings) {}

    /**
     * @param array<string, mixed> $row source record
     * @return array<string, bool> field name => is rich text (HTML)
     */
    public function collect(string $table, array $row): array
    {
        $tca = $GLOBALS['TCA'][$table] ?? null;
        if (!is_array($tca)) {
            return [];
        }
        $typeField = $tca['ctrl']['type'] ?? null;
        $type = is_string($typeField) && !str_contains($typeField, ':') ? (string)($row[$typeField] ?? '') : '';
        $overrides = $type !== '' ? ($tca['types'][$type]['columnsOverrides'] ?? []) : [];

        $fields = [];
        foreach ($tca['columns'] ?? [] as $field => $column) {
            if (in_array($field, self::SYSTEM_FIELDS, true)
                || in_array($field, self::TECHNICAL_FIELDS[$table] ?? [], true)
                || !array_key_exists($field, $row)
            ) {
                continue;
            }
            $config = array_replace_recursive($column['config'] ?? [], $overrides[$field]['config'] ?? []);
            $kind = (string)($config['type'] ?? '');
            if (!in_array($kind, ['input', 'text'], true)) {
                continue;
            }
            if (($column['l10n_mode'] ?? '') === 'exclude' || !empty($config['readOnly'])) {
                continue;
            }
            if (in_array((string)($config['renderType'] ?? ''), self::SKIPPED_RENDER_TYPES, true) || !empty($config['format'])) {
                continue;
            }
            if ($kind === 'input' && preg_match('/\b(int|num|double2|date|datetime|time|md5|password|email|alphanum)\b/', (string)($config['eval'] ?? ''))) {
                continue;
            }
            if ($table === 'tt_content' && $field === 'bodytext' && in_array($type, $this->settings->getSkipBodytextCTypes(), true)) {
                continue;
            }
            $value = $row[$field];
            if (!is_string($value) || trim($value) === '' || is_numeric(trim($value))) {
                continue;
            }
            $isRichText = $kind === 'text' && (!empty($config['enableRichtext']) || preg_match('/<\/?(p|br|strong|em|ul|ol|li|h[1-6]|a|span|div|table)\b[^>]*>/i', $value) === 1);
            $fields[$field] = $isRichText;
        }
        return $fields;
    }
}
