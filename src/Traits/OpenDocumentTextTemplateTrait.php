<?php declare(strict_types=1);

namespace BulkExport\Traits;

use PhpOffice\PhpWord;

trait OpenDocumentTextTemplateTrait
{
    /**
     * @var \PhpOffice\PhpWord\PhpWord
     */
    protected $openDocument;

    protected function initializeOpenDocumentText()
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $user = $services->get('Omeka\AuthenticationService')->getIdentity();
        $user = $user ? $user->getName() : $this->translator->translate('Anonymous'); // @translate
        $languageCode = $this->translator->getDelegatedTranslator()->getLocale();
        $languageCode = str_replace('_', '-', $languageCode);

        $this->openDocument = new PhpWord\PhpWord();
        $this->openDocument
            ->addParagraphStyle(
                'pRecordLabel', [
                    'spacing' => 480,
                    'indent' => 0,
                    'keepNext' => true,
                ]
            );
        $this->openDocument
            ->addParagraphStyle(
                'pRecordMetadata', [
                    'spacing' => 240,
                    'indent' => 240,
                    'keepNext' => true,
                ]
            );
        $this->openDocument
            ->addFontStyle(
                'recordLabel',
                ['name' => 'Arial', 'size' => 12, 'color' => '1B2232', 'bold' => true]
            );
        $this->openDocument
            ->addFontStyle(
                'recordMetadata',
                ['name' => 'Arial', 'size' => 12]
            );

        $this->openDocument->getDocInfo()
            ->setCreator($user)
            ->setCompany($settings->get('installation_title'))
            ->setTitle(sprintf(
                'Bibliography from %s', // @translate
                $settings->get('installation_title')
            ))
            ->setDescription($this->translator->translate('Bibliography')) // @translate
            ->setCategory($this->translator->translate('Bibliography')) // @translate
            ->setLastModifiedBy($user)
            ->setCreated(time())
            ->setModified(time())
            ->setSubject($this->translator->translate('Bibliography')) // @translate
            ->setKeywords(
                $this->translator->translate('Bibliography'), // @translate
                $this->translator->translate('Digital library') // @translate
            );

        $this->openDocument->getSettings()
            ->setThemeFontLang(new PhpWord\Style\Language($languageCode));

        return $this;
    }

    protected function writeFields(array $fields)
    {
        $section = $this->openDocument->addSection(['breakType' => 'continuous']);
        foreach ($fields as $fieldName => $fieldValues) {
            if (!is_array($fieldValues)) {
                $fieldValues = [$fieldValues];
            }
            if ($this->options['format_fields'] === 'label') {
                $fieldName = $this->getFieldLabel($fieldName);
            }
            $section->addText($fieldName, 'recordLabel', 'pRecordLabel');
            foreach ($fieldValues as $fieldValue) {
                $fieldValue = strip_tags($fieldValue);
                if (mb_strlen($fieldValue) < 1000) {
                    $section->addText($fieldValue, 'recordMetadata', 'pRecordMetadata');
                } else {
                    $this->logger->warn(
                        'Skipped field "{fieldname}" of resource: it contains more than 1000 characters.', // @translate
                        ['fieldname' => $fieldName]
                    );
                }
            }
        }
        $section->addText('--');
        $section->addTextBreak();
        return $this;
    }
}
