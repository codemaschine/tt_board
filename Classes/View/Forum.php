<?php

namespace JambageCom\TtBoard\View;

/***************************************************************
*  Copyright notice
*
*  (c) 2018 Kasper Skårhøj <kasperYYYY@typo3.com>
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
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 *
 * Function library for a forum/board in tree or list style
 *
 * TypoScript config:
 * - See static_template 'plugin.tt_board_tree' and plugin.tt_board_list
 * - See TS_ref.pdf
 *
 * @author	Kasper Skårhøj  <kasperYYYY@typo3.com>
 * @author	Franz Holzinger <franz@ttproducts.de>
 */


use TYPO3\CMS\Core\Service\MarkerBasedTemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use JambageCom\TtBoard\Domain\Composite;

use JambageCom\Div2007\Utility\FrontendUtility;

class Forum implements \TYPO3\CMS\Core\SingletonInterface
{
    /**
    * Creates the forum display, including listing all items/a single item
    */
    public function printView (
        Composite $composite,
        $treeView,
        $conf,
        $ref,
        $theCode,
        $linkParams,
        $pid
    )
    {
        $uid = $composite->getTtBoardUid();
        $local_cObj = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class);
        $controlObj = GeneralUtility::makeInstance(\JambageCom\Div2007\Utility\ControlUtility::class);
        $recentPosts = [];
        $searchWord = $controlObj->readGP('sword', $prefixId);
        $pointerName = 'pointer';

        $markerObj = $composite->getMarkerObj();
        $modelObj = $composite->getModelObj();
        $alternativeLayouts = $composite->getAlternativeLayouts();
        $prefixId = $composite->getPrefixId();
        $typolinkConf = $composite->getTypolinkConf();
        $templateService = GeneralUtility::makeInstance(MarkerBasedTemplateService::class);

        $continue = true;

        if ($theCode == 'THREAD_TREE') {
            if (!$uid && $ref == '') {
                $continue = false;
            }
        }

        if ($conf['tree']) {
            $lConf = $conf['thread_tree.'];
        } else {
            $lConf = $conf['list_threads.'];
        }

        $limit = $lConf['thread_limit'];

        if ($continue) {
                // Clear
            $subpartMarkerArray = [];
            $wrappedSubpartArray = [];

            $browserConf = '';
            if (
                isset($lConf['browser']) &&
                $lConf['browser'] == 'div2007'
            ) {
                $templateCode =
                    $templateService->getSubpart(
                        $composite->getOrigTemplateCode(),
                        '###LINK_BROWSE###'
                    );

                if ($templateCode) {
                    $browserConf = [];
                    if (isset($lConf['browser.'])) {
                        $browserConf = $lConf['browser.'];
                    }
                }

                $addQueryString = [];
                $recordCount = 0;
                $more = 0;
                $useCache = true;

                if ($theCode == 'FORUM') {
                    $recordCount = $modelObj->getNumThreads(
                        $composite->getPidList(),
                        $ref,
                        $searchWord
                    );
                }

                if ($recordCount > $limit) {
                    $more = 1;
                }
                if ($searchWord != '') {
                    $addQueryString['sword'] = $searchWord;
                    $useCache = false;
                }
                $begin_at = intval($controlObj->readGP($pointerName, $prefixId)) * $limit;
                $piVars = $controlObj->readGP('', $prefixId);
                $markerObj->getBrowserMarkers(
                    $markerArray,
                    $subpartMarkerArray,
                    $wrappedSubpartArray,
                    $local_cObj,
                    $composite->getLanguageObj(),
                    $browserConf,
                    $prefixId,
                    $addQueryString,
                    $recordCount,
                    $piVars,
                    $limit,
                    $more,
                    $pointerName,
                    $begin_at,
                    $useCache
                );
            }

            $subpart = '###TEMPLATE_FORUM###';
            $templateCode =
                $templateService->getSubpart(
                    $composite->getOrigTemplateCode(),
                    '###TEMPLATE_FORUM###'
                );

            if ($templateCode) {
                    // Getting the specific parts of the template
                $markerObj->getColumnMarkers(
                    $markerArray,
                    $composite->getLanguageObj()
                );

                $markerArray['###FORUM_TITLE###'] =
                    $local_cObj->stdWrap(
                        $GLOBALS['TSFE']->page['title'],
                        $lConf['forum_title_stdWrap.']
                    );

                $templateCode =
                    $templateService->substituteMarkerArrayCached(
                        $templateCode,
                        $markerArray,
                        $subpartMarkerArray,
                        $wrappedSubpartArray
                    );

                $postHeader =
                    $markerObj->getLayouts(
                        $templateCode,
                        $alternativeLayouts,
                        'POST'
                    );
                    // Template code used if tt_board_uid matches...
                $postHeader_active =
                    $markerObj->getLayouts(
                        $templateCode,
                        1,
                        'POST_ACTIVE'
                    );
                $subpartContent = '';

                if (
                    $theCode == 'THREAD_TREE' &&
                    $uid > 0
                ) {
                    $parentUid = $uid;
                    $rootParent =
                        $modelObj->getRootParent(
                            $uid,
                            $ref
                        );
                    if (is_array($rootParent)) {
                        $parentUid = $rootParent['uid'];
                    }

                    $recentPosts =
                        $modelObj->getSingleThread(
                            $parentUid,
                            $ref,
                            1
                        );
                } else {
                    $recentPosts =
                        $modelObj->getThreads(
                            $composite->getPidList(),
                            $ref,
                            $conf['tree'],
                            $lConf['thread_limit'] ?
                                $lConf['thread_limit'] :
                                '50',
                            $begin_at,
                            $controlObj->readGP('sword', $prefixId)
                        );
                }

                if (is_object($treeView)) {
                    $treeView->addTreeIcons($recentPosts);
                }

                $c_post = 0;
                $subpartArray = [];
                $tagArray = \JambageCom\Div2007\Utility\MarkerUtility::getTags($templateCode);

                foreach ($recentPosts as $recentPost) {
                    $out = $postHeader[$c_post % count($postHeader)];

                    if ($recentPost['uid'] == $uid && $postHeader_active[0]) {
                        $out = $postHeader_active[0];
                    }
                    $c_post++;
                    $local_cObj->start($recentPost);

                        // Clear
                    $markerArray = [];
                    $wrappedSubpartArray = [];

                        // Markers
                    $markerObj->getRowMarkerArray(
                        $markerArray,
                        $modelObj,
                        $recentPost,
                        'POST',
                        $tagArray,
                        $lConf
                    );

                        // Link to the post
                    $overrulePIvars = [
                        'uid' => ($recentPost['uid'])
                    ];

                    $linkParams[$prefixId . '[uid]'] = $recentPost['uid'];
                    $url =
                        FrontendUtility::getTypoLink_URL(
                            $local_cObj,
                            $pid,
                            $linkParams,
                            '',
                            ['useCacheHash' => $composite->getAllowCaching()]
                    );
                    $wrappedSubpartArray['###LINK###'] =
                        [
                            '<a href="' . htmlspecialchars($url)  . '">',
                            '</a>'
                        ];

                        // Last post processing:
                    $lastPostInfo =
                        $modelObj->getLastPostInThread(
                            $recentPost['pid'],
                            $recentPost['uid'],
                            $ref
                        );
                    if (!$lastPostInfo) {
                        $lastPostInfo = $recentPost;
                    }

                    $local_cObj->start($lastPostInfo);
                    $recentDate = $modelObj->recentDate($lastPostInfo);
                    $markerArray['###LAST_POST_DATE###'] =
                        $local_cObj->stdWrap(
                            $recentDate,
                            $conf['date_stdWrap.']
                        );
                    $markerArray['###LAST_POST_TIME###'] =
                        $local_cObj->stdWrap(
                            $recentDate,
                            $conf['time_stdWrap.']
                        );
                    $markerArray['###LAST_POST_AGE###'] =
                        $local_cObj->stdWrap(
                            $recentDate,
                            $conf['age_stdWrap.']
                        );
                    $markerArray['###LAST_POST_AUTHOR###'] =
                        $local_cObj->stdWrap(
                            $markerObj->formatStr($lastPostInfo['author']),
                            $lConf['last_post_author_stdWrap.']
                        );
                    $markerArray['###LAST_POST_CITY###'] =
                        $local_cObj->stdWrap(
                            $markerObj->formatStr($lastPostInfo['city']),
                            $lConf['last_post_city_stdWrap.']
                        );

                        // Link to the last post
                    $linkParams[$prefixId . '[uid]'] = $lastPostInfo['uid'];
                    $url =
                        FrontendUtility::getTypoLink_URL(
                            $local_cObj,
                            $pid,
                            $linkParams,
                            '',
                            ['useCacheHash' => $composite->getAllowCaching()]
                        );
                    $wrappedSubpartArray['###LINK_LAST_POST###'] =
                        [
                            '<a href="' .  htmlspecialchars($url) . '">',
                            '</a>'
                        ];

                        // Substitute:
                    $subpartArray[$recentDate . sprintf('%010d', $recentPost['uid'])] =
                        $templateService->substituteMarkerArrayCached(
                            $out,
                            $markerArray,
                            [],
                            $wrappedSubpartArray
                        );
                }

                // if (!$conf['tree']) {
                //     krsort($subpartArray);
                // }
                $subpartArray = \array_reverse($subpartArray, true);

                    // Substitution:
                $markerArray = [];
                $subpartContentArray = [];
                $markerArray['###SEARCH_NAME###'] = $prefixId . '[sword]';

                    // Fill in array
                $markerArray['###SEARCH_WORD###'] =
                    $GLOBALS['TSFE']->no_cache ?
                        $controlObj->readGP('sword', $prefixId) :
                        '';	// Setting search words in field if cache is disabled.
                    // Set FORM_URL
                $local_cObj->setCurrentVal($GLOBALS['TSFE']->id);
                $temp_conf = $typolinkConf;
                $temp_conf['no_cache'] = 1;
                $markerArray['###FORM_URL###'] = $local_cObj->typoLink_URL($temp_conf);
                $subpartContent = implode('', $subpartArray);

                // Substitute CONTENT-subpart
                $subpartContentArray['###CONTENT###'] = $subpartContent;

                $newContent = $templateService->substituteMarkerArrayCached(
                    $templateCode,
                    $markerArray,
                    $subpartContentArray
                );

                $content .= $newContent;
            } // if ($templateCode) {
        } // if($continue) {

        return $content;
    } // printView
}
