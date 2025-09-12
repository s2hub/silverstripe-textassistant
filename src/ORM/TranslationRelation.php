<?php

namespace S2Hub\TextAssistant\ORM;

use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\SS_List;

class TranslationRelation
{

    protected $name;
    protected $list;
    protected $checked_by_default;


    public function __construct(string $name, DataList $list, bool $checked_by_default = false)
    {
        $this->name = $name;
        $this->list = $list;
        $this->checked_by_default = $checked_by_default;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getObjectType()
    {
        return $this->list->dataClass();
    }

    public function getList(): SS_List
    {
        return $this->list;
    }

    public function getCount(): int
    {
        return $this->list->count();
    }

    public function isCheckedByDefault(): int
    {
        return $this->checked_by_default;
    }

    public function getNiceName()
    {
        return _t($this->getObjectType().'.PLURALNAME', 'x') . " (" . $this->getCount() . _t(self::class.'.PIECES', 'pcs')  . ")";
    }


    public function getRelatedObjectsToQueueForObject(DataObject $object): array
    {
        return [];
    }


}