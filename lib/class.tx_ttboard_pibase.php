<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2007 Kasper Skårhøj <kasperYYYY@typo3.com>
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
 * boardLib.inc
 *
 * Function library for a forum/board in tree or list style
 *
 * TypoScript config:
 * - See static_template 'plugin.tt_board_tree' and plugin.tt_board_list
 * - See TS_ref.pdf
 *
 * $Id$
 * 
 * @author	Kasper Skårhøj  <kasperYYYY@typo3.com>
 * @author	Franz Holzinger <kontakt@fholzinger.com>
 */


require_once(PATH_tslib.'class.tslib_pibase.php');

require_once (PATH_BE_ttboard.'marker/class.tx_ttboard_marker.php');


class tx_ttboard_pibase extends tslib_pibase {
	var $extKey = TT_BOARD_EXTkey;	// The extension key.
	var $cObj;		// The backReference to the mother cObj object set at call time

	var $enableFields = '';		// The enablefields of the tt_board table.
	var $dontParseContent=0;
	var $treeIcons=array(
		'joinBottom'=>'\\-',
		'join'=>'|-',
		'line'=>'|&nbsp;',
		'blank'=>'&nbsp;&nbsp;',
		'thread'=>'+',
		'end'=>'-'
	);
	var $searchFieldList='author,email,subject,message';

	var $emoticons = 1;
	var $emoticonsPath = 'media/emoticons/';
	var $emoticonsTag = '<img src="{}" valign="bottom" hspace=4>';
	var $emoticonsSubst=array(
		':-)' => 'smile.gif',
		';-)' => 'wink.gif',
		':-D' => 'veryhappy.gif',
		':-(' => 'sad.gif'
	);

	var $alternativeLayouts='';
	var $allowCaching='';
	var $conf=array();
	var $config=array();
	var $pid_list;			// list of page ids

	var $tt_board_uid='';
	var $pid='';
	var $orig_templateCode='';
	var $typolink_conf=array();
	var $local_cObj='';

	var $errorMessage;
	var $markerObj;

	/**
	 * does the initialization stuff
	 *
	 * @param		string		  content string
	 * @param		string		  configuration array
	 * @param		string		  modified configuration array
	 * @return	  void
 	 */
	function init (&$content,&$conf,&$config) {
		global $TSFE;

		// *************************************
		// *** getting configuration values:
		// *************************************

		$this->conf = &$conf;
		$this->config = &$config;

		if (t3lib_extMgm::isLoaded(FH_LIBRARY_EXTkey)) {
		 		// FE BE library for language functions
			include_once(PATH_BE_fh_library.'lib/class.tx_fhlibrary_language.php');
			tx_fhlibrary_language::pi_loadLL($this,'EXT:'.$this->extKey.'/share/locallang.xml');
		}

		$this->tt_board_uid = intval(t3lib_div::_GP('tt_board_uid'));
		$this->alternativeLayouts = intval($this->conf['alternatingLayouts'])>0 ? intval($this->conf['alternatingLayouts']) : 2;
	
			// pid_list is the pid/list of pids from where to fetch the guest items.
		$tmp = trim($this->cObj->stdWrap($conf['pid_list'],$conf['pid_list.']));
		
		$pid_list = $config['pid_list'] = ($conf['pid_list'] ? $conf['pid_list'] :trim($this->cObj->stdWrap($conf['pid_list'],$conf['pid_list.'])));
		$this->pid_list = ($pid_list ? $pid_list : $TSFE->id);
		// page where to go usually
		$this->pid = ($conf['PIDforum'] ? $conf['PIDforum'] : $TSFE->id);

			// template is read.
		$this->orig_templateCode = $this->cObj->fileResource($conf['templateFile']);

		$this->allowCaching = $this->conf['allowCaching'] ? 1 : 0;
		$this->markerObj = t3lib_div::makeInstance('tx_ttboard_marker');
		$this->markerObj->init($this, $conf, $config);

		$globalMarkerArray = $this->markerObj->getGlobalMarkers();
			// Substitute Global Marker Array
		$this->orig_templateCode= $this->cObj->substituteMarkerArray($this->orig_templateCode, $globalMarkerArray);

			// TypoLink.
		$this->typolink_conf = $this->conf['typolink.'];
		$this->typolink_conf['parameter.']['current'] = 1;
		$this->typolink_conf['additionalParams'] = $this->cObj->stdWrap(
			$this->typolink_conf['additionalParams'],
			$this->typolink_conf['additionalParams.']
		);
		unset($this->typolink_conf['additionalParams.']);

		// *************************************
		// *** doing the things...:
		// *************************************

		$this->enableFields = $this->cObj->enableFields('tt_board');
		$this->dontParseContent = $this->conf['dontParseContent'];
		$this->local_cObj = t3lib_div::makeInstance('tslib_cObj');		// Local cObj.

			// If the current record should be displayed.
		$config['displayCurrentRecord'] = $conf['displayCurrentRecord'];
		if ($config['displayCurrentRecord'])	{
			$config['code']='FORUM';
			$this->tt_board_uid=$this->cObj->data['uid'];
		}


		// *************************************
		// *** doing the things...:
		// *************************************
		// tt_guest parts:
//		$this->recordCount = $this->getRecordCount($this->pid_list);
//		$globalMarkerArray['###PREVNEXT###'] = $this->getPrevNext();

		// all extensions:

			// Substitute Global Marker Array
		$this->orig_templateCode= $this->cObj->substituteMarkerArray($this->orig_templateCode, $globalMarkerArray);	
	}

	function getCodeArray(&$conf)	{
		$config = array();
		if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['useFlexforms'] && t3lib_extMgm::isLoaded(FH_LIBRARY_EXTkey)) {
		 		// FE BE library for flexform functions
			require_once(PATH_BE_fh_library.'lib/class.tx_fhlibrary_flexform.php');
				// check the flexform
			$this->pi_initPIflexForm();
			$config['code'] = tx_fhlibrary_flexform::getSetupOrFFvalue(
				$this, 
				$conf['code'], 
				$conf['code.'],
				$conf['defaultCode'], 
				$this->cObj->data['pi_flexform'], 
				'display_mode',
				$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['useFlexforms']
			);
		} else {
				// 'CODE' decides what is rendered:
			$config['code'] = $this->cObj->stdWrap($conf['code'],$conf['code.']);
			$config['code'] = $config['code'] ? $config['code'] : $conf['defaultCode'];
		}

		$codeArray=t3lib_div::trimExplode(',', $config['code'],1);
		if (!count($codeArray))	$codeArray=array('');
		return ($codeArray);
	}

	function processCode($theCode, &$content)	{
		global $TSFE;

		switch($theCode)	{
			case 'LIST_CATEGORIES':
			case 'LIST_FORUMS':
				$content.= $this->forum_list($theCode);
			break;
			case 'POSTFORM':
			case 'POSTFORM_REPLY':
			case 'POSTFORM_THREAD':
				$content.= $this->forum_postform($theCode);
			break;
			case 'FORUM':
			case 'THREAD_TREE':
				$content.= $this->forum_forum($theCode);
			break;
			default:
				$contentTmp = 'error';
			break;
		}	// Switch

		if ($contentTmp == 'error') {
			$fileName = 'EXT:'.TT_BOARD_EXTkey.'/template/board_help.tmpl';
			$helpTemplate = $this->cObj->fileResource($fileName);
			if (t3lib_extMgm::isLoaded(FH_LIBRARY_EXTkey)) {
			 		// FE BE library for flexform functions
				require_once(PATH_BE_fh_library.'lib/class.tx_fhlibrary_view.php');
				$content .= tx_fhlibrary_view::displayHelpPage($this, $helpTemplate, $this->extKey, $this->errorMessage, $theCode);
			}
		}
	}

	/**
	 * Creates a list of forums or categories depending on theCode
	 */
	function forum_list($theCode)	{
		if (!$this->tt_board_uid)	{
			$forumlist=0;		// set to true if this is a list of forums and not categories + forums
			if ($theCode=='LIST_CATEGORIES')	{
					// Config if categories are listed.
				$lConf=	$this->conf['list_categories.'];
			} else {
				$forumlist=1;
					// Config if forums are listed.
				$lConf=	$this->conf['list_forums.'];
				$lConf['noForums'] = 0;
			}
			$GLOBALS['TSFE']->set_cache_timeout_default($lConf['cache_timeout'] ? intval($lConf['cache_timeout']) : 60*5);
			$templateCode = $this->local_cObj->getSubpart($this->orig_templateCode, '###TEMPLATE_OVERVIEW###');

			if ($templateCode)	{
				
					// Clear
				$subpartMarkerArray = array();
				$wrappedSubpartContentArray = array();

					// Getting the specific parts of the template

				$markerArray = $this->markerObj->getColumnMarkers();
				$templateCode = $this->local_cObj->substituteMarkerArrayCached($templateCode,$markerArray,$subpartMarkerArray,$wrappedSubpartContentArray);

					// Getting the specific parts of the template
				$categoryHeader = $this->getLayouts($templateCode,$this->alternativeLayouts,'CATEGORY');
				$forumHeader = $this->getLayouts($templateCode,$this->alternativeLayouts,'FORUM');
				$postHeader = $this->getLayouts($templateCode,$this->alternativeLayouts,'POST');
				$subpartContent = '';

					// Getting categories
				$categories = $this->getPagesInPage($this->pid_list);
				reset($categories);
				$c_cat=0;
				foreach ($categories as $k => $catData)	{
						// Getting forums in category
					if ($forumlist)	{
						$forums = $categories;
					} else {
						$forums = $this->getPagesInPage($catData['uid']);
					}
					if (!$forumlist && count($categoryHeader))	{
							// Rendering category
						$out=$categoryHeader[$c_cat%count($categoryHeader)];
						$c_cat++;
						$this->local_cObj->start($catData);

							// Clear
						$markerArray=array();
						$wrappedSubpartContentArray=array();

							// Markers
						$markerArray['###CATEGORY_TITLE###']=$this->local_cObj->stdWrap($this->formatStr($catData['title']), $lConf['title_stdWrap.']);
						$markerArray['###CATEGORY_DESCRIPTION###']=$this->local_cObj->stdWrap($this->formatStr($catData['subtitle']), $lConf['subtitle_stdWrap.']);
						$markerArray['###CATEGORY_FORUMNUMBER###']=$this->local_cObj->stdWrap(count($forums), $lConf['count_stdWrap.']);

							// Link to the category (wrap)
						$this->local_cObj->setCurrentVal($catData['uid']);
						$wrappedSubpartContentArray['###LINK###']=$this->local_cObj->typolinkWrap($this->typolink_conf);

							// Substitute
						$subpartContent.=$this->local_cObj->substituteMarkerArrayCached($out,$markerArray,array(),$wrappedSubpartContentArray);
					}
					if (count($forumHeader) && !$lConf['noForums'])	{
							// Rendering forums
						$c_forum=0;
						foreach($forums as $k2 => $forumData)	{
							$out=$forumHeader[$c_forum%count($forumHeader)];
							$c_forum++;

							$this->local_cObj->start($forumData);

								// Clear
							$markerArray=array();
							$wrappedSubpartContentArray=array();


								// Markers
							$markerArray['###FORUM_TITLE###']=$this->local_cObj->stdWrap($this->formatStr($forumData['title']), $lConf['forum_title_stdWrap.']);
							$markerArray['###FORUM_DESCRIPTION###']=$this->local_cObj->stdWrap($this->formatStr($forumData['subtitle']), $lConf['forum_description_stdWrap.']);
							$markerArray['###FORUM_POSTS###']=$this->local_cObj->stdWrap($this->getNumPosts($forumData['uid']), $lConf['forum_posts_stdWrap.']);
							$markerArray['###FORUM_THREADS###']=$this->local_cObj->stdWrap($this->getNumThreads($forumData['uid']), $lConf['forum_threads_stdWrap.']);

								// Link to the forum (wrap)
							$this->local_cObj->setCurrentVal($forumData['uid']);
							$wrappedSubpartContentArray['###LINK###']=$this->local_cObj->typolinkWrap($this->typolink_conf);


								// LAST POST:
							$lastPostInfo = $this->getLastPost($forumData['uid']);
							$this->local_cObj->start($lastPostInfo);
							if ($lastPostInfo)	{
								$markerArray['###LAST_POST_AUTHOR###'] = $this->local_cObj->stdWrap($this->formatStr($lastPostInfo['author']), $lConf['last_post_author_stdWrap.']);
								$markerArray['###LAST_POST_DATE###'] = $this->local_cObj->stdWrap($this->recentDate($lastPostInfo),$this->conf['date_stdWrap.']);
								$markerArray['###LAST_POST_TIME###'] = $this->local_cObj->stdWrap($this->recentDate($lastPostInfo),$this->conf['time_stdWrap.']);
								$markerArray['###LAST_POST_AGE###'] = $this->local_cObj->stdWrap($this->recentDate($lastPostInfo),$this->conf['age_stdWrap.']);
							} else {
								$markerArray['###LAST_POST_AUTHOR###'] = '';
								$markerArray['###LAST_POST_DATE###'] = '';
								$markerArray['###LAST_POST_TIME###'] = '';
								$markerArray['###LAST_POST_AGE###'] = '';
							}

								// Link to the last post
							$this->local_cObj->setCurrentVal($lastPostInfo['pid']);
							$temp_conf=$this->typolink_conf;
							$temp_conf['additionalParams'].= '&tt_board_uid='.$lastPostInfo['uid'];
							$temp_conf['useCacheHash']=$this->allowCaching;
							$temp_conf['no_cache']=!$this->allowCaching;
							$wrappedSubpartContentArray['###LINK_LAST_POST###']=$this->local_cObj->typolinkWrap($temp_conf);

								// Add result
							$subpartContent.=$this->local_cObj->substituteMarkerArrayCached($out,$markerArray,array(),$wrappedSubpartContentArray);

								// Rendering the most recent posts
							if (count($postHeader) && $lConf['numberOfRecentPosts'])	{
								$recentPosts = $this->getMostRecentPosts($forumData['uid'],intval($lConf['numberOfRecentPosts']));
								reset($recentPosts);
								$c_post=0;
								while(list(,$recentPost)=each($recentPosts))	{
									$out=$postHeader[$c_post%count($postHeader)];
									$c_post++;
									$this->local_cObj->start($recentPost);

										// Clear:
									$markerArray=array();
									$wrappedSubpartContentArray=array();

										// markers:
									$markerArray['###POST_TITLE###'] = $this->local_cObj->stdWrap($this->formatStr($recentPost['subject']), $lConf['post_title_stdWrap.']);
									$markerArray['###POST_CONTENT###'] = $this->substituteEmoticons($this->local_cObj->stdWrap($this->formatStr($recentPost['message']), $lConf['post_content_stdWrap.']));
									$markerArray['###POST_REPLIES###'] = $this->local_cObj->stdWrap($this->getNumReplies($recentPost['pid'],$recentPost['uid']), $lConf['post_replies_stdWrap.']);
									$markerArray['###POST_AUTHOR###'] = $this->local_cObj->stdWrap($this->formatStr($recentPost['author']), $lConf['post_author_stdWrap.']);
									$markerArray['###POST_DATE###'] = $this->local_cObj->stdWrap($this->recentDate($recentPost),$this->conf['date_stdWrap.']);
									$markerArray['###POST_TIME###'] = $this->local_cObj->stdWrap($this->recentDate($recentPost),$this->conf['time_stdWrap.']);
									$markerArray['###POST_AGE###'] = $this->local_cObj->stdWrap($this->recentDate($recentPost),$this->conf['age_stdWrap.']);

										// Link to the post:
									$this->local_cObj->setCurrentVal($recentPost['pid']);
									$temp_conf=$this->typolink_conf;
									$temp_conf['additionalParams'].= '&tt_board_uid='.$recentPost['uid'];
									$temp_conf['useCacheHash']=$this->allowCaching;
									$temp_conf['no_cache']=!$this->allowCaching;
									$wrappedSubpartContentArray['###LINK###']=$this->local_cObj->typolinkWrap($temp_conf);
									$subpartContent.=$this->local_cObj->substituteMarkerArrayCached($out,$markerArray,array(),$wrappedSubpartContentArray);
										// add result
									#$subpartContent.=$out;	// 250902
								}
							}
						}
					}
					if ($forumlist)	{
						break;
					}
				}
					// Substitution:
				$content.= $this->local_cObj->substituteSubpart($templateCode,'###CONTENT###',$subpartContent) ;
			} else {
				$content = $this->outMessage('No template code for ###TEMPLATE_OVERVIEW###');
			}
		}
		return $content;
	}

	/**
	 * Checks if posting is allowed to user
	 */
	function isAllowed($memberOfGroups)	{
		global $TSFE;

		$allowed = false;
		if ($memberOfGroups)	{
			if (is_array($TSFE->fe_user->user))	{
				$requestGroupArray = t3lib_div::trimExplode(',', $memberOfGroups);
				$usergroupArray = explode(',',$TSFE->fe_user->user['usergroup']);
				$fitArray = array_intersect($requestGroupArray, $usergroupArray);
				if (count($fitArray))	{
					$allowed = true;
				}
			} else {
				$allowed = false;
			}
		} else {
			$allowed = true;
		}

		return $allowed;
	}

	/**
	 * Creates a post form for a forum
	 */
	function forum_postform($theCode)	{
		global $TSFE;

		$content = '';

		if ($this->isAllowed($this->conf['memberOfGroups']))	{
			$parent=0;		// This is the parent item for the form. If this ends up being is set, then the form is a reply and not a new post.
			$nofity=array();
				// Find parent, if any
			if ($this->tt_board_uid)	{
				if ($this->conf['tree'])	{
					$parent = $this->tt_board_uid;
				} else {
					$parentR = $this->getRootParent($this->tt_board_uid);
					$parent = $parentR['uid'];
				}
	
				$rootParent = $this->getRootParent($parent);
				$wholeThread = $this->getSingleThread($rootParent['uid'],1);
				reset($wholeThread);
				while(list(,$recordP)=each($wholeThread))	{
					if ($recordP['notify_me'] && $recordP['email'])		{
						$notify[md5(trim(strtolower($recordP['email'])))] = trim($recordP['email']);
					}
				}
			}
	
				// Get the render-code
			$lConf = $this->conf['postform.'];

//   postform.dataArray {
//     10.label = Subject:
//     10.type = *data[tt_board][NEW][subject]=input,60
//     20.label = Message:
//     20.type =  *data[tt_board][NEW][message]=textarea,60
//     30.label = Name:
//     30.type = *data[tt_board][NEW][author]=input,40
//     40.label = Email:
//     40.type = *data[tt_board][NEW][email]=input,40
//     50.label = Notify me<BR>by reply: 
//     50.type = data[tt_board][NEW][notify_me]=check
//     60.type = formtype_db=submit
//     60.value = Post Reply
//   }

			$setupArray = array('10' => 'subject', '20' => 'message', '30' => 'author', '40' => 'email', '50' => 'notify_me', '60' => 'post_reply');

			$modEmail = $this->conf['moderatorEmail'];
			if (!$parent && isset($this->conf['postform_newThread.']))	{
				$lConf = $this->conf['postform_newThread.'] ? $this->conf['postform_newThread.'] : $lConf;			// Special form for newThread posts...
				$modEmail = $this->conf['moderatorEmail_newThread'] ? $this->conf['moderatorEmail_newThread'] : $modEmail;
				$setupArray['60'] = 'post_new_reply';
			}
			if ($modEmail)	{
				$modEmail = explode(',', $modEmail);
				while(list(,$modEmail_s)=each($modEmail))	{
					$notify[md5(trim(strtolower($modEmail_s)))] = trim($modEmail_s);
				}
			}
			if ($theCode=='POSTFORM' || ($theCode=='POSTFORM_REPLY' && $parent) || ($theCode=='POSTFORM_THREAD' && !$parent))	{
				$lConf['dataArray.']['9999.'] = array(
					'type' => '*data[tt_board][NEW][parent]=hidden',
					'value' => $parent
				);
				$lConf['dataArray.']['9998.'] = array(
					'type' => '*data[tt_board][NEW][pid]=hidden',
					'value' => $this->pid
				);
				$lConf['dataArray.']['9997.'] = array(
					'type' => 'tt_board_uid=hidden',
					'value' => $parent
				);
				if (count($notify))		{
					$lConf['dataArray.']['9997.'] = array(
						'type' => 'notify_me=hidden',
						'value' => htmlspecialchars(implode($notify,','))
					);
				}
				if (is_array($TSFE->fe_user->user))	{
					foreach ($lConf['dataArray.'] as $k => $dataRow)	{
						if (strpos($dataRow['type'],'[author]') !== FALSE)	{
							$lConf['dataArray.'][$k]['value'] = $TSFE->fe_user->user['name'];
						} else if (strpos($dataRow['type'],'[email]') !== FALSE)	{
							$lConf['dataArray.'][$k]['value'] = $TSFE->fe_user->user['email'];
						}
					}
				}

				foreach ($setupArray as $k => $type)	{
					if ($k == '60')	{
						$field = 'value';
					} else {
						$field = 'label';
					}
					if (is_array($lConf['dataArray.'][$k.'.']))	{
						if (
							(!$this->LLkey || $this->LLkey=='en') && !$lConf['dataArray.'][$k.'.'][$field] || 
							($this->LLkey!='en' && 
								!is_array($lConf['dataArray.'][$k.'.'][$field.'.']) ||  !is_array($lConf['dataArray.'][$k.'.'][$field.'.']['lang.']) || !is_array($lConf['dataArray.'][$k.'.'][$field.'.']['lang.'][$this->LLkey.'.'])
							)
						) {
							$lConf['dataArray.'][$k.'.'][$field] = $this->pi_getLL($type);
						}
					}
				}

				$content.=$this->local_cObj->FORM($lConf);
			}
		}
		return $content;
	}

	/**
	 * Creates the forum display, including listing all items/a single item
	 */
	function forum_forum($theCode)	{
		$recentPosts = array();
		if ($this->conf['iconCode'])	{
			$this->treeIcons['joinBottom'] = $this->local_cObj->stdWrap($this->conf['iconCode.']['joinBottom'],$this->conf['iconCode.']['joinBottom.']);
			$this->treeIcons['join'] = $this->local_cObj->stdWrap($this->conf['iconCode.']['join'],$this->conf['iconCode.']['join.']);
			$this->treeIcons['line'] = $this->local_cObj->stdWrap($this->conf['iconCode.']['line'],$this->conf['iconCode.']['line.']);
			$this->treeIcons['blank'] = $this->local_cObj->stdWrap($this->conf['iconCode.']['blank'],$this->conf['iconCode.']['blank.']);
			$this->treeIcons['thread'] = $this->local_cObj->stdWrap($this->conf['iconCode.']['thread'],$this->conf['iconCode.']['thread.']);
			$this->treeIcons['end'] = $this->local_cObj->stdWrap($this->conf['iconCode.']['end'],$this->conf['iconCode.']['end.']);
		}

		if ($this->tt_board_uid && $theCode=='FORUM')	{
			if (!$this->allowCaching)	{
				$GLOBALS['TSFE']->set_no_cache();		// MUST set no_cache as this displays single items and not a whole page....
			}
			$lConf = $this->conf['view_thread.'];
			$templateCode = $this->local_cObj->getSubpart($this->orig_templateCode, '###TEMPLATE_THREAD###');

			if ($templateCode)	{

					// Clear
				$subpartMarkerArray = array();
				$wrappedSubpartContentArray = array();

					// Getting the specific parts of the template

				$markerArray = $this->markerObj->getColumnMarkers();

				$templateCode = $this->local_cObj->substituteMarkerArrayCached($templateCode,$markerArray,$subpartMarkerArray,$wrappedSubpartContentArray);

				$rootParent = $this->getRootParent($this->tt_board_uid);
				$wholeThread = $this->getSingleThread($rootParent['uid'],1);

				if ($lConf['single'])	{
					foreach ($wholeThread as $recentP)	{
						if ($recentP['uid']==$this->tt_board_uid)	{
							$recentPosts[]=$recentP;
							break;
						}
					}
				} else {
					$recentPosts = $wholeThread;
				}
				$nextThread = $this->getThreadRoot($this->pid_list,$rootParent);
				$prevThread = $this->getThreadRoot($this->pid_list,$rootParent,'prev');
				$subpartContent='';

					// Clear
				$markerArray = array();
				$wrappedSubpartContentArray = array();

					// Getting the specific parts of the template
				$markerArray['###FORUM_TITLE###'] = $this->local_cObj->stdWrap($GLOBALS['TSFE']->page['title'],$lConf['forum_title_stdWrap.']);

					// Link back to forum
				$this->local_cObj->setCurrentVal($this->pid);
				$wrappedSubpartContentArray['###LINK_BACK_TO_FORUM###']=$this->local_cObj->typolinkWrap($this->typolink_conf);

					// Link to next thread
				$this->local_cObj->setCurrentVal($this->pid);
				$temp_conf=$this->typolink_conf;
				if (is_array($nextThread))	{
					$temp_conf['additionalParams'].= '&tt_board_uid='.$nextThread['uid'];
					$temp_conf['useCacheHash']=$this->allowCaching;
					$temp_conf['no_cache']=!$this->allowCaching;
				}
				$wrappedSubpartContentArray['###LINK_NEXT_THREAD###']=$this->local_cObj->typolinkWrap($temp_conf);

					// Link to prev thread
				$this->local_cObj->setCurrentVal($this->pid);
				$temp_conf=$this->typolink_conf;
				if (is_array($prevThread))	{
					$temp_conf['additionalParams'].= '&tt_board_uid='.$prevThread['uid'];
					$temp_conf['useCacheHash']=$this->allowCaching;
					$temp_conf['no_cache']=!$this->allowCaching;
				}
				$wrappedSubpartContentArray['###LINK_PREV_THREAD###']=$this->local_cObj->typolinkWrap($temp_conf);

					// Link to first !!
				$this->local_cObj->setCurrentVal($this->pid);
				$temp_conf=$this->typolink_conf;
				$temp_conf['additionalParams'].= '&tt_board_uid='.$rootParent['uid'];
				$temp_conf['useCacheHash']=$this->allowCaching;
				$temp_conf['no_cache']=!$this->allowCaching;
				$wrappedSubpartContentArray['###LINK_FIRST_POST###']=$this->local_cObj->typolinkWrap($temp_conf);

					// Substitute:
				$templateCode=$this->local_cObj->substituteMarkerArrayCached($templateCode,$markerArray,array(),$wrappedSubpartContentArray);

					// Getting subpart for items:
				$postHeader=$this->getLayouts($templateCode,$this->alternativeLayouts,'POST');
				reset($recentPosts);
				$c_post=0;
				$indexedTitle='';
				foreach ($recentPosts as $recentPost)	{
					$out=$postHeader[$c_post%count($postHeader)];
					$c_post++;
					if (!$indexedTitle && trim($recentPost['subject']))	$indexedTitle=trim($recentPost['subject']);

						// Clear
					$markerArray=array();
					$wrappedSubpartContentArray=array();

					$this->local_cObj->start($recentPost);

						// Markers
					$markerArray['###POST_THREAD_CODE###'] = $this->local_cObj->stdWrap($recentPost['treeIcons'], $lConf['post_thread_code_stdWrap.']);
					$markerArray['###POST_TITLE###'] = $this->local_cObj->stdWrap($this->formatStr($recentPost['subject']), $lConf['post_title_stdWrap.']);
					$markerArray['###POST_CONTENT###'] = $this->substituteEmoticons($this->local_cObj->stdWrap($this->formatStr($recentPost['message']), $lConf['post_content_stdWrap.']));
					$markerArray['###POST_REPLIES###'] = $this->local_cObj->stdWrap($this->getNumReplies($recentPost['pid'],$recentPost['uid']), $lConf['post_replies_stdWrap.']);
					$markerArray['###POST_AUTHOR###'] = $this->local_cObj->stdWrap($this->formatStr($recentPost['author']), $lConf['post_author_stdWrap.']);
					$markerArray['###POST_AUTHOR_EMAIL###'] = $recentPost['email'];
					$markerArray['###POST_DATE###'] = $this->local_cObj->stdWrap($this->recentDate($recentPost),$this->conf['date_stdWrap.']);
					$markerArray['###POST_TIME###'] = $this->local_cObj->stdWrap($this->recentDate($recentPost),$this->conf['time_stdWrap.']);
					$markerArray['###POST_AGE###'] = $this->local_cObj->stdWrap($this->recentDate($recentPost),$this->conf['age_stdWrap.']);

						// Link to the post
					$this->local_cObj->setCurrentVal($recentPost['pid']);
					$temp_conf=$this->typolink_conf;
					$temp_conf['additionalParams'].= '&tt_board_uid='.$recentPost['uid'];
					$temp_conf['useCacheHash']=$this->allowCaching;
					$temp_conf['no_cache']=!$this->allowCaching;
					$wrappedSubpartContentArray['###LINK###']=$this->local_cObj->typolinkWrap($temp_conf);

						// Link to next thread
					$this->local_cObj->setCurrentVal($recentPost['pid']);
					$temp_conf=$this->typolink_conf;
					$temp_conf['additionalParams'].= '&tt_board_uid='.($recentPost['nextUid']?$recentPost['nextUid']:$nextThread['uid']);
					$temp_conf['useCacheHash']=$this->allowCaching;
					$temp_conf['no_cache']=!$this->allowCaching;
					$wrappedSubpartContentArray['###LINK_NEXT_POST###']=$this->local_cObj->typolinkWrap($temp_conf);

						// Link to prev thread
					$this->local_cObj->setCurrentVal($recentPost['pid']);
					$temp_conf=$this->typolink_conf;
					$temp_conf['additionalParams'].= '&tt_board_uid='.($recentPost['prevUid']?$recentPost['prevUid']:$prevThread['uid']);
					$temp_conf['useCacheHash']=$this->allowCaching;
					$temp_conf['no_cache']=!$this->allowCaching;
					$wrappedSubpartContentArray['###LINK_PREV_POST###']=$this->local_cObj->typolinkWrap($temp_conf);

						// Substitute:
					$subpartContent.=$this->local_cObj->substituteMarkerArrayCached($out,$markerArray,array(),$wrappedSubpartContentArray);
				}
				$GLOBALS['TSFE']->indexedDocTitle = $indexedTitle;
					// Substitution:
				$content.= $this->local_cObj->substituteSubpart($templateCode,'###CONTENT###',$subpartContent);
			} else {
				debug('No template code for ');
			}
		} else { // if ($this->tt_board_uid && $theCode=='FORUM')
			$continue = true;
			if ($theCode == 'THREAD_TREE')	{
				if (!$this->tt_board_uid)	{
					$continue = false;
				}
				
				$lConf = $this->conf['thread_tree.'];
			} else {
				$lConf = $this->conf['list_threads.'];
			}
			if($continue){
				$templateCode = $this->local_cObj->getSubpart($this->orig_templateCode, '###TEMPLATE_FORUM###');

				if ($templateCode)	{
						// Clear
					$subpartMarkerArray = array();
					$wrappedSubpartContentArray = array();

						// Getting the specific parts of the template

					$markerArray = $this->markerObj->getColumnMarkers();
					$markerArray['###FORUM_TITLE###'] = $this->local_cObj->stdWrap($GLOBALS['TSFE']->page['title'],$lConf['forum_title_stdWrap.']);

					$templateCode = $this->local_cObj->substituteMarkerArrayCached($templateCode,$markerArray,$subpartMarkerArray,$wrappedSubpartContentArray);

					$postHeader=$this->getLayouts($templateCode,$this->alternativeLayouts,'POST');
						// Template code used if tt_board_uid matches...
					$postHeader_active = $this->getLayouts($templateCode,1,'POST_ACTIVE');
					$subpartContent='';

					if ($theCode=='THREAD_TREE')	{
						$rootParent = $this->getRootParent($this->tt_board_uid);
						$recentPosts = $this->getSingleThread($rootParent['uid'],1);
					} else {
						$recentPosts = $this->getThreads($this->pid_list,$this->conf['tree'], $lConf['thread_limit']?$lConf['thread_limit']:'50', t3lib_div::_GP('tt_board_sword'));
					}
					reset($recentPosts);
					$c_post=0;
					$subpartArray=array();
					foreach ($recentPosts as $k => $recentPost)	{
						$GLOBALS['TT']->push('/Post/');
						$out=$postHeader[$c_post%count($postHeader)];
						if ($recentPost['uid']==$this->tt_board_uid && $postHeader_active[0])	{
							$out = $postHeader_active[0];
						}
						$c_post++;

						$this->local_cObj->start($recentPost);

							// Clear
						$markerArray=array();
						$wrappedSubpartContentArray=array();

							// Markers
						$GLOBALS['TT']->push('/postMarkers/');
						$markerArray['###POST_THREAD_CODE###'] = $this->local_cObj->stdWrap($recentPost['treeIcons'], $lConf['post_thread_code_stdWrap.']);
						$markerArray['###POST_TITLE###'] = $this->local_cObj->stdWrap($this->formatStr($recentPost['subject']), $lConf['post_title_stdWrap.']);
						$markerArray['###POST_CONTENT###'] = $this->substituteEmoticons($this->local_cObj->stdWrap($this->formatStr($recentPost['message']), $lConf['post_content_stdWrap.']));
						$markerArray['###POST_REPLIES###'] = $this->local_cObj->stdWrap($this->getNumReplies($recentPost['pid'],$recentPost['uid']), $lConf['post_replies_stdWrap.']);
						$markerArray['###POST_AUTHOR###'] = $this->local_cObj->stdWrap($this->formatStr($recentPost['author']), $lConf['post_author_stdWrap.']);
						$markerArray['###POST_DATE###'] = $this->local_cObj->stdWrap($this->recentDate($recentPost),$this->conf['date_stdWrap.']);
						$markerArray['###POST_TIME###'] = $this->local_cObj->stdWrap($this->recentDate($recentPost),$this->conf['time_stdWrap.']);
						$markerArray['###POST_AGE###'] = $this->local_cObj->stdWrap($this->recentDate($recentPost),$this->conf['age_stdWrap.']);

							// Link to the post
						$this->local_cObj->setCurrentVal($recentPost['pid']);
						$temp_conf=$this->typolink_conf;
						$temp_conf['additionalParams'].= '&tt_board_uid='.$recentPost['uid'];
						$temp_conf['useCacheHash']=$this->allowCaching;
						$temp_conf['no_cache']=!$this->allowCaching;
						$wrappedSubpartContentArray['###LINK###']=$this->local_cObj->typolinkWrap($temp_conf);
						$GLOBALS['TT']->pull();

							// Last post processing:
						$GLOBALS['TT']->push('/last post info/');
						$lastPostInfo = $this->getLastPostInThread($recentPost['pid'],$recentPost['uid']);
						$GLOBALS['TT']->pull();
						if (!$lastPostInfo)	$lastPostInfo=$recentPost;

						$this->local_cObj->start($lastPostInfo);

						$GLOBALS['TT']->push('/lastPostMarkers/');
						$recentDate = $this->recentDate($lastPostInfo);
						$markerArray['###LAST_POST_DATE###']=$this->local_cObj->stdWrap($recentDate,$this->conf['date_stdWrap.']);
						$markerArray['###LAST_POST_TIME###']=$this->local_cObj->stdWrap($recentDate,$this->conf['time_stdWrap.']);
						$markerArray['###LAST_POST_AGE###']=$this->local_cObj->stdWrap($recentDate,$this->conf['age_stdWrap.']);
						$markerArray['###LAST_POST_AUTHOR###']=$this->local_cObj->stdWrap($this->formatStr($lastPostInfo['author']), $lConf['last_post_author_stdWrap.']);

							// Link to the last post
						$this->local_cObj->setCurrentVal($lastPostInfo['pid']);
						$temp_conf=$this->typolink_conf;
						$temp_conf['additionalParams'].= '&tt_board_uid='.$lastPostInfo['uid'];
						$temp_conf['useCacheHash']=$this->allowCaching;
						$temp_conf['no_cache']=!$this->allowCaching;
						$wrappedSubpartContentArray['###LINK_LAST_POST###']=$this->local_cObj->typolinkWrap($temp_conf);
						$GLOBALS['TT']->pull();

							// Substitute:
						$subpartArray[$recentDate.sprintf('%010d',$recentPost['uid'])]=$this->local_cObj->substituteMarkerArrayCached($out,$markerArray,array(),$wrappedSubpartContentArray);
						$GLOBALS['TT']->pull();
					}
					if (!$this->conf['tree'])	{
						krsort($subpartArray);
					}

						// Substitution:
					$markerArray=array();
					$subpartContentArray=array();
						// Fill in array
					$markerArray['###SEARCH_WORD###']=$GLOBALS['TSFE']->no_cache ? t3lib_div::_GP('tt_board_sword') : '';		// Setting search words in field if cache is disabled.
						// Set FORM_URL
					$this->local_cObj->setCurrentVal($GLOBALS['TSFE']->id);
					$temp_conf=$this->typolink_conf;
					$temp_conf['no_cache'] = 1;
					$markerArray['###FORM_URL###']=$this->local_cObj->typoLink_URL($temp_conf);
					$subpartContent = implode('',$subpartArray);

						// Substitute CONTENT-subpart
					$subpartContentArray['###CONTENT###']=$subpartContent;
					$content.= $this->local_cObj->substituteMarkerArrayCached($templateCode,$markerArray,$subpartContentArray);

				} else {
					debug('No template code for ');
				}
			} // if($continue){
		}
		return $content;
	} // forum_forum

	/**
	 * Get a record tree of forum items
	 */
	function getRecordTree($theRows,$parent,$pid,$treeIcons='') {
		$where = 'pid='.intval($pid).' AND parent='.intval($parent).$this->enableFields;
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tt_board', $where, '', $this->orderBy());
		$c = 0;
		$rc = $GLOBALS['TYPO3_DB']->sql_num_rows($res);

		$theRows[count($theRows)-1]['treeIcons'].= $rc ? $this->treeIcons['thread'] : $this->treeIcons['end'];

		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
			$c++;
			$row['treeIcons'] = $treeIcons.($rc==$c ? $this->treeIcons['joinBottom'] : $this->treeIcons['join']);
				// prev/next item:
			$theRows[count($theRows)-1]['nextUid'] = $row['uid'];
			$row['prevUid'] = $theRows[count($theRows)-1]['uid'];

			$theRows[] = $row;
				// get the branch
			$theRows = $this->getRecordTree($theRows,$row['uid'],$row['pid'],$treeIcons.($rc==$c ? $this->treeIcons['blank'] : $this->treeIcons['line']));
		}
		return $theRows;
	}

	/**
	 * Get subpages
	 *
	 * This function returns an array a pagerecords from the page-uid's in the pid_list supplied.
	 * Excludes pages, that would normally not enter a regular menu. That means hidden, timed or deleted pages + pages with another doktype than 'standard' or 'advanced'
	 */
	function getPagesInPage($pid_list)	{
		$thePids = t3lib_div::intExplode(',',$pid_list);
		$theMenu = array();
		while(list(,$p_uid)=each($thePids))	{
			$theMenu = array_merge($theMenu, $GLOBALS['TSFE']->sys_page->getMenu($p_uid));
		}
			// Exclude pages not of doktype 'Standard' or 'Advanced'
		reset($theMenu);
		while(list($key,$data)=each($theMenu))	{
			if (!t3lib_div::inList($GLOBALS['TYPO3_CONF_VARS']['FE']['content_doktypes'],$data['doktype']))	{unset($theMenu[$key]);} // All pages including pages 'not in menu'
		}
		return $theMenu;
	}

	/**
	 * Returns number of post in a forum.
	 */
	function getNumPosts($pid)	{
		$where = 'pid IN ('.$pid.')'.$this->enableFields;
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('count(*)', 'tt_board', $where);
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_row($res);
		return $row[0];
	}

	/**
	 * Returns number of threads.
	 */
	function getNumThreads($pid)	{
		$where = 'pid IN ('.$pid.') AND parent=0'.$this->enableFields;
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('count(*)', 'tt_board', $where);
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_row($res);
		return $row[0];
	}

	/**
	 * Returns number of replies.
	 */
	function getNumReplies($pid,$uid)	{
		$where = 'pid IN ('.$pid.') AND parent='.intval($uid).$this->enableFields;
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('count(*)', 'tt_board', $where);
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_row($res);
		return $row[0];
	}

	/**
	 * Returns last post.
	 */
	function getLastPost($pid)	{
		$where = 'pid IN ('.$pid.')'.$this->enableFields;
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tt_board', $where, '', $this->orderBy('DESC'), '1');
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		return $row;
	}

	/**
	 * Returns last post in thread.
	 */
	function getLastPostInThread($pid,$uid)	{
		$where = 'pid IN ('.$pid.') AND parent='.$uid.$this->enableFields;
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tt_board', $where, '', $this->orderBy('DESC'), '1');
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		return $row;
	}

	/**
	 * Most recent posts.
	 *
	 * Returns an array with records
	 */
	function getMostRecentPosts($pid,$number)	{
		$where = 'pid IN ('.$pid.')'.$this->enableFields;
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tt_board', $where, '', $this->orderBy('DESC'), $number);
		$out = array();
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Returns an array with threads
	 */
	function getThreads($pid,$descend=0,$limit=100,$searchWord)	{
		$outArray=array();
		if ($searchWord)	{
			$where = $this->cObj->searchWhere($searchWord, $this->searchFieldList, 'tt_board');
			$where = 'pid IN ('.$pid.') '.$where.$this->enableFields;
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tt_board', $where, '', $this->orderBy('DESC'), intval($limit));
			$set = array();
			while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				$rootRow = $this->getRootParent($row['uid']);
				if (is_array($rootRow) && !isset($set[$rootRow['uid']]))	{
					$set[$rootRow['uid']] = 1;
					$outArray[] = $rootRow;
					if ($descend)	{
						$outArray = $this->getRecordTree($outArray,$rootRow['uid'],$rootRow['pid']);
					}
				}
			}
		} else {
			$where = 'pid IN ('.$pid.') AND parent=0'.$this->enableFields;
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tt_board', $where, '', $this->orderBy('DESC'), intval($limit));
			while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				$outArray[] = $row;
				if ($descend)	{
					$outArray = $this->getRecordTree($outArray,$row['uid'],$row['pid']);
				}
			}
		}
		return $outArray;
	}

	/**
	 * Returns records in a thread
	 */
	function getSingleThread($uid,$decend=0)	{
		$hash = md5($uid.'|'.$decend);
		if ($this->cache_thread[$hash])	{return $this->cache_thread[$hash]; debug('!');}

		$out=array();
		if ($uid)	{
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tt_board', 'uid='.$uid.$this->enableFields);
			if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				$out[] = $row;
				if ($decend)	{
					$out = $this->getRecordTree($out,$row['uid'],$row['pid']);
				}
			}
		}
		return $out;
	}

	/**
	 * Get root parent of a tt_board record.
	 */
	function getRootParent($uid,$limit=99)	{
		if ($limit > 0)	{
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tt_board', 'uid='.$uid.$this->enableFields);
			if($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				if ($row['parent'])	{
					$tmpRow = $this->getRootParent($row['parent'],$limit-1);
					if ($tmpRow)	{
						$row = $tmpRow;
					}
				}
			}
		}
		return $row;
	}

	/**
	 * Returns next or prev thread in a tree
	 */
	function getThreadRoot($pid,$rootParent,$type='next')	{
		global $TYPO3_DB;

		$datePart = ' AND crdate'.($type!='next'?'>':'<').intval($rootParent['crdate']);
		$where = 'pid IN ('.$pid.') AND parent=0'.$datePart.$this->enableFields;
		$res = $TYPO3_DB->exec_SELECTquery('*', 'tt_board', $where, '', $this->orderBy($type!='next'?'':'DESC'));
		return $TYPO3_DB->sql_fetch_assoc($res);
	}

	/**
	 * Format string with nl2br and htmlspecialchars()
	 */
	function formatStr($str)	{
		$rc = '';
		if (!$this->dontParseContent)	{
			$rc = nl2br(htmlspecialchars($str));
		} else {
			$rc = $str;
		}
		return $rc;
	}

	/**
	 * Emoticons substitution
	 */
	function substituteEmoticons($str)	{
		if ($this->emoticons)	{
			reset($this->emoticonsSubst);
			while(list($source,$dest)=each($this->emoticonsSubst))	{
				$str = str_replace($source, str_replace('{}', $this->emoticonsPath.$dest, $this->emoticonsTag), $str);
			}
		}
		return $str;
	}

	/**
	 * Returns alternating layouts
	 */
	function getLayouts($templateCode,$alternativeLayouts,$marker)	{
		$out=array();
		for($a=0;$a<$alternativeLayouts;$a++)	{
			$m= '###'.$marker.($a?'_'.$a:'').'###';
			if(strstr($templateCode,$m))	{
				$out[]=$GLOBALS['TSFE']->cObj->getSubpart($templateCode, $m);
			} else {
				break;
			}
		}
		return $out;
	}

	/**
	 * Returns a message, formatted
	 */
	function outMessage($string,$content='')	{
		$msg= '
		<HR>
		<h3>'.$string.'</h3>
		'.$content.'
		<HR>
		';

		return $msg;
	}

	/**
	 * Returns ORDER BY field
	 */
	function orderBy($desc='')	{
		$rc = 'crdate '.$desc;
		return $rc;
	}

	/**
	 * Returns recent date from a tt_board record
	 */
	function recentDate($rec)	{
		return $rec['tstamp'];
	}

}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/tt_board/lib/class.tx_ttboard_pibase.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/tt_board/lib/class.tx_ttboard_pibase.php']);
}

?>
