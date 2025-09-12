<?php

namespace S2Hub\TextAssistant\Forms;

use S2Hub\TextAssistant\Jobs\DataObjectTranslationHandlerJob;
use S2Hub\TextAssistant\Jobs\FluentTranslationHandlerJob;
use S2Hub\TextAssistant\Jobs\ObjectTranslationJob;
use S2Hub\TextAssistant\Jobs\QueuePageTranslationsJob;
use SilverStripe\ORM\SS_List;
use SilverStripe\ORM\DataList;
use SilverStripe\View\ArrayData;
use SilverStripe\Forms\FormField;
use SilverStripe\Control\HTTPResponse;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

class TranslationAdminInformationField extends FormField
{
    private $list;

    private static $allowed_actions = [
        'update'
    ];

    public function __construct(SS_List $gridFieldList)
    {
        $this->list = $gridFieldList;
        return parent::__construct("TranslationAdminInformationField");
    }

    private $_cached_data = null;
    public function getTranslationJobsData()
    {
        if (!is_null($this->_cached_data)) return $this->_cached_data;

        $jobs = DataList::create(QueuedJobDescriptor::class)->filter([
            'JobStatus' => ['Running', 'New', 'Complete'],
            'Created:GreaterThan' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'Implementation' => [FluentTranslationHandlerJob::class, DataObjectTranslationHandlerJob::class, ObjectTranslationJob::class]
        ]);

        $initJobs = DataList::create(QueuedJobDescriptor::class)->filter([
            'Implementation' => [QueuePageTranslationsJob::class],
            'JobStatus' => ['Running', 'New'],
        ]);


        $initJobsExists = $initJobs->count() !== 0;


        $totalSteps = 0;
        $stepsProcessed = 0;
        $isRunning = false;
        $anyJobsExisted = false;


        foreach ($jobs as $job) {
            $totalSteps += $job->TotalSteps;
            $stepsProcessed += $job->StepsProcessed;

            if ($job->JobStatus == 'Running' || $job->JobStatus == 'New') {
                $isRunning = true;

            }

            $anyJobsExisted = true;
        }

        $translatedPercentage = 100;
        if ($stepsProcessed > 0 && $totalSteps > 0) {
            $translatedPercentage = round(($stepsProcessed / $totalSteps) * 100, 1);

        }

        // $timeLeft = gmdate("H:i:s", (($totalSteps - $stepsProcessed) * $average));

        $data = new ArrayData([
            'Display' => $anyJobsExisted || $initJobsExists,
            'TotalSteps' => $totalSteps,
            'StepsProcessed' => $stepsProcessed,
            'IsRunning' => $isRunning,
            'TranslatedPercentage' => $translatedPercentage,
            'TranslationActions' => $this->list->count(),
            'InitializationJobsExists' => $initJobsExists,
            // 'TimeLeft' => $timeLeft
        ]);

        $this->_cached_data = $data;
        return $data;
    }

    public function update()
    {
        $data = $this->getTranslationJobsData();

        $response = new HTTPResponse(json_encode([
            'IsRunning' => $data->IsRunning,
            'Template' => $this->FieldHolder()->forTemplate()
        ]));
        $response->addHeader('Content-Type', 'application/json');

        return $response;
    }

}