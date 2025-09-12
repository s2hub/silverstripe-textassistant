<?php

namespace S2Hub\TextAssistant\Forms;

use SilverStripe\View\Requirements;
use SilverStripe\i18n\i18n;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\View\Parsers\HTMLValue;
use TractorCow\Fluent\Model\Locale;

class TranslatableDataObjectField extends FieldGroup
{
    protected $schemaComponent = 'TranslatableDataObjectField';
    protected $defaultLocale = null;
    protected $hasData = false;
    protected $relation = null;

    public function __construct($titleOrField = null, $otherFields = null)
    {
        $title = null;
        if ($titleOrField instanceof HTMLValue) {
            $title = $titleOrField;
            $fields = $otherFields;
        } elseif (is_array($titleOrField) || $titleOrField instanceof FieldList) {
            $fields = $titleOrField;
            
            // This would be discarded otherwise
            if ($otherFields) {
                throw new \InvalidArgumentException(
                    '$otherFields is not accepted if passing in field list to $titleOrField'
                );
            }
        } elseif (is_array($otherFields) || $otherFields instanceof FieldList) {
            $title = $titleOrField;
            $fields = $otherFields;
        } else {
            $fields = func_get_args();
            if (!is_object(reset($fields))) {
                $title = array_shift($fields);
            }
        }
        
        CompositeField::__construct($fields);
        
        if ($title) {
            $this->setTitle($title);
        }
    }

    public function setHasData($value)
    {
        $this->hasData = $value;
        return $this;
    }

    public function hasData()
    {
        return $this->hasData;
    }

    public function canSubmitValue()
    {
        return false;
    }

    public function setName($name)
    {
        if (!$this->relation) {
            $this->relation = $name;
        }
        parent::setName($name);
        if ($this->getChildren()->exists() && $this->relation && $this->relation != $name) {
            foreach ($this->getChildren() as $field) {
                $locale = $field->getAttribute('data-locale');
                $field->setName(str_replace($this->relation, $this->relation.'_'.$locale, $name));
            }
        }
        return $this;
    }
    
    public function FieldHolder($properties = array())
    {
        return parent::FieldHolder($properties);
    }
    
    public function getDefaultLocale()
    {
        $defaultLocale = $this->defaultLocale;
        if (!$defaultLocale) {
            $locale = Locale::getDefault();
            if ($locale) {
                $defaultLocale = $locale->Locale;
            }
            $defaultLocale = i18n::get_locale();
        }
        return $defaultLocale;
    }
    
    public function setDefaultLocale($locale)
    {
        if (i18n::getData()->validate($locale)) {
            $this->defaultLocale = $locale;
        }
    }
    
    public function getDefaultField()
    {
        foreach ($this->children as $child) {
            if ($child->getAttribute('data-locale') == $this->getDefaultLocale()) {
                return $child;
            }
        }
        return $this->children->first();
    }
    
    public function HasHtmlEditor()
    {
        return ($this->getDefaultField() instanceof HTMLEditorField);
    }
    
    public function push(FormField $field, $locale = null)
    {
        if ($locale == $this->defaultLocale) {
            $this->addExtraClass($field->Type(true));
        }
        $field->setAttribute('data-locale', $locale);
        $language = i18n::getData()->langFromLocale($locale);
        $tabTitle = $language;
        $otherFieldsFound = false;
        foreach ($this->children as $otherField) {
            if (!($otherField instanceof UploadField) && $otherField->getAttribute('data-tab-title') == $tabTitle) {
                $country = i18n::getData()->countryFromLocale($otherField->getAttribute('data-locale'));
                $otherField->setAttribute('data-tab-title', $tabTitle." ($country)");
                $otherFieldsFound = true;
            }
        }
        if ($otherFieldsFound) {
            $country = i18n::getData()->countryFromLocale($locale);
            $tabTitle .= " ($country)";
        }
        $field->setAttribute('data-tab-title', $tabTitle);
        $field->setSchemaData([
            'data' => [
                'locale' => $locale,
                'tabTitle' => $tabTitle
            ]
        ]);
        $field->addExtraClass($locale);
        parent::push($field);
    }
    
    public function HasTranslations()
    {
        return $this->FieldList()->count() > 1;
    }
    
    public function getName()
    {
        if (!strlen($this->name)) {
            return parent::getName();
        }
        return $this->name;
    }

    public function getSchemaDataDefaults()
    {
        $defaults = parent::getSchemaDataDefaults();
        $defaults['data']['defaultLocale'] = $this->owner->getDefaultLocale();
        $defaults['data']['defaultChild'] = $this->owner->getDefaultField()->getSchemaData();
        return $defaults;
    }
}
