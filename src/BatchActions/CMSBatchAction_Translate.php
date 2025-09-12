<?php

namespace S2Hub\TextAssistant\BatchActions;

use SilverStripe\Admin\CMSBatchAction;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Permission;
use SilverStripe\ORM\SS_List;

class CMSBatchAction_Translate extends CMSBatchAction
{
    public function getActionTitle()
    {
        return _t(__CLASS__ . '.TITLE', 'Translate with AI');
    }

    public function run(SS_List $objs): HTTPResponse
    {
        return new HTTPResponse("OK");
    }

    public function applicablePages($ids)
    {
        // Basic permission check based on SiteTree::canEdit
        if (!Permission::check(["ADMIN", "SITETREE_EDIT_ALL"])) {
            return [];
        }

        return $this->applicablePagesHelper($ids, 'canView', true, true);
    }
}
