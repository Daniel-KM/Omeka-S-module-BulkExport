<?php declare(strict_types=1);

namespace BulkExport\Writer;

use BulkExport\Form\Writer\TextWriterConfigForm;
use Common\Stdlib\PsrMessage;

/**
 * ODT Writer - thin wrapper around Odt Formatter.
 *
 * @see \BulkExport\Formatter\Odt for the actual ODT formatting logic
 */
class OpenDocumentTextWriter extends AbstractFormatterWriter
{
    protected $label = 'OpenDocument Text'; // @translate
    protected $extension = 'odt';
    protected $mediaType = 'application/vnd.oasis.opendocument.text';
    protected $configFormClass = TextWriterConfigForm::class;
    protected $paramsFormClass = TextWriterConfigForm::class;

    /**
     * The formatter to delegate to.
     */
    protected $formatterName = 'odt';

    protected $configKeys = [
        'dirpath',
        'filebase',
        'format_fields',
        'format_fields_labels',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'language',
        'resource_types',
        'metadata',
        'metadata_exclude',
        'metadata_shapers',
        'query',
        'zip_files',
        'incremental',
        'include_deleted',
    ];

    protected $paramsKeys = [
        'dirpath',
        'filebase',
        'format_fields',
        'format_fields_labels',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'language',
        'resource_types',
        'metadata',
        'metadata_exclude',
        'metadata_shapers',
        'query',
        'zip_files',
        'incremental',
        'include_deleted',
    ];

    public function isValid(): bool
    {
        if (!extension_loaded('zip') || !extension_loaded('xml')) {
            $this->lastErrorMessage = new PsrMessage(
                'To process export of "{label}", the php extensions "zip" and "xml" are required.', // @translate
                ['label' => $this->getLabel()]
            );
            return false;
        }
        return parent::isValid();
    }
}
