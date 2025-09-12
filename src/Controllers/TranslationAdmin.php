<?php

namespace S2Hub\TextAssistant\Controllers;

use Colymba\BulkManager\BulkManager;
use S2Hub\TextAssistant\Forms\GridField\BulkManager\TranslationActionContainerObjectPublishHandler;
use S2Hub\TextAssistant\Forms\GridField\BulkManager\TranslationActionContainerObjectSendToProofReader;
use S2Hub\TextAssistant\Forms\GridField\TranslationActionContainerObjectItemRequest;
use S2Hub\TextAssistant\Models\TranslationAction;
use S2Hub\TextAssistant\Models\TranslateFilter;
use S2Hub\TextAssistant\Models\TextAssistantSettings;
use S2Hub\TextAssistant\ORM\TranslationActionDataList;
use S2Hub\TextAssistant\Forms\GridField\TranslationAdminInstructionsButton;
use S2Hub\TextAssistant\Forms\TranslationAdminInformationField;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldDataColumns;

class TranslationAdmin extends ModelAdmin
{
    private static $url_segment = 'translations';

    private static $menu_title = 'Translations';

    private static $menu_priority = 1;

    private static $menu_icon_class = 'font-icon-globe';

    private static $managed_models = [
        TranslationAction::class,
        TranslateFilter::class,
        TextAssistantSettings::class
    ];

    public function getList()
    {
        $list = parent::getList();

        if ($this->modelClass == TranslationAction::class) {

            $list = TranslationActionDataList::create()
                ->filter('Status', 'Draft')
                ->sort('Created', 'DESC');
        }

        return $list;
    }

    public function getEditForm($id = null, $fields = null)
    {
        if ($this->modelTab == TextAssistantSettings::class) {
            $record = TextAssistantSettings::currentRecord();
            $form = Form::create(
                $this,
                'EditForm',
                $record->getCMSFields(),
                new FieldList([
                    FormAction::create('SaveTextAssistantSettings', _t('SilverStripe\\Admin\\ModelAdmin.SAVE', 'Save'))
                        ->setUseButtonTag(true)
                        ->addExtraClass('btn btn-primary font-icon-save')
                ])
            )->setHTMLID('Form_EditForm');
            $form->addExtraClass('cms-edit-form cms-panel-padded center flexbox-area-grow');
            $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
            $editFormAction = Controller::join_links($this->getLinkForModelTab($this->modelTab), 'EditForm');
            $form->setFormAction($editFormAction);
            $form->setAttribute('data-pjax-fragment', 'CurrentForm');
            $form->loadDataFrom($record);

            // Check if the the record  requires sudo mode, If so then require sudo mode for the edit form
            if ($record->getRequireSudoMode()) {
                $form->requireSudoMode();
            }

            $this->extend('updateEditForm', $form);

            return $form;
        }
        $form = parent::getEditForm($id, $fields);

        if ($this->modelClass == TranslationAction::class) {
            $form->addExtraClass('TranslationAdmin');

            $gridField = $form->Fields()->fieldByName($this->sanitiseClassName($this->modelClass));
            $config = $gridField->getConfig();

            $form->Fields()->insertBefore(TranslationAdminInformationField::create($gridField->getList())->setForm($form), $gridField);


            $columns = $config->getComponentByType(GridFieldDataColumns::class);

            $config->getComponentByType(GridFieldDetailForm::class)
                ->setItemRequestClass(TranslationActionContainerObjectItemRequest::class);


            $columns->setDisplayFields([
                'ObjectType' => _t(TranslationAction::class.'.TYPE', 'Type'),
                'ObjectDescription' => _t(TranslationAction::class.'.NAME', 'Name'),
                'ActionFromToLocale' => _t(TranslationAction::class.'.LOCALE', 'Locale'),
                'CreatedNice' => _t(TranslationAction::class.'.SINGULARNAME_ADJECTIVE', 'Translated')
            ]);

            $bulkManager = new BulkManager(false, false, false);

            $bulkManager->addBulkAction(TranslationActionContainerObjectPublishHandler::class);
            $bulkManager->addBulkAction(TranslationActionContainerObjectSendToProofReader::class);

            $config->addComponent($bulkManager);
            
            $config->addComponent(new TranslationAdminInstructionsButton());
        }

        return $form;
    }

    public function SaveTextAssistantSettings($data, Form $form)
    {
        $record = TextAssistantSettings::currentRecord();
        $form->saveInto($record);
        $record->write();

        $this->getResponse()->addHeader('X-Status', rawurlencode(_t('SilverStripe\\Admin\\LeftAndMain.SAVEDUP', 'Saved.')));

        return $this->redirectBack();
    }
}