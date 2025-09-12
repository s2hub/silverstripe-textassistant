<?php

namespace S2Hub\TextAssistant\Extensions;

use GuzzleHttp\Exception\ClientException;
use S2Hub\TextAssistant\Helpers\TranslationJobHelper;
use S2Hub\TextAssistant\Services\TextAssistantService;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\SelectionGroup;
use SilverStripe\Forms\SelectionGroup_Item;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Parsers\HTMLValue;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class FormFieldExtension extends Extension
{
    use Configurable;

    private static $allowed_actions = [
        'TextAssistantForm'
    ];

    private $textAssistantConfigs = [];
    private $disabledTextAssistants = [];

    public static $field_was_translated_via_ai = [];

    public function disableTextAssistant()
    {
        $id = spl_object_id($this->owner);
        $this->disabledTextAssistants[$id] = true;
        return $this->owner;
    }

    protected static $fluentLocalisedFieldsCache = [];

    protected function initTextAssistantConfig()
    {
        $id = spl_object_id($this->owner);
        if (!isset($this->textAssistantConfigs[$id])) {
            $this->textAssistantConfigs[$id] = [];
        }

        // This code is for adding text assistant to Fluent fields
        if (
               $this->owner->getForm()
            && $this->owner->getForm()->getRecord()
            && $this->owner->getForm()->getRecord()->hasExtension(FluentExtension::class)
        ) {
            if (!isset(self::$fluentLocalisedFieldsCache[$this->owner->getForm()->getRecord()->ClassName])) {
                self::$fluentLocalisedFieldsCache[$this->owner->getForm()->getRecord()->ClassName] = TranslationJobHelper::getAllLocalisedFields($this->owner->getForm()->getRecord()->ClassName);
            }

            if (
                isset(self::$fluentLocalisedFieldsCache[$this->owner->getForm()->getRecord()->ClassName][$this->owner->getName()])
                && !($this->owner instanceof CompositeField)
                && !($this->owner instanceof UploadField)
                && !($this->owner instanceof FormAction)
                && !($this->owner->getName() == 'URLSegment' || substr($this->owner->getName(), 0, -6) == 'URLSegment')
                && !isset($this->textAssistantConfigs[$id]['FluentTranslateFrom'])
            ) {
                $localeObjs = Locale::getLocales();
                $locales = [];
        
                foreach ($localeObjs as $locale) {
                    $locales[$locale->Locale] = $locale->Title;
                }
        
                unset($locales[FluentState::singleton()->getLocale()]); // we cant translate from self to self


                if (!empty($locales)) {
                    $currentLocaleName = FluentState::singleton()->getLocale();

                    $this->textAssistantConfigs[$id]['TranslateFrom'] = [
                        'id' => 'TranslateFrom',
                        'title' => _t(self::class.'.TRANSLATEFROM', 'Translate from'),
                        'group' => 'Translate',
                        'options' => [
                            OptionsetField::create(
                                'TranslateFrom',
                                '',
                                $locales,
                                array_keys($locales)[0]
                            )
                        ],
                        'message' => TextAssistantService::getTranslatePrompt('{text}', '{toLocale}', false, $this->owner->getForm()->getRecord()),
                        'data' => [
                            'fromLocale' => [
                                'option' => 'TranslateFrom',
                                'type' => 'locale'
                            ],
                            'toLocale' => $currentLocaleName,
                            'text' => [
                                'callback' => function($item, $owner, $dataField, $data) {
                                    return FluentState::singleton()->withState(function (FluentState $newState) use ($item, $owner, $data) {
                                        if (isset($data['TranslateFrom'])) {
                                            $newState->setLocale($data['TranslateFrom']);
                                        }
                                        return DataObject::get_by_id(get_class($item), $item->ID)->{$owner->getName()};
                                    });
                                }
                            ]
                        ]
                    ];
                }
            }
        }

        return $this->textAssistantConfigs[$id];
    }

    public function getTextAssistantEnabled()
    {
        $id = spl_object_id($this->owner);
        if (isset($this->disabledTextAssistants[$id]) && $this->disabledTextAssistants[$id]) {
            return false;
        }
        return $this->getTextAssistantConfig();
    }

    public function getTextAssistantConfig()
    {
        return $this->initTextAssistantConfig();
    }

    public function addTextAssistantConfig($config)
    {
        $this->initTextAssistantConfig();
        if (!isset($config['id'])) {
            $config['id'] = $config['title'];
        }
        $id = spl_object_id($this->owner);
        $this->textAssistantConfigs[$id][$config['id']] = $config;
        return $this->owner;
    }

    public function onBeforeRender()
    {
        if ($this->owner->getTextAssistantEnabled()) {
            $this->owner->addExtraClass('text-assistant');

            $wasTranslatedWithAI = false;

            if (isset(self::$field_was_translated_via_ai[$this->owner->getName()]) && self::$field_was_translated_via_ai[$this->owner->getName()] === true) {
                $wasTranslatedWithAI = true;
            }

            $title = $this->owner->Title();
            if ($this->owner->getForm()) {
                $this->owner->setRightTitle($this->owner->renderWith('S2Hub/TextAssistant/Forms/TextAssistantButton', [
                    'wasTranslatedWithAI' => $wasTranslatedWithAI,
                    'FieldName' => $this->owner->getName(),
                    'FieldTitle' => $title
                ]));
            }
        }
    }

    public function Breadcrumbs()
    {
        if ($this->getTextAssistantEnabled()) {
            $items = new ArrayList([
                new ArrayData(array(
                    'Title' => 'Text assistant',
                    'Link' => false
                ))
            ]);
            return $items;
        }
    }

    protected function getTextAssistantMessage($configID = null, $data = [])
    {
        $configs = $this->owner->getTextAssistantConfig();
        $config = reset($configs);
        if ($configID) {
            $config = $configs[$configID];
        }
        $messages = [];
        $configMessages = $config['message'];
        if (!is_array($configMessages)) {
            $configMessages = [$configMessages];
        }
        foreach ($configMessages as $message) {
            if (isset($config['data'])) {
                $this->replaceData($message, $config, $data);
            }
            $messages[] = $message;
        }

        return $messages;
    }

    protected function getTextAssistantTitle($configID = null, $data = [])
    {
        $configs = $this->owner->getTextAssistantConfig();
        $config = reset($configs);
        if ($configID) {
            $config = $configs[$configID];
        }
        $title = $config['title'];
        if (isset($config['data'])) {
            $this->replaceData($title, $config, $data);
        }
        return $title;
    }

    protected function replaceData(&$value, $config, $data = [])
    {
        $isFluent = $this->owner->getForm()->getRecord()->hasExtension(FluentExtension::class);

        foreach ($config['data'] as $dataField => $dataValue) {
            $replacement = $dataValue;
            if (is_array($dataValue)) {
                if (isset($dataValue['attribute'])) {
                    $replacement = $this->owner->getAttribute($dataValue['attribute']);
                } elseif (isset($dataValue['option'])) {
                    if (isset($data[$dataValue['option']])) {
                        $replacement = $data[$dataValue['option']];
                    } else {
                        $replacement = '';
                    }
                } elseif (isset($dataValue['field'])) {
                    $field = $dataValue['field'];

                    if (isset($dataValue['locale'])) {
                        $locale = $dataValue['locale'];
                        if (!i18n::getData()->validate($locale) && isset($config['data'][$locale])) {
                            if (isset($config['data'][$locale]['option']) && isset($data[$config['data'][$locale]['option']])) {
                                $locale = $data[$config['data'][$locale]['option']];
                            } else {
                                $locale = $config['data'][$locale];
                            }
                        }
                        if (is_string($locale) && i18n::getData()->validate($locale)) {
                            if (!$isFluent) {
                                $field = $this->owner->getForm()->getRecord()->getLocaleFieldName($field, $locale);
                            }
                        }
                    } elseif ($this->owner->getForm()->getRecord()->isTranslatableField($field) && $locale = $this->owner->getAttribute('data-locale')) {
                        $field = $this->owner->getForm()->getRecord()->getLocaleFieldName($field, $locale);
                    }
                    
                    $replacement = $this->owner->getForm()->getRecord()->$field;

                } elseif (isset($dataValue['callback']) && is_callable($dataValue['callback'])) {
                    $callback = $dataValue['callback'];
                    $replacement = $callback($this->owner->getForm()->getRecord(), $this->owner, $dataField, $data);
                }
                if (isset($dataValue['type'])) {
                    if ($dataValue['type'] == 'locale') {
                        // this'd do e.g. en_US -> englanti, however chatGPT very much prefers the code
                        // $replacement = i18n::getData()->languageName($replacement);
                    }
                }
            }
            if ($replacement !== null) {
                $value = str_replace('{'.$dataField.'}', $replacement, $value);
            }
        }
    }

    public function TextAssistantForm()
    {
        $configs = $this->owner->getTextAssistantConfig();
        $fields = new FieldList();
        if ($fieldValues = $this->owner->getRequest()->requestVar('FieldValues')) {
            // store unsaved values in record
            $data = json_decode($fieldValues, true);
            $record = $this->owner->getForm()->getRecord();
            foreach ($data as $field => $value) {
                $record->$field = $value;
            }
            $fields->push(new HiddenField('FieldValues', '', $fieldValues));
        }
        if (count($configs) >= 1) {
            $items = [];
            $groups = [];
            foreach ($configs as $config) {
                if (isset($config['group'])) {
                    $groups[$config['group']] = $config['group'];
                } else {
                    $groups[$config['id']] = $config['id'];
                }
            }
            foreach ($configs as $config) {
                $field = null;
                if (isset($config['options'])) {
                    if (count($config['options']) > 0) {
                        $field = new CompositeField($config['options']);
                    } else {
                        $field = $config['options'][0];
                    }
                }
                $item = new SelectionGroup_Item(
                    $config['id'],
                    $field,
                    $this->getTextAssistantTitle($config['id'])
                );
                if (isset($config['group']) && count($groups) > 1) {
                    $group = $config['group'];
                    if (!isset($items[$group])) {
                        $items[$group] = new SelectionGroup_Item(
                            $group,
                            new SelectionGroup($group, []),
                            _t(self::class.'.'.strtoupper($group), $group)
                        );
                    }
                    $parent = $items[$group];
                    $selectionGroup = $parent->FieldList()->first();
                    $selectionGroup->getChildren()->push($item);
                } else {
                    $items[$config['id']] = $item;
                }
            }
            $first = reset($items);
            $fields->push(new SelectionGroup('ConfigID', $items, $first->getValue()));
        } else {
            $config = reset($configs);
            $fields->push(new HeaderField('DefaultHeader', $this->getTextAssistantTitle($config['id'])));
        }
        
        $actions = new FieldList(
            FormAction::create('GenerateText', _t(self::class.'.GENERATE', 'Generate'))->setUseButtonTag(true)->addExtraClass('btn btn-primary'),
            FormAction::create('close', _t(self::class.'.CLOSE', 'Close'))->setUseButtonTag(true)->addExtraClass('btn btn-secondary')
        );

        $form = new Form($this->owner, __FUNCTION__, $fields, $actions);
        $form->setTemplate([
            'type' => 'Includes',
            'SilverStripe\\Admin\\LeftAndMain_EditForm',
        ]);
        $form->addExtraClass('cms-content cms-edit-form center fill-height flexbox-area-grow');
        $form->setAttribute('data-pjax-fragment', 'CurrentForm');
        if ($form->Fields()->hasTabSet()) {
            $form->Fields()->findOrMakeTab('Root')->setTemplate('SilverStripe\\Forms\\CMSTabSet');
            $form->addExtraClass('cms-tabset');
        
        }
        if ($this->owner->getRequest()->isGET()) {
            return $form->forTemplate();
        }
        return $form;
    }

    public function GenerateText($data, Form $form, $returnForm = false)
    {
        $configs = $this->owner->getTextAssistantConfig();
        $configID = null;
        if (isset($data['ConfigID'])) {
            $configID = $data['ConfigID'];
            if (!isset($configs[$configID]) && isset($data[$configID])) {
                // group
                $configID = $data[$configID];
            }
        }
        if ($configID == 'TranslateTo') {
            // multiple runs for translate to
            $form->Fields()->push($field = CompositeField::create()->setName('Content'));
            foreach ($data['TranslateTo'] as $locale) {
                $message = $this->getTextAssistantMessage($configID, array_merge($data, ['TranslateTo' => $locale]));
                try {
                    $result = TextAssistantService::singleton()->prompt($message);
                    $field->FieldList()->push(ReadonlyField::create(
                        substr($this->owner->getName(), 0, -6).'_'.$locale,
                        _t(self::class.'.CONTENT', 'Content').' ('.substr($locale, 0, 2).')',
                        HTMLValue::create(trim($result, ' "'))
                    )->setTemplate('S2Hub/TextAssistant/Forms/ReadonlyField'));
                } catch (ClientException $e) {
                    $message = '';
                    $body = (string)$e->getResponse()->getBody();
                    if ($body) {
                        $message = $body;
                        $responseData = json_decode($body, true);
                        if ($responseData && isset($responseData['error']['message'])) {
                            $message = $responseData['error']['message'];
                        }
                    }
                    $form->Fields()->push(ReadonlyField::create('Error', _t(self::class.'.ERROR', 'Error'), $message));
                    break;
                }
            }
        } else {
            $message = $this->getTextAssistantMessage($configID, $data);
            try {
                $result = TextAssistantService::singleton()->prompt($message);

                $form->Fields()->push(ReadonlyField::create(
                    'Content',
                    _t(self::class.'.CONTENT', 'Content'),
                    HTMLValue::create(trim($result, ' "'))
                )->setTemplate('S2Hub/TextAssistant/Forms/ReadonlyField'));
            } catch (ClientException $e) {
                $form->Fields()->push(ReadonlyField::create('Error', _t(self::class.'.ERROR', 'Error'), $e->getMessage()));
            }
        }
        if (!$form->Fields()->fieldByName('Error')) {
            $form->Actions()->unshift(FormAction::create('accept', _t(self::class.'.OK', 'Ok'))->setUseButtonTag(true)->addExtraClass('btn'));
        }

        if ($returnForm === true) {
            return $form;
        }

        return $form->forTemplate();
    }

}
