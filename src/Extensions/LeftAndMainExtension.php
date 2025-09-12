<?php

namespace S2Hub\TextAssistant\Extensions;

use S2Hub\TextAssistant\BatchActions\CMSBatchAction_Translate;
use S2Hub\TextAssistant\Forms\GridField\TranslationAdminInstructionsButton;
use S2Hub\TextAssistant\Forms\BatchActions_TranslateForm;
use SilverStripe\Core\Extension;
use SilverStripe\Admin\CMSBatchActionHandler;
use SilverStripe\View\Requirements;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\LiteralField;

class LeftAndMainExtension extends Extension
{

    private static $allowed_actions = [
        'batchactions_translateform',
        'batchactions_translateform_action'
    ];

    public function onAfterInit()
    {
        Requirements::javascript('s2hub/silverstripe-textassistant: client/dist/js/bundle.js');
        Requirements::css('s2hub/silverstripe-textassistant: client/dist/styles/bundle.css');

        CMSBatchActionHandler::register('translate', CMSBatchAction_Translate::class);
    }

    public function batchactions_translateform(HTTPRequest $request = null)
    {
        $form = new BatchActions_TranslateForm($this->owner);
        return $form->getForm($request);
    }

    public function batchactions_translateform_action(HTTPRequest $request = null)
    {
        $form = new BatchActions_TranslateForm($this->owner);
        return $form->StartTranslation($request);
    }
    
    public function updateBatchActionsForm($form)
    {
        $fields = $form->Fields();

        $fields->push(new LiteralField('tutorial-translate-strings', '<div id="tutorial-translate-strings" data-translations=\''.TranslationAdminInstructionsButton::getSecondPageTranslations().'\'></div>'));
    }
}
