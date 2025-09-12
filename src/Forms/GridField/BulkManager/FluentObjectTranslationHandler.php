<?php


namespace S2Hub\TextAssistant\Forms\GridField\BulkManager;

use Colymba\BulkManager\BulkAction\Handler;
use Colymba\BulkTools\HTTPBulkToolsResponse;
use S2Hub\TextAssistant\Jobs\FluentTranslationHandlerJob;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\Form;
use SilverStripe\Security\Security;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symbiote\QueuedJobs\Services\QueuedJob;
use TractorCow\Fluent\State\FluentState;
use SilverStripe\Forms\GridField\GridState_Data;

class FluentObjectTranslationHandler extends Handler
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
        if (empty($state)) {
            // State was empty, use the request instead to find it 
            // THIS only occurs in NESTED GridFields, where records were ONLY selected in a SINGLE NESTED gridfiel
            // If records were selected in multiple nested gridfield this doesn't occur
            $vars = $request->postVars();
            // this turns e.g. "A[B][C]" into an array A[] => B[] => C[] lol
            $parts = str_replace("]", "", explode("[", $this->gridField->getName()));
            
            foreach ($parts as $part) $vars = $vars[$part];

            $gridState = json_decode($vars['GridState'], true);
            $state = new GridState_Data($gridState);
            $state = $state->getData('GridFieldBulkManager');
        }

        $options = null;
        if ($state) {
            $options = $state->getData('Options')->toArray();
        }

        if ($options && empty($options['FromLocale'])) {
            $response->setMessage(_t(self::class.'.ERROR_MUSTFROMTO', 'Both from and to locale must be selected'));
            return $response;
        }

        $fromLocale = $options['FromLocale'];
        $toLocale = FluentState::singleton()->getLocale();

        $class_grouping = [];

        foreach ($records as $record) {
            $class_grouping[get_class($record)][] = $record->ID;
        }


        foreach ($class_grouping as $class => $ids) {

            $chunks = array_chunk($ids, 50);

            foreach ($chunks as $chunk) {
                $job = new FluentTranslationHandlerJob();
                $jobData = new \stdClass();
                $jobData->fromLocale = $fromLocale;
                $jobData->toLocale = $toLocale;
                $jobData->records = $chunk;
                $jobData->recordClassName = $class;
                $jobData->CreatorID = Security::getCurrentUser()->ID;
                
                $job->setJobData(0, 0, false, $jobData, []);
                $descriptorID = QueuedJobService::singleton()->queueJob($job, null, null, QueuedJob::IMMEDIATE);
            }

        }

        // $request->getSession()->set($this->gridField->getName().'_BulkAction', $descriptorID);
        // $statusField = $this->component->getQueuedJobStatusField($this->gridField);
        // $response->setQueuedJobStatusField($statusField);
        return $response;
    }

    public function options()
    {
        $gridField = $this->gridField;

        
        $localeObjs = \TractorCow\Fluent\Model\Locale::getLocales();
        $locales = [];

        foreach ($localeObjs as $locale) {
            $locales[$locale->Locale] = $locale->Title;
        }

        $currentLocale = FluentState::singleton()->getLocale();
        unset($locales[$currentLocale]); // we cant translate from self to self
         

        $fields = new FieldList([
            new DropdownField('FromLocale', _t(self::class.'.FROMLOCALE', 'From locale'), $locales, array_key_first($locales)),
        ]);
        $form = new Form($gridField, 'bulkAction', $fields, new FieldList());
        return $form;
    }


}