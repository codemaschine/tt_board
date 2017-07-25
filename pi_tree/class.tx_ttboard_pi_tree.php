<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2017 Franz Holzinger <franz@ttproducts.de>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*0
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * boardLib.inc
 *
 * Creates a forum/board in tree or list style
 *
 * TypoScript config:
 * - See static_template 'plugin.tt_board_tree' and plugin.tt_board_list
 * - See TS_ref.pdf
 *
 * @author	Kasper Skårhøj <kasperYYYY@typo3.com>
 * @author	Franz Holzinger <franz@ttproducts.de>
 */



class tx_ttboard_pi_tree extends tx_ttboard_pibase {
    public $prefixId = 'tx_ttboard_pi_tree';	// Same as class name
    public $scriptRelPath = 'pi_list/class.tx_ttboard_pi_tree.php';	// Path to this script relative to the extension dir.
    public $list_type = '2';


    /**
    * Main board function. Call this from TypoScript
    */
    public function main ($content, $conf) {
        $bOrigInitCalled = false;
        $this->conf = $conf;
        $codeArray = $this->getCodeArray($conf);
        $bCreate = true;

        foreach ($codeArray as $k => $theCode) {
            $theCode = (string)strtoupper(trim($theCode));
            switch($theCode) {
                default:
                    $setupCode = $conf['userFunc.'][$theCode];
                    if ($setupCode) {
                        $bOrigInitCalled = false;
                        $setup = $conf['userFunc.'][$theCode . '.'];
                        $newConf = $conf;
                        tx_div2007_core::mergeRecursiveWithOverrule(
                            $newConf,
                            $setup['10.']
                        );

                        unset($newConf['userFunc.']);
                        $newSetup = array();
                        if ($setupCode == 'COA') {
                            $newSetup['10'] = 'USER';
                        } else {
                            $newSetup['10'] = 'USER_INT';
                        }
                        $newSetup['10'] = 'USER';
                        $newSetup['10.'] = $newConf;
                        $content .= $this->cObj->cObjGetSingle($setupCode, $newSetup);
                    } else {
                        if (!$bOrigInitCalled) {
                            $bOrigInitCalled = true;
                            parent::init ($content, $conf);
                        }
                        parent::processCode($theCode, $content);
                    }
                break;
            }	// Switch

            if ($this->errorMessage) {
                break;
            }
        }
        return $content;
    }


    public function help ($content, $conf) {
        parent::init($content, $conf);
        parent::processCode('HELP', $content);
        return $content;
    }


    public function listCagetories ($content, $conf) {
        parent::init($content, $conf);
        parent::processCode('LIST_CATEGORIES', $content);
        return $content;
    }


    public function listForums ($content, $conf) {
        parent::init($content, $conf);
        parent::processCode('LIST_FORUMS', $content);
        return $content;
    }


    public function forum ($content, $conf) {
        parent::init($content, $conf);
        parent::processCode('FORUM', $content);
        return $content;
    }


    public function postForm ($content, $conf) {
        parent::init($content, $conf);
        parent::processCode('POSTFORM', $content);
        return $content;
    }


    public function postFormReply ($content, $conf) {
        parent::init($content, $conf);
        parent::processCode('POSTFORM_REPLY', $content);
        return $content;
    }


    public function thread ($content, $conf) {
        parent::init($content, $conf);
        parent::processCode('POSTFORM_THREAD', $content);
        return $content;
    }


    public function threadTree ($content, $conf) {
        parent::init($content, $conf);
        parent::processCode('THREAD_TREE', $content);
        return $content;
    }
}

