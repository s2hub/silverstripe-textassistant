<?php

namespace S2Hub\TextAssistant\Models;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use TractorCow\Fluent\Model\Locale;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\Queries\SQLInsert;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorConfig;
use S2Hub\TextAssistant\Forms\TranslatableDataObjectField;
use S2Hub\TextAssistant\Helpers\TranslationJobHelper;
use SilverStripe\CMS\Forms\SiteTreeURLSegmentField;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\TextField;
use SilverStripe\View\SSViewer;
use SilverStripe\View\ViewableData;
use SilverStripe\View\ThemeResourceLoader;

class TranslationAction extends DataObject
{
    use Configurable;

    private static $allow_logging = true;

    private static $table_name = "TextAssistant_TranslationAction";

    private static $db = [
        'Locale' => 'Varchar(5)',
        'FromLocale' => 'Varchar(5)',

        'FieldName' => 'Varchar(255)',

        'Type' => 'Enum("Generated, Manual", "Manual")',
        'Status' => 'Enum("Draft, Accepted","Accepted")',

        'PublishedPlace' => 'Enum("TranslationAdmin, Default","Default")',

        'Value' => 'Text',
    ];

    private static $has_one = [
        'Object' => DataObject::class,
        'Creator' => Member::class,
    ];

    private static $summary_fields = [
        'FieldNameTranslation', 'LocaleNice', 'StatusNice'
    ];

    public function getTitle()
    {
        return DBHTMLText::create()->setValue($this->Object()->Title . " (" . $this->getFromToLocale() . ")");
    }

    private static $default_sort = 'Created DESC';

    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    public function canDelete($member = null)
    {
        return false;
    }

