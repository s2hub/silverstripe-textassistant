<?php

namespace S2Hub\TextAssistant\Jobs;

use Exception;
use S2Hub\TextAssistant\Helpers\TranslationJobHelper;
use S2Hub\TextAssistant\Models\TranslationAction;
use S2Hub\TextAssistant\Services\TextAssistantService;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\Extension\FluentExtension;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use TractorCow\Fluent\Extension\FluentVersionedExtension;
use TractorCow\Fluent\Model\Locale;

class FluentTranslationHandlerJob extends AbstractQueuedJob
{

    /**
     * If the field is chunked, we dont want to write TranslationAction until all chunks are translated.
     * This var will hold the chunks until all are translated and then write TranslationAction.
     */
    private $chunking_object_buffer = [];

    private $translate_after_done = [];

    private $formFieldForType_cache = [];

    public function getTitle()
    {
        return _t(self::class.'.TITLE', 'Translating..');
    }

    public function setup()
    {
        parent::setup();
        TranslationAction::config()->set('allow_logging', false);

        $ids = $this->jobData->records;
        $dataClass = $this->jobData->recordClassName;
        $toLocale = $this->jobData->toLocale;

        
        $remaining = FluentState::singleton()->withState(function (FluentState $newState) use ($ids, $dataClass, $toLocale) {
            $newState->setLocale($toLocale);
            $remaining = [];

            // get all IDs
            $objects = DataList::create($dataClass)->filter([
                'ID' => $ids
            ]);

            foreach ($objects as $object) {
                if ($object->hasExtension(Versioned::class) && !$object->isPublished()) {
                    // If the object is not published, we don't want to translate it.
                    continue;
                }

                $localisedFields = TranslationJobHelper::getAllLocalisedFields(get_class($object));

                if (!$object->hasExtension(FluentExtension::class)) {
                    throw new Exception(get_class($object) . " does not have FluentExtension");
                }

                if (!isset($this->formFieldForType_cache[get_class($object)])) {
                    $formFieldForType = [];
                    foreach ($object->getCMSFields()->dataFields() as $field) {
                        $formFieldForType[$field->getName()] = $field;
                    }

                    $this->formFieldForType_cache[get_class($object)] = $formFieldForType;
                }

                foreach ($localisedFields as $field => $type) {
        
                    // If there's not a dataField for it, skip it.
                    if (!isset($formFieldForType[$field])) {
                        continue;
                    }
        
                    // FluentExtension localises everything. We use ->dataFields() to find TextField/HTMLEditorField
                    // Without this we'll translate fields that may be e.g. SelectionGroup that's a Varchar.
                    if (!($formFieldForType[$field] instanceof TextField ||
                        $formFieldForType[$field] instanceof TextareaField ||
                        $formFieldForType[$field] instanceof HTMLEditorField)) {
        
                        continue;
                    }
        
        
        
                    if ($formFieldForType[$field] instanceof HTMLEditorField) {
        
                        $chunks = DataObjectTranslationHandlerJob::split_html_to_chunks($object->getField($field));
        
                        if (sizeof($chunks) === 1) {
        
                            $remaining[] = [
                                'id' => $object->ID,
                                'field' => $field,
                                'fieldtype' => $type,
                            ];
        
                        } else {
        
                            foreach ($chunks as $i => $chunk) {
                                $remaining[] = [
                                    'id' => $object->ID,
                                    'field' => $field,
                                    'fieldtype' => $type,
                                    'html_chunk' => $i
                                ];
                            }
        
                        }
        
        
        
                    } else {

                        $remaining[] = [
                            'id' => $object->ID,
                            'field' => $field,
                            'fieldtype' => $type,
                        ];
                    }
        
                }
            }

            return $remaining;
        });

        $this->remaining = $remaining;

        $this->totalSteps = count($this->remaining);
    }

    public function process()
    {
        $remaining = $this->remaining;

        // check for trivial case
        if (count($remaining) === 0) {
            $this->isComplete = true;

            return;
        }

        $item = array_shift($remaining);

        $this->translate($item);

        $this->remaining = $remaining;

        $this->currentStep += 1;

        // check for job completion
        if (count($remaining) > 0) {
            return;
        }

        // Queue runner will mark this job as finished
        $this->isComplete = true;
    }

    public function translate($item)
    {
        TranslationAction::config()->set('allow_logging', false);

        $id = $item['id'];
        $dataClass = $this->jobData->recordClassName;
        $toLocale = $this->jobData->toLocale;
        $fromLocale = $this->jobData->fromLocale;
        $fieldName = $item['field'];

        $records = [];
        $allowed_locales = Locale::getCached()->column('Locale');
        foreach ($allowed_locales as $locale) {
            $records[$locale] = FluentState::singleton()->withState(function (FluentState $newState) use ($id, $dataClass, $locale) {
                $newState->setLocale($locale);
                return DataObject::get_by_id($dataClass, $id);
            });

        }

        if (!$records[$fromLocale]) {
            $this->addMessage("Record #".$id." not found", 'INFO');
            return;
        }

        $fieldValue = $records[$fromLocale]->getField($fieldName);

        if (empty($fieldValue)) {
            $this->addMessage("Field $fieldName is empty on #" . $id, 'INFO');
            return;
        }

        // A special case: We want to avoid translation SiteTree with "home" URLSegment
        if ($fieldName === "URLSegment" && strtolower($fieldValue) === "home") {
            $this->addMessage("Field $fieldName is 'home' on #" . $id . ". Skipping this special value.", 'INFO');
            return;
        }

        $isHTML = false;                // if html we want to tell chatgpt to keep html
        $is_chunked = false;            // if chunked we want add to the field rather than overwrite the whole field

        if ($item['fieldtype'] === "HTMLText") {
            $isHTML = true;
        }

        if (isset($item['html_chunk'])) {
            $chunks = DataObjectTranslationHandlerJob::split_html_to_chunks($fieldValue);
            $fieldValue = $chunks[$item['html_chunk']];
            $is_chunked = true;
        }

        $messages = TextAssistantService::getTranslatePrompt($fieldValue, $toLocale, $records[$fromLocale]);

        try {
            $result = TextAssistantService::singleton()->prompt($messages, true);
        } catch (Exception $e) {
            $result = false;

            $this->addMessage("Failed to translate field $fieldName on #" . $item['id'] . ":" . $e, 'WARNING');

            user_error($e, E_USER_WARNING);
        }

        if ($result) {
            $result = TranslationJobHelper::parseResponse($result, $fieldName);

            if (strtoupper(str_replace(['-', ' '], '_', $result)) == "TEXT_ALREADY_TRANSLATED") {
                // We gave instructions to OpenAI to not translate text already in $toLocale language
                $this->addMessage("Field $fieldName on #" . $item['id'] . " is already in $toLocale", 'INFO');
                return;
            }

            $action = new TranslationAction();
            $action->ObjectID = $records[$fromLocale]->ID;
            $action->ObjectClass = get_class($records[$fromLocale]);
            $action->CreatorID = $this->jobData->CreatorID;

            $allowed_locales = Locale::getCached()->column('Locale');
            foreach ($allowed_locales as $locale) {
                $translatedValue = $records[$locale]->getField($fieldName);
                $action->setField("Value_".$locale, $translatedValue);
            }

            $action->setField("Value_".$toLocale, $result);

            $action->FromLocale = $fromLocale;
            $action->Locale = $toLocale;
            $action->FieldName = $fieldName;

            $action->Type = "Generated";
            $action->Status = "Draft";

            if ($is_chunked) {
                
                $uniq = $records[$toLocale]->ID . get_class($records[$toLocale]) . $fieldName . $toLocale;
                if (!isset($this->chunking_object_buffer[$uniq])) {
                    $this->chunking_object_buffer[$uniq] = "";

                    // Since we're now chunk-writing to the obj, we must empty the field here
                    // because we'll be sequentially writing to $field without emptying it.
                    // however only if the record exists in the locale
                    if ($records[$toLocale]->existsInLocale($toLocale)) {
                        $id_was_written = true;

                        $recordInLocale = $records[$toLocale];

                        FluentState::singleton()->withState(function (FluentState $newState) use ($recordInLocale, $toLocale, $fieldName) {
                            $newState->setLocale($toLocale);

                            $recordInLocale->setField($fieldName, "");

                            DataObject::config()->set('validation_enabled', false);

                            if (!empty($recordInLocale->getChangedFields(true, DataObject::CHANGE_VALUE))) {
                                if ($recordInLocale->hasExtension(FluentVersionedExtension::class)) {
                                    $recordInLocale->writeToStage(Versioned::DRAFT);
                                } else {
                                    $recordInLocale->write();
                                }
                                
                            }


                        });
                    }
                }

                // if last chunk, write to db
                if ($item['html_chunk'] === sizeof($chunks) - 1) {

                    // write last chunk
                    $this->chunking_object_buffer[$uniq] .= $result;

                    $action->setField("Value_".$toLocale, $this->chunking_object_buffer[$uniq]);
                    $action->write();

                    TranslationJobHelper::FluentDeleteOldGeneratedTranslations($action, $records[$toLocale], $fieldName, $toLocale);
                    TranslationJobHelper::FluentDeleteOldDraftManualTranslations($action, $records[$toLocale], $fieldName, $toLocale);

                    // If Versioned, write to stage. If no Versioned, we're not changing the DataObject.
                    if ($records[$toLocale]->hasExtension(FluentVersionedExtension::class)) {

                        $bufferValue = $this->chunking_object_buffer[$uniq];
                        $toRecord = $records[$toLocale];

                        FluentState::singleton()->withState(function (FluentState $newState) use ($toRecord, $fieldName, $toLocale, $bufferValue) {
                            $newState->setLocale($toLocale);

                            DataObject::config()->set('validation_enabled', false);

                            Versioned::set_stage(Versioned::DRAFT);
                            $toRecord->setField($fieldName, $bufferValue);
                            $toRecord->write();
                        });

                        unset($bufferValue);
                    }

                    unset($this->chunking_object_buffer[$uniq]);

                } else {

                    // write chunk to buffer
                    $this->chunking_object_buffer[$uniq] .= $result;
                }

            } else {

                $action->write();

                TranslationJobHelper::FluentDeleteOldGeneratedTranslations($action, $records[$toLocale], $fieldName, $toLocale);
                TranslationJobHelper::FluentDeleteOldDraftManualTranslations($action, $records[$toLocale], $fieldName, $toLocale);

                // If Versioned, write to stage. If no Versioned, we're not changing the DataObject.
                if ($records[$toLocale]->hasExtension(FluentVersionedExtension::class)) {

                    FluentState::singleton()->withState(function (FluentState $newState) use ($records, $fieldName, $result, $toLocale) {
                        $newState->setLocale($toLocale);

                        DataObject::config()->set('validation_enabled', false);

                        Versioned::set_stage(Versioned::DRAFT);
                        $records[$toLocale]->setField($fieldName, $result);
                        $records[$toLocale]->write();


                    });
                }
            }

        }

    }

}