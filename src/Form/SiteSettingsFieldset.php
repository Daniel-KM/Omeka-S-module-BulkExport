<?php declare(strict_types=1);

namespace BulkExport\Form;

class SiteSettingsFieldset extends SettingsFieldset
{
    public function init(): void
    {
        parent::init();

        $this
            ->get('bulkexport_limit')
            ->setOption('info', null);
    }
}