    public function getCMSFields()
    {
        $fields = new FieldList();

        $originObj = $this->Object();

        $originFields = $originObj->getCMSFields()->dataFields();

        if (isset($originFields[$this->FieldName])) {
            $originalField = $originFields[$this->FieldName];

        } else {
            $originalField = new TextareaField('a', 'b');
        }

        if ($originalField instanceof SiteTreeURLSegmentField) {
            $newOriginalField = new TextField($originalField->getName(), $originalField->Title());
            $originalField = $newOriginalField;
        }

        $originalFieldAttributes = $originalField->getAttributes();

        $fieldClass = get_class($originalField);

        $fromValueLabel = $this->getFieldNameTranslation();
        $toValueLabel = $this->getFieldNameTranslation() . " (" . _t(TranslationAction::class.'.SINGULARNAME', 'Translation') . ")";

        $fromValueTranslatableContainer = new TranslatableDataObjectField();
        $fromValueTranslatableContainer->setName("FromValue");
        $fromValueTranslatableContainer->setDefaultLocale($this->FromLocale);

        $allowedLocales = array_flip(Locale::getCached()->column('Locale'));
        unset($allowedLocales[$this->Locale]);

        foreach ($allowedLocales as $locale => $null) {
            $value = $this->getField("Value_" . $locale);

            // Only show a before-translation if the value is not empty
            if (empty($value)) {
                continue;
            }

            $field = new $fieldClass($this->FieldName . '_'. $locale, $fromValueLabel);

            $field->setValue($value);
            
            if ($fieldClass === HTMLEditorField::class) {

                /** @var TinyMCEConfig */
                $editorConfig = clone HTMLEditorConfig::get_active();
                $editorConfig->setButtonsForLine(1, []);
                $editorConfig->setOption('menubar', false);
                $editorConfig->setOption('contextmenu', '');
                $editorConfig->setOption('readonly', '1');
                $editorConfig->setOption('content_style', 'body { background-color: #f4f6f8; }');

                $field->setEditorConfig($editorConfig);

                $field->addExtraClass('html-editor-field-other-locale');

            } else {
                $field = $field->performReadonlyTransformation();

            }

            $field->disableTextAssistant();
            $fromValueTranslatableContainer->push($field, $locale);
        }

        $toValueField = new $fieldClass(
            "TranslationAction[".$this->ID."][".$this->FieldName . '_'.$this->Locale."]",
            DBHTMLText::create()->setValue($toValueLabel),
            $this->getField("Value_" . $this->Locale)); 


            
        if (!empty($originalFieldAttributes)) {

            $allowed_attributes = ["style", "rows", "cols", "data-maxlength", "class"];

            foreach ($allowed_attributes as $attribute) {
                if (isset($originalFieldAttributes[$attribute])) {
                    $toValueField->setAttribute($attribute, $originalFieldAttributes[$attribute]);
                }

            }
        }


        $fromValueContainer = CompositeField::create([$fromValueTranslatableContainer])->addExtraClass('translation-action-from-value-container');
        $toValueContainer = CompositeField::create([$toValueField])->addExtraClass('translation-action-to-value-container');

        $fields->push($fromValueContainer);
        $fields->push($toValueContainer);

        unset($originalField); // just to clear some RAM

        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    protected static $written_md5 = [];
    private static $_cached_possible_translatable_fields = [];
    // In this func only manually created are logged.
    // All manually created are also deleted, so only the "latest created" is logged.
    public static function logManual(DataObject $object, array $changedFields, $toStatus = "Accepted")
    {
        if (!self::config()->get('allow_logging')) return;

        $translatable_fields = $object->getTranslatableFields();

        if (empty($translatable_fields) || empty($changedFields)) return;
        
        if (!isset(self::$_cached_possible_translatable_fields[get_class($object)])) {
            $possible_translatable_fields = [];

            foreach (Locale::getCached()->column('Locale') as $locale) {
                foreach ($translatable_fields as $translatable_field) {
                    $possible_translatable_fields[] = $translatable_field . '_' . $locale;
                }
            }

            $possible_translatable_fields = array_flip($possible_translatable_fields);
            self::$_cached_possible_translatable_fields[get_class($object)] = $possible_translatable_fields;
        } else {
            $possible_translatable_fields = self::$_cached_possible_translatable_fields[get_class($object)];
        }

        $schema = Injector::inst()->get(DataObjectSchema::class);
        $tableName = $schema->tableName(TranslationAction::class);

        $affectedFields = [];
        $createdIds = [];

        $global_locale = Locale::getDefault();

        foreach ($possible_translatable_fields as $field => $null) {
            if (!isset($changedFields[$field])) continue;

            $fieldName = substr($field, 0, strlen($field) - 6);
            $locale = substr($field, strlen($field) - 5);

            if ($locale === $global_locale->Locale) {
                // If we're doing a change in the global locale, we assume the client knows what they're doing
                // so we do NOTHING.
                continue;
            }

            // this field is generated from Title, so we dont really care about it
            if ($fieldName === "URLSegment") {
                continue;
            }

            $createdVia = "Manual";

            if (isset($_POST["TextAssistantData"][$field])) {
                $postData = json_decode($_POST["TextAssistantData"][$field], true);

                if (isset($postData['GeneratedByAI'])) {
                    $createdVia = "Generated";
                }

            }

            $data = [
                'ObjectID' => $object->ID,
                'ObjectClass' => get_class($object),
                'LastEdited' => date('Y-m-d H:i:s'),
                'Created' => date('Y-m-d H:i:s'),

                'FieldName' => $fieldName,

                'Locale' => $locale,
                'Type' => $createdVia,
                'Status' => $toStatus,

                'CreatorID' => Security::getCurrentUser() ? Security::getCurrentUser()->ID : 0,
            ];

            // if all fields for all locales are empty, we dont want to save this.
            $areFieldsEmpty = true;

            foreach (self::getValueLocaleFields() as $valueLocale => $localeField) {
                $objFieldName = $object->getLocaleFieldName($fieldName, $valueLocale);
                $data[$localeField] = $object->getField($objFieldName);

                if (!empty($data[$localeField])) {
                    $areFieldsEmpty = false;
                }
            }

            if (!$areFieldsEmpty) {
                $query = SQLInsert::create($tableName, $data);
                $query->execute();
    
                $affectedFields[] = $field;
                $id = DB::get_generated_id($tableName);
                $createdIds[] = $id;
    
                if ($createdVia === "Generated") {
    
                    // Now since we generated this, we must delete old generated..
                    TranslationJobHelper::TranslatableDeleteOldGeneratedTranslations(
                        DataObject::get_by_id(TranslationAction::class, $id),
                        $object,
                        $field,
                        $locale
                    );
                }
            }
        }

        // After we've created our logs, we want to delete all manual previous logs for the same fields.
        // The intent of this is that we only save the "Latest" manual change.
        // The intent of TranslationAction isn't to be a log -- that's dedicated to either Versioned / LoggableDataObject.
        // TranslationAction is only for the "relevant" actions of translation.
        if (!empty($affectedFields) && !empty($createdIds)) {
            $oldActions = DataList::create(self::class)->filter([
                'ID:ExactMatch:not' => !empty($createdIds) ? $createdIds : [-1],
                'Type' => 'Manual',
                'ObjectID' => $object->ID,
                'ObjectClass' => get_class($object),
            ])->where('CONCAT(FieldName, \'_\', Locale) IN (\'' . implode("', '", $affectedFields) . '\')');
            
            foreach ($oldActions as $action) {
                $action->delete();
            }
        }
        
    }

    public function getFieldNameTranslation()
    {
        $test = _t($this->ObjectClass . '.' . strtoupper($this->FieldName), $this->FieldName);

        if ($test === $this->FieldName) {
            $test = _t($this->ObjectClass . '.' . $this->FieldName, $this->FieldName);
        }

        return $test;
    }

    private static $_cached_locale_nice = [];
    public function getLocaleNice($locale = "")
    {
        if (empty($locale)) {
            $locale = $this->Locale;
        }

        if (isset(self::$_cached_locale_nice[$locale])) {
            return self::$_cached_locale_nice[$locale];
        }

        $localeObj = DataObject::get(Locale::class)->filter('Locale', $locale)->first();

        if ($localeObj) {
            self::$_cached_locale_nice[$locale] = $localeObj->LocaleNice;
            return $localeObj->Title;
        }

        return $locale;
    }

    public function getStatusNice()
    {
        $color = "";

        switch($this->Status) {
            case "Draft":
                $color = "#FEFFD3";
                break;
            case "Rejected":
                $color = "#FFD3D3";
                break;
            case "Accepted":
                $color = "#D4FFD3";
                break;
        }

        return DBHTMLText::create()->setValue("<span style='padding: 5px; border-radius: 5px; background-color: $color;'>"._t(self::class.'.STATUS_'.strtoupper($this->Status), $this->Status)."</span>");
    }

    public function getFromValueSummary()
    {
        $fieldName = 'Value_' . $this->FromLocale;
        $value = strip_tags($this->getField($fieldName));

        if (strlen($value) > 300) {
            $value = substr($value, 0, 300) . '...';
        }

        return DBHTMLText::create()->setValue("<div style='max-width: 400px'>" . $value . "</div>");
    }

    public function getToValueSummary()
    {
        $fieldName = 'Value_' . $this->Locale;
        $value = strip_tags($this->getField($fieldName));

        if (strlen($value) > 300) {
            $value = substr($value, 0, 300) . '...';
        }

        return DBHTMLText::create()->setValue("<div style='max-width: 400px'>" . $value . "</div>");

    }

    public static function getValueLocaleFields(): array
    {
        $fields = [];

        $allowed_locales = Locale::getCached()->column('Locale');

        foreach ($allowed_locales as $locale) {
            $fields[$locale] = "Value_" . $locale;
        }

        return $fields;
    }

    public function getCreatedNice()
    {
        return date("d.m.Y H:i", strtotime($this->Created));
    }

    public static function getGridFieldFilterFields(): FieldList
    {
        $fields = new FieldList();

        $objectClasses = SQLSelect::create("DISTINCT ObjectClass", DataObjectSchema::create()->tableName(TranslationAction::class), "Status = 'Draft'")->execute()->map();
        $objectClassesTranslated = [];
        foreach ($objectClasses as $key => $value) {
            $objectClassesTranslated[$key] = _t($key.'.SINGULARNAME', $key);
        }

        $locales = SQLSelect::create("DISTINCT Locale", DataObjectSchema::create()->tableName(TranslationAction::class), "Status = 'Draft'")->execute()->map();
        $localesTranslated = [];
        $localeNameMap = Locale::get()->map('Locale', 'Title')->toArray();
        foreach ($locales as $key => $value) {
            if (isset($localeNameMap[$key])) {
                $localesTranslated[$key] = $localeNameMap[$key];
            } else {
                $localesTranslated[$key] = $key;
            }
        }
        
        $fields->push(new ListboxField('ObjectClass', _t(self::class.'.TYPE', 'Type'), $objectClassesTranslated));
        $fields->push(new ListboxField('Locale', _t(self::class.'.LOCALE', 'Locale'), $localesTranslated));

        return $fields;
    }

    public function getFromToLocale()
    {
        if (empty($this->FromLocale)) {
            return DBHTMLText::create()->setValue($this->getLocaleNice($this->Locale));
        } else {
            return DBHTMLText::create()->setValue($this->getLocaleNice($this->FromLocale) . " &rarr; " . $this->getLocaleNice($this->Locale));

        }

    }

    public function getTypeNice()
    {
        return _t(self::class.'.TYPE_'.strtoupper($this->Type), $this->Type);
    }

    public function getTranslationActionObjectInformation()
    {
        $subclasses = ClassInfo::dataClassesFor($this->Object->ClassName);
        array_reverse($subclasses);


        foreach ($subclasses as $subclass) {
            $class = substr($subclass, strrpos($subclass, '\\') + 1);

            $templateName = "TranslationAction_Information_".$class;

            if (ThemeResourceLoader::inst()->findTemplate($templateName, SSViewer::get_themes())) {
                return ViewableData::singleton()->renderWith($templateName, [
                    'TranslationAction' => $this
                ]);
            }
        }


        return false;
    }

    public function getField($field)
    {
        if (substr($field, 0, 6) === "Value_") {
            $locale = substr($field, 6);
            $currentValue = json_decode($this->Value ?: "", true);
            return $currentValue[$locale] ?? null;
        }

        return parent::getField($field);
    }

    public function setField($fieldName, $val)
    {
        if (substr($fieldName, 0, 6) === "Value_") {
            $locale = substr($fieldName, 6);
            $currentValue = json_decode($this->Value ?: "", true);
            $currentValue[$locale] = $val;
            $val = json_encode($currentValue);
            $fieldName = 'Value';
        }

        return parent::setField($fieldName, $val);
    }
}