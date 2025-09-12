<?php

namespace S2Hub\TextAssistant\Extensions;

use S2Hub\TextAssistant\Models\TranslationAction;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Core\Extension;

class DataObjectTranslationActionExtension extends Extension
{

    public static function getGridFieldFilterFields(): FieldList
    {
        $statusOptions = singleton(TranslationAction::class)->dbObject('Status')->enumValues();
        
        foreach ($statusOptions as $key => $value) {
            $statusOptions[$key] = _t(TranslationAction::class.'.STATUS_'.strtoupper($key), $value);
        }


        $fields = new FieldList([
            ListboxField::create('Status', _t(TranslationAction::class.'.STATUS', 'Status'), $statusOptions)
        ]);

        return $fields;
    }

    public function onAfterPublish()
    {
        if ($this->owner->hasExtension(Versioned::class)) {

            // all translations in draft stage is assumed to be accepted,
            // since they were put into draft stage when created. If we're publishing it now means we're implicitly moving them to accepted stage.
            // If we run the translation job, these will be deleted instead..
            $translations = TranslationAction::get()->filter([
                'ObjectID' => $this->owner->ID,
                'ObjectClass' => get_class($this->owner),
                'Status' => 'Draft'
            ]);

            if ($translations) {
                foreach ($translations as $translation) {
                    $translation->Status = 'Accepted';
                    $translation->write();
                }
            }
                
        }
    }


    public function onAfterWrite()
    {
        if (!$this->owner->hasExtension(Versioned::class)) {
            // If object is not versioned, when saved TranslationActions becomes Accepted.

            $translations = TranslationAction::get()->filter([
                'ObjectID' => $this->owner->ID,
                'ObjectClass' => get_class($this->owner),
                'Status' => 'Draft'
            ]);

            if ($translations) {
                foreach ($translations as $translation) {
                    $translation->Status = 'Accepted';
                    $translation->write();
                }
            }
        }
    }
}