<?php

namespace Concrete\Controller\SinglePage\Dashboard\System\Multilingual;

use \Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Page\EditResponse;
use Loader;
use Concrete\Core\Multilingual\Page\Section as MultilingualSection;
use Concrete\Core\Multilingual\Page\PageList as MultilingualPageList;
defined('C5_EXECUTE') or die("Access Denied.");

class PageReport extends DashboardPageController
{
    public $helpers = array('form');

    public function view()
    {
        $list = MultilingualSection::getList();
        $sections = array();
        usort($list, function($item) {
           if ($item->getLocale() == \Config::get('concrete.multilingual.default_locale')) {
               return -1;
           }  else {
               return 1;
           }
        });
        foreach ($list as $pc) {
            $sections[$pc->getCollectionID()] = $pc->getLanguageText() . " (" . $pc->getLocale() . ")";
        }
        $this->set('sections', $sections);
        $this->set('sectionList', $list);

        if (!isset($_REQUEST['sectionID']) && (count($sections) > 0)) {
            foreach ($sections as $key => $value) {
                $sectionID = $key;
                break;
            }
        } else {
            $sectionID = $_REQUEST['sectionID'];
        }

        if (!isset($_REQUEST['targets']) && (count($sections) > 1)) {
            $i = 0;
            foreach ($sections as $key => $value) {
                if ($key != $sectionID) {
                    $targets[$key] = $key;
                    break;
                }
                $i++;
            }
        } else {
            $targets = $_REQUEST['targets'];
        }
        if (!isset($targets) || (!is_array($targets))) {
            $targets = array();
        }

        $targetList = array();
        foreach ($targets as $key => $value) {
            $targetList[] = MultilingualSection::getByID($key);
        }
        $this->set('targets', $targets);
        $this->set('targetList', $targetList);
        $this->set('sectionID', $sectionID);
        $this->set('fh', \Core::make('multilingual/interface/flag'));

        if (isset($sectionID) && $sectionID > 0) {
            $pl = new MultilingualPageList();
            $pc = \Page::getByID($sectionID);
            $path = $pc->getCollectionPath();
            if (strlen($path) > 1) {
                $pl->filterByPath($path);
            }

            if ($_REQUEST['keywords']) {
                $pl->filterByName($_REQUEST['keywords']);
            }

            $pl->setItemsPerPage(25);
            $pl->ignoreAliases();
            if (!$_REQUEST['showAllPages']) {
                $pl->filterByMissingTargets($targetList);
            }

            $pagination = $pl->getPagination();
            $this->set('pagination', $pagination);
            $this->set('pages', $pagination->getCurrentPageResults());
            $this->set('section', MultilingualSection::getByID($sectionID));
            $this->set('pl', $pl);
        }
    }

    public function assign_page()
    {
        if (Loader::helper('validation/token')->validate('assign_page', $_POST['token'])) {
            if ($_REQUEST['destID'] == $_REQUEST['sourceID']) {
                print '<span class="ccm-error">' . t("You cannot assign this page to itself.") . '</span>';
                exit;
            }
            $destPage = Page::getByID($_POST['destID']);
            if (MultilingualSection::isMultilingualSection($destPage)) {
                $ms = MultilingualSection::getByID($destPage->getCollectionID());
            } else {
                $ms = MultilingualSection::getBySectionOfSite($destPage);
            }
            if (is_object($ms)) {
                $page = Page::getByID($_POST['sourceID']);

                // we need to assign/relate the source ID too, if it doesn't exist
                if (!MultilingualSection::isAssigned($page)) {
                    MultilingualSection::assignAdd($page);
                }

                MultilingualSection::relatePage($page, $destPage, $ms->getLocale());
                print '<a href="' . Loader::helper("navigation")->getLinkToCollection(
                        $destPage
                    ) . '">' . $destPage->getCollectionName() . '</a>';
            } else {
                print '<span class="ccm-error">' . t(
                        "The destination page doesn't appear to be in a valid multilingual section."
                    ) . '</span>';
            }
        }
        exit;
    }

    public function ignore()
    {
        if (Loader::helper('validation/token')->validate('ignore', $_POST['token'])) {
            $page = \Page::getByID($_POST['cID']);
            $section = MultilingualSection::getByID($_POST['section']);
            MultilingualSection::ignorePageRelation($page, $section->getLocale());
            $r = new EditResponse();
            $r->setPage($page);
            $r->setMessage(t('Page ignored.'));
            $r->outputJSON();
        }
    }
}