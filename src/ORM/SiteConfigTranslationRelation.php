<?php

namespace S2Hub\TextAssistant\ORM;

use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use DNADesign\Elemental\Models\BaseElement;
use S2Hub\TextAssistant\Forms\BatchActions_TranslateForm;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;

class SiteConfigTranslationRelation extends TranslationRelation
{
    protected $name;
    protected $list;
    protected $checked_by_default;

    public function __construct(string $name, SiteConfig $siteConfig, bool $checked_by_default = false)
    {
        $this->name = $name;
        $this->list = new ArrayList([$siteConfig]);
        $this->checked_by_default = $checked_by_default;
    }

    public function getRelatedObjectsToQueueForObject(DataObject $object): array
    {
        $items = [];

        if ($object->hasExtension(ElementalAreasExtension::class)) {
            $elementIDs = [];
            $relations = $object->getElementalRelations();
            foreach ($relations as $relation) {
                $area = $object->getComponent($relation);
                if ($area && $area->exists()) {
                    $elementIDs = array_merge($elementIDs, $area->Elements()->column('ID'));
                }
            }

            if (!empty($elementIDs)) {
                $items['Blocks'] = BaseElement::get()->byIDs($elementIDs);
            }
        }
        
        return $items;
    }

    public function getObjectType()
    {
        return SiteConfig::class;
    }

    public function getNiceName()
    {
        return _t(BatchActions_TranslateForm::class.'.SITESETTINGS', 'Site settings');
    }
}