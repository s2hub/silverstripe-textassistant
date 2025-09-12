<?php

namespace S2Hub\TextAssistant\Jobs;

use Exception;
use S2Hub\TextAssistant\Models\TranslationAction;
use S2Hub\TextAssistant\Services\TextAssistantService;
use S2Hub\TextAssistant\Helpers\TranslationJobHelper;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBHTMLText;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use TractorCow\Fluent\Model\Locale;

class DataObjectTranslationHandlerJob extends AbstractQueuedJob
{

    /**
     * If the field is chunked, we dont want to write TranslationAction until all chunks are translated.
     * This var will hold the chunks until all are translated and then write TranslationAction.
     */
    private $chunking_object_buffer = [];

    private $translate_after_done = [];
    
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
        $obj = Injector::inst()->get($dataClass);
        $toLocale = $this->jobData->toLocale;

        $translatableFields = $obj->getTranslatableFields();

        $ignore_translate = $obj->config()->openai_ignore_translate ?? [];

        if (!empty($ignore_translate)) {
            $ignore_translate = array_flip($ignore_translate);
        }

        $remaining = [];

        foreach ($ids as $id) {

            foreach ($translatableFields as $field) {

                if (isset($ignore_translate[$field])) {
                    continue;
                }

                $fieldObj = $obj->dbObject($field);
                $record = DataObject::get_by_id($dataClass, $id);

                if ($record->hasExtension(Versioned::class) && !$record->isPublished()) {
                    // If the object is not published, we don't want to translate it.
                    continue;
                }

                if ($fieldObj instanceof DBHTMLText) {

                    $chunks = self::split_html_to_chunks($record->getField($field));

                    if (sizeof($chunks) === 1) {
                        $remaining[] = [
                            'id' => $id,
                            'field' => $field
                        ];
                    } else {
                        foreach ($chunks as $i => $chunk) {
                            $remaining[] = [
                                'id' => $id,
                                'field' => $field,
                                'html_chunk' => $i
                            ];
                        }
                    }

                } else {
                    $remaining[] = [
                        'id' => $id,
                        'field' => $field
                    ];
                }

            }
        }

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
        // all logging is manually created by us, not in the ::log() function.
        TranslationAction::config()->set('allow_logging', false);

        $record = DataObject::get_by_id($this->jobData->recordClassName, $item['id']);

        if (!$record) {
            $this->addMessage("Record #".$item['id']." not found", 'INFO');
            return;
        }

        $fromLocale = $this->jobData->fromLocale;
        $toLocale = $this->jobData->toLocale;
        $field = $item['field'];

        $fieldName = $record->getLocaleFieldName($field, $fromLocale);
        $fieldValue = $record->getField($fieldName);

        if (empty($fieldValue)) {
            // commented out, not really neccessary to log this
            // $this->addMessage("Field $fieldName is empty on #" . $item['id'], 'INFO');
            return;
        }

        $isHTML = false;                // if html we want to tell chatgpt to keep html
        $is_chunked = false;            // if chunked we want add to the field rather than overwrite the whole field

        if ($record->hasField($field) && $record->dbObject($field) instanceof DBHTMLText) {
            $isHTML = true;
        }

        if (isset($item['html_chunk'])) {
            $chunks = self::split_html_to_chunks($fieldValue);
            $fieldValue = $chunks[$item['html_chunk']];
            $is_chunked = true;
        }

        $messages = TextAssistantService::getTranslatePrompt($fieldValue, $toLocale, $record);

        try {
            $result = TextAssistantService::singleton()->prompt($messages);
        } catch (Exception $e) {
            $result = false;

            $this->addMessage("Failed to translate field $fieldName on #" . $item['id'] . ":" . $e, 'WARNING');

            user_error($e, E_USER_WARNING);
        }

        if ($result) {

            $result = TranslationJobHelper::parseResponse($result, $field);

            if (strtoupper(str_replace(['-', ' '], '_', $result)) == "TEXT_ALREADY_TRANSLATED") {
                // We gave instructions to OpenAI to not translate text already in $toLocale language
                $this->addMessage("Field $fieldName on #" . $item['id'] . " is already in $toLocale", 'INFO');
                return;
            }

            $action = new TranslationAction();

            $action->ObjectID = $record->ID;
            $action->ObjectClass = get_class($record);
            $action->CreatorID = $this->jobData->CreatorID;

            $allowed_locales = Locale::getCached()->column('Locale');
            foreach ($allowed_locales as $locale) {
                $translatedField = $record->getLocaleFieldName($field, $locale);
                $action->setField("Value_".$locale, $record->getField($translatedField));
            }

            $translatedField = $record->getLocaleFieldName($field, $locale);
            $action->setField("Value_".$toLocale, $result);

            $action->FromLocale = $fromLocale;
            $action->Locale = $toLocale;
            $action->FieldName = $field;

            $action->Type = "Generated";
            $action->Status = "Draft";

            if ($is_chunked) {

                $uniq = $record->ID . get_class($record) . $field . $toLocale;
                if (!isset($this->chunking_object_buffer[$uniq])) {
                    $this->chunking_object_buffer[$uniq] = "";

                    // Since we're now chunk-writing to the obj, we must empty the field here
                    // because we'll be sequentially writing to $field without emptying it.
                    $fieldName = $record->getLocaleFieldName($field, $toLocale);
                    $record->setField($fieldName, "");

                    DataObject::config()->set('validation_enabled', false);
                    if (!empty($record->getChangedFields(true, DataObject::CHANGE_VALUE))) {
                        // If has versioning, write to draft, otherwise just write it completely.
                        if ($record->hasExtension(Versioned::class)) {
                            $record->writeToStage(Versioned::DRAFT);
                        } else {
                            $record->write();
                        }
                    }

                }

                // if last chunk, write to db
                if ($item['html_chunk'] === sizeof($chunks) - 1) {

                    // write last chunk
                    $this->chunking_object_buffer[$uniq] .= $result;

                    $translatedField = $record->getLocaleFieldName($field, $toLocale);

                    $action->setField("Value_".$toLocale, $this->chunking_object_buffer[$uniq]);
                    $action->write();

                    TranslationJobHelper::TranslatableDeleteOldGeneratedTranslations($action, $record, $translatedField, $toLocale);
                    TranslationJobHelper::TranslatableDeleteOldDraftManualTranslations($action, $record, $translatedField, $toLocale);

                    // If Versioned, write to stage. If no Versioned, we're not changing the DataObject.
                    if ($record->hasExtension(Versioned::class)) {
                        DataObject::config()->set('validation_enabled', false);
                        $record->setField($translatedField, $this->chunking_object_buffer[$uniq]);
                        $record->writeToStage(Versioned::DRAFT);
                    }

                    unset($this->chunking_object_buffer[$uniq]);

                } else {

                    // write chunk to buffer
                    $this->chunking_object_buffer[$uniq] .= $result;
                }


            } else {
                $action->write();

                TranslationJobHelper::TranslatableDeleteOldGeneratedTranslations($action, $record, $translatedField, $toLocale);
                TranslationJobHelper::TranslatableDeleteOldDraftManualTranslations($action, $record, $translatedField, $toLocale);

                // If Versioned, write to stage. If no Versioned, we're not changing the DataObject.
                if ($record->hasExtension(Versioned::class)) {
                    $translatedField = $record->getLocaleFieldName($field, $toLocale);

                    $initial_editable_urlsegment = null;

                    if ($field === "Title" && $record->config()->editable_urlsegment === true) {
                        $initial_editable_urlsegment = $record->config()->editable_urlsegment;
                        $record->config()->editable_urlsegment = false;

                    }

                    DataObject::config()->set('validation_enabled', false);
                    $record->setField($translatedField, $result);
                    $record->writeToStage(Versioned::DRAFT);

                    if (!is_null($initial_editable_urlsegment)) {
                        $record->config()->editable_urlsegment = $initial_editable_urlsegment;
                    }
                }
            }

        }

    }

    public static function split_html_to_chunks($html): array
    {
        $nominal_max_length = 4000;

        // do nothing if the html is already short enough
        if (strlen($html) < $nominal_max_length) {
            return [$html];
        }

        preg_match_all('/<[^>]*>(.*)+/', $html, $output_array);

        $parts = $output_array[0];


        $current_length = 0;

        $parts_by_nominal = [];
        $current_index = 0;

        foreach ($parts as $part) {

            if (!isset($parts_by_nominal[$current_index])) {
                $parts_by_nominal[$current_index] = "";
            }

            $part_length = strlen($part);
            
            $parts_by_nominal[$current_index] .= $part . "\n";

            
            if ($part_length + $current_length > $nominal_max_length) {
                $current_length = 0;
                $current_index++;
                
            } else {
                $current_length += $part_length;
            }
            
        }

        return $parts_by_nominal;
    }

}