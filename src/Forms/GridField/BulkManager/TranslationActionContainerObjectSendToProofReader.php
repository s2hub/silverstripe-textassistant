<?php

namespace S2Hub\TextAssistant\Forms\GridField\BulkManager;

use Colymba\BulkManager\BulkAction\Handler;
use Colymba\BulkTools\HTTPBulkToolsResponse;
use SilverStripe\Control\HTTPRequest;


class TranslationActionContainerObjectSendToProofReader extends Handler
{

    private static $url_segment = 'send_to_proofreader';

    private static $allowed_actions = [
        'send_to_proofreader',
    ];

    private static $url_handlers = [
        '' => 'send_to_proofreader',
    ];

    protected $label = 'send_to_proofreader';

    public function getI18nLabel()
    {
        return _t(self::class.'.TITLE', "Send to proofreader");
    }

    public function send_to_proofreader(HTTPRequest $request)
    {
        $response = new HTTPBulkToolsResponse(false, $this->gridField);
        $records = $this->getRecords();

        $message = _t(self::class.'.MESSAGE', 'Sent {Count} items to proofreader.', [
            'Count' => $records->count()
        ]);

        $response->setMessage($message);


        return $response;
    }

}