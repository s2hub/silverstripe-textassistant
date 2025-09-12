<?php

namespace S2Hub\TextAssistant\Forms\GridField\BulkManager;

use Colymba\BulkManager\BulkAction\Handler;
use Colymba\BulkTools\HTTPBulkToolsResponse;
use S2Hub\TextAssistant\Jobs\DataObjectTranslationHandlerJob;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\DropdownField;
use TractorCow\Fluent\Model\Locale;
use Symbiote\QueuedJobs\Services\QueuedJob;
use SilverStripe\Security\Security;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use SilverStripe\ORM\Queries\SQLUpdate;

class DataObjectTranslationHandler extends Handler
{
    private static $url_segment = 'translateaction';

    private static $allowed_actions = [
        'translateaction',
        'options'
    ];

    private static $url_handlers = [
        '' => 'translateaction',
    ];

    protected $label = 'Translate';

    public function getI18nLabel()
    {
        return _t(self::class.'.TITLE', "Translate with AI");
    }

    public function getConfig()
    {
        $config = parent::getConfig();
        $config['options'] = 'options';
        return $config;
    }

    /**
     * Publish the selected records passed from the publish bulk action.
     *
     * @param HTTPRequest $request
     *
     * @return HTTPBulkToolsResponse
     */
    public function translateaction(HTTPRequest $request)
    {
        $records = $this->getRecords();
        $response = new HTTPBulkToolsResponse(false, $this->gridField);

        $state = $this->gridField->getState()->getData('GridFieldBulkManager');
        $options = null;
        if ($state) {
            $options = $state->getData('Options')->toArray();
        }

        if ($options && empty($options['FromLocale']) || empty($options['ToLocale'])) {
            $response->setMessage(_t(self::class.'.ERROR_MUSTFROMTO', 'Both from and to locale must be selected'));
            return $response;
        }

        $fromLocale = $options['FromLocale'];
        $toLocale = $options['ToLocale'];

        if ($fromLocale === $toLocale) {
            $response->setMessage(_t(self::class.'.ERROR_SAMELOCALE', 'From and to locale cannot be the same'));
            return $response;
        }

        $ids = $records->column('ID');

        $chunks = array_chunk($ids, 50);

        foreach ($chunks as $chunk) {
            $job = new DataObjectTranslationHandlerJob();
            $jobData = new \stdClass();
            $jobData->fromLocale = $fromLocale;
            $jobData->toLocale = $toLocale;
            $jobData->records = $chunk;
            $jobData->recordClassName = $records->dataClass();
            $jobData->CreatorID = Security::getCurrentUser()->ID;
            
            $job->setJobData(0, 0, false, $jobData, [$this->getI18nLabel()]);
            $descriptorID = QueuedJobService::singleton()->queueJob($job, null, null, QueuedJob::IMMEDIATE);

            if ($descriptorID) {
                $job->setup();
                SQLUpdate::create("QueuedJobDescriptor", ['TotalSteps' => sizeof($job->remaining)], ['ID' => $descriptorID])->execute();
            }
        }

        return $response;
    }

    public function options()
    {
        $gridField = $this->gridField;
        $translations_nice = Locale::getCached()->map('Locale', 'Title')->toArray();
        $locales = Locale::getCached()->column('Locale');
        $translations = [];
        foreach ($locales as $locale) {
            if (isset($translations_nice[$locale])) {
                $translations[$locale] = $translations_nice[$locale];
            } else {
                $translations[$locale] = substr($locale, 0, 2);
            }
        }

        $default1 = key($translations);
        next($translations);
        $default2 = key($translations);

        $fields = new FieldList([
            new DropdownField('FromLocale', _t(self::class.'.FROMLOCALE', 'From locale'), $translations, $default1),
            new DropdownField('ToLocale', _t(self::class.'.TOLOCALE', 'To locale'), $translations, $default2),
        ]);
        $form = new Form($gridField, 'bulkAction', $fields, new FieldList());
        return $form;
    }


}