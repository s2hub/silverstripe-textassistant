<?php

namespace S2Hub\TextAssistant\ORM;

use S2Hub\TextAssistant\Models\TranslationAction;
use S2Hub\TextAssistant\View\TranslationActionContainerObject;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Injector\Injector;

class TranslationActionDataList extends DataList
{

    public function __construct()
    {
        parent::__construct(TranslationAction::class);

        $this->dataQuery = $this->dataQuery->groupby("ObjectID, ObjectClass, Locale");
    }

    public function count(): int
    {
        return $this->dataQuery->getFinalisedQuery()->count("DISTINCT ObjectID, ObjectClass, Locale");
    }

    public function createDataObject($row)
    {
        $creationType = empty($row['ID']) ? DataObject::CREATE_OBJECT : DataObject::CREATE_HYDRATED;

        $item = Injector::inst()->create(TranslationActionContainerObject::class, $row, $creationType, $this->getQueryParams());

        return $item;
    }

    public function byID($id)
    {
        $object = TranslationAction::get()->byID($id);
        return $object;
    }
    
}