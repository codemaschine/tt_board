<?php

namespace JambageCom\TtBoard\Domain;

/***************************************************************
*  Copyright notice
*
*  (c) 2020 Kasper Skårhøj <kasperYYYY@typo3.com>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
// *  the Free Software Foundation; either version 2 of the License, or
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
 * Function library for a forum/board in tree or list style
 *
 * TypoScript config:
 * - See static_template 'plugin.tt_board_tree' and plugin.tt_board_list
 * - See TS_ref.pdf
 *
 * @author	Kasper Skårhøj  <kasperYYYY@typo3.com>
 * @author	Franz Holzinger <franz@ttproducts.de>
 */

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;


use JambageCom\TtBoard\Constants\TreeMark;
use JambageCom\TtBoard\Domain\QueryParameter;


class TtBoard implements \TYPO3\CMS\Core\SingletonInterface
{
    public $enableFields = '';		// The enablefields of the tt_board table.
    public $searchFieldList = 'author,email,subject,message';
    protected $tablename = 'tt_board';


    public function init ()
    {
        $enableFields = \JambageCom\Div2007\Utility\TableUtility::enableFields($this->tablename);
        $this->setEnableFields($enableFields);
    }

    public function getTablename ()
    {
        return $this->tablename;
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder ()
    {
        $result = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->getTablename());
        return $result;
    }

    public function setEnableFields ($value)
    {
        $this->enableFields = $value;
    }

    public function getEnableFields ()
    {
        return $this->enableFields;
    }

    /**
     * Returns the reference to an external table to which the forum belongs
    *  @return	QueryParameter ... $andWhereEqualsArray array of QueryParameter
     */
    public function getWhereRef ($ref)
    {
        $result = null;

        if ($ref != '') {
            $result =
                GeneralUtility::makeInstance(
                    QueryParameter::class,
                    QueryParameter::CLAUSE_AND_WHERE,
                    $this->getTablename(),
                    'reference',
                    $ref,
                    \PDO::PARAM_STR,
                    QueryParameter::COMP_EQUAL
                );
        }
        return $result;
    }

    public function addQueryParameter (
        QueryBuilder &$queryBuilder,
        &$whereCount,
        QueryParameter $queryParameter
    )
    {
        if (empty($queryParameter) || !is_int($whereCount)) {
            return false;
        }

        $field = $queryParameter->field;
        if ($queryParameter->tablename != '') {
            $field = $queryParameter->tablename . '.' . $field;
        }
        $parameter =
            $queryBuilder->createNamedParameter(
                $queryParameter->value,
                $queryParameter->type
            );

        switch ($queryParameter->clause) {
            case QueryParameter::CLAUSE_AND_WHERE:

                $expression = '';

                switch ($queryParameter->comparator) {
                    case QueryParameter::COMP_EQUAL:
                        $field = $queryParameter->field;
                        if ($queryParameter->tablename != '') {
                            $field = $queryParameter->tablename . '.' . $field;
                        }
                        $expression = $queryBuilder->expr()->eq(
                            $field,
                            $parameter
                        );
                        break;
                    default:
                        throw new \RuntimeException(TT_BOARD_EXT . ': wrong comparator in parameter field "' . $queryParameter->field . '"');
                    break;
                }

                if ($whereCount > 0) {
                    $queryBuilder->andWhere(
                        $expression
                    );
                } else {
                    $queryBuilder->where(
                        $expression
                    );
                }
                $whereCount++;
                break;

            default:
                break;
        }

        return true;
    }

    /**
    * Checks if posting is allowed to user
    */
    static public function isAllowed ($memberOfGroups)
    {
        $allowed = false;

        if (
            $memberOfGroups != '' &&
            $memberOfGroups != '{$plugin.tt_board.memberOfGroups}' &&
            $memberOfGroups != '0'
        ) {
            if (is_array($GLOBALS['TSFE']->fe_user->user)) {
                $requestGroupArray =
                    GeneralUtility::trimExplode(
                        ',',
                        $memberOfGroups
                    );
                $usergroupArray = explode(',', $GLOBALS['TSFE']->fe_user->user['usergroup']);
                $fitArray = array_intersect($requestGroupArray, $usergroupArray);
                if (count($fitArray)) {
                    $allowed = true;
                }
            }
        } else {
            $allowed = true;
        }

        return $allowed;
    }

    /**
    * Get subpages
    *
    * This function returns an array a pagerecords from the page-uid's in the pid_list supplied.
    * Excludes pages, that would normally not enter a regular menu. That means hidden, timed or deleted pages and pages with another doktype than 'standard' or 'advanced'
    */
    static public function getPagesInPage ($pid_list)
    {
        $result = [];
        $thePids = GeneralUtility::intExplode(',', $pid_list);
        $pageRows = [];
        foreach($thePids as $pid) {
            $menuRows = $GLOBALS['TSFE']->sys_page->getMenu($pid);

                // avoid the insertion of duplicate page rows
            foreach ($menuRows as $menuRow) {
                $uid = $menuRow['uid'];
                if (!isset($pageRows[$uid])) {
                    $pageRows[$uid] = $menuRow;
                }
            }
        }

            // Exclude pages not of doktype 'Standard' or 'Advanced'
        foreach($pageRows as $pageRow) {
            if (
                !isset($GLOBALS['TYPO3_CONF_VARS']['FE']['content_doktypes']) || // removed since TYPO3 9.5
                GeneralUtility::inList(
                    $GLOBALS['TYPO3_CONF_VARS']['FE']['content_doktypes'],
                    $pageRow['doktype']
                )
            ) {
                $result[] = $pageRow;
            } // All pages including pages 'not in menu'
        }

        return $result;
    }

    /**
     * Returns number of post in a forum.
     * @param string ... $pidList comma separated list of page ids.
     * @param array  ... $andWhereEqualsArray array of QueryParameter for equation comparisons
     * @param where ... $where
     */
    public function getNumPosts ($pidList, array $queryParameters = [], QueryBuilder $where = null)
    {
        $pageIds =  GeneralUtility::intExplode(',', $pidList, true);
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->setRestrictions(GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer::class)
        );

        $field = $this->getTablename() . '.pid';

        $queryBuilder
            ->count('*')
            ->from($this->getTablename())
            ->where(
                $queryBuilder->expr()->in(
                    $field,
                    $queryBuilder->createNamedParameter(
                        $pageIds,
                        Connection::PARAM_INT_ARRAY
                    )
                )
            );

        $whereCount = 1;
        foreach ($queryParameters as $queryParameter) {
            if (!empty($queryParameter)) {
                $this->addQueryParameter($queryBuilder, $whereCount, $queryParameter);
            }
        }

        if (is_object($where) && !empty($where)) {
            $queryBuilder->andWhere($where);
        }

        $result =
            $queryBuilder
                ->execute()
                ->fetchColumn(0);

        return $result;
    }

    /**
    * Returns number of threads.
     * @param string   ... $pid page id
    *  @param array    ... $andWhereEqualsArray array of QueryParameter
     */
    public function getNumThreads ($pid, $ref = '', $searchWords = 0)
    {
        $count = 0;
        $whereRef = $this->getWhereRef($ref);
        $queryParameters = [];
        $queryParameters[] = $whereRef;

        if ($searchWords) {
            $where = \JambageCom\Div2007\Database\QueryBuilderApi::searchWhere(
                $searchWords,
                $this->searchFieldList,
                $this->getTablename()
            );
            $count = $this->getNumPosts($pid, $queryParameters, $where);
        } else {
            $queryParameter =
                GeneralUtility::makeInstance(
                    QueryParameter::class,
                    QueryParameter::CLAUSE_AND_WHERE,
                    $this->getTablename(),
                    'parent',
                    0,
                    \PDO::PARAM_INT,
                    QueryParameter::COMP_EQUAL
                );
            $queryParameters[] = $queryParameter;
            $count = $this->getNumPosts($pid, $queryParameters);
        }

        return $count;
    }

    /**
    * Returns number of replies.
    */
    public function getNumReplies ($pid, $uid)
    {
        $queryParameters = [];
        $queryParameter =
            GeneralUtility::makeInstance(
                QueryParameter::class,
                QueryParameter::CLAUSE_AND_WHERE,
                $this->getTablename(),
                'parent',
                intval($uid), \PDO::PARAM_INT,
                QueryParameter::COMP_EQUAL
            );
        $queryParameters[] = $queryParameter;
        $count = $this->getNumPosts($pid, $queryParameters);

        return $count;
    }

    /**
    * Returns last post.
    */
    public function getLastPost ($pidList)
    {
        $result = false;
        $pageIds =  GeneralUtility::intExplode(',', $pidList, true);
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->setRestrictions(GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer::class));
        $field = 'pid';

        $rows = $queryBuilder
            ->select('*')
            ->from($this->getTablename())
            ->where(
                $queryBuilder->expr()->in(
                    $field,
                    $queryBuilder->createNamedParameter(
                        $pageIds,
                        Connection::PARAM_INT_ARRAY
                    )
                )
            )
            ->orderBy('crdate', 'DESC')
            ->setMaxResults(1)
            ->execute()
            ->fetchAll();

        if (is_array($rows)) {
            $result = $rows['0'];
        }
        return $result;
    }

    /**
    * Returns last post in thread.
    */
    public function getLastPostInThread ($pidList, $uid, $ref)
    {
        $result = false;
        $pageIds =  GeneralUtility::intExplode(',', $pidList, true);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->setRestrictions(GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer::class));

        $field = 'pid';

        $queryBuilder
            ->select('*')
            ->from($this->getTablename())
            ->where(
                $queryBuilder->expr()->in(
                    $field,
                    $queryBuilder->createNamedParameter(
                        $pageIds,
                        Connection::PARAM_INT_ARRAY
                    )
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'parent',
                    $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)
                )
            );

        $whereRef = $this->getWhereRef($ref);
        $whereCount = 2;
        if (!empty($whereRef)) {
            $this->addQueryParameter($queryBuilder, $whereCount, $whereRef);
        }

        $rows = $queryBuilder
            ->orderBy('crdate', 'DESC')
            ->setMaxResults(1)
            ->execute()
            ->fetchAll();

        if (is_array($rows)) {
            $result = $rows['0'];
        }

        return $result;
    }

    /**
    * Returns current post in thread.
    */
    public function getCurrentPost ($uid, $ref)
    {
        $result = false;
        $rows = null;

        if ($uid || $ref != '') {
            $queryBuilder = $this->getQueryBuilder();
            $queryBuilder->setRestrictions(GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer::class));
            $whereCount = 0;

            $queryBuilder
                ->select('*')
                ->from($this->getTablename());

            if ($uid) {
                $queryBuilder->where(
                    $queryBuilder->expr()->eq(
                        $this->getTablename() . '.uid',
                        $queryBuilder->createNamedParameter(
                            $uid,
                            \PDO::PARAM_INT
                        )
                    )
                );
                $whereCount++;
            }

            $whereRef = $this->getWhereRef($ref);
            if (!empty($whereRef)) {
                $this->addQueryParameter($queryBuilder, $whereCount, $whereRef);
            }

            $rows = $queryBuilder
                ->setMaxResults(1)
                ->execute()
                ->fetchAll();
        }

        if (is_array($rows)) {
            $result = $rows['0'];
        }

        return $result;
    }

    /**
    * Most recent posts.
    *
    * Returns an array with records
    */
    public function getMostRecentPosts ($pidList, $number, $days = 300)
    {
        $pageIds =  GeneralUtility::intExplode(',', $pidList, true);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->setRestrictions(GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer::class));

        $field = 'pid';
        $queryBuilder
            ->select('*')
            ->from($this->getTablename())
            ->where(
                $queryBuilder->expr()->in(
                    $field,
                    $queryBuilder->createNamedParameter(
                        $pageIds,
                        Connection::PARAM_INT_ARRAY
                    )
                )
            );

        if ($days) {
            $seconds = time() - 86400 * intval(trim($days));
            $queryBuilder->andWhere(
                $queryBuilder->expr()->gte(
                    'crdate',
                    $queryBuilder->createNamedParameter($seconds, \PDO::PARAM_INT)
                )
            );
        }

        $result = $queryBuilder
            ->orderBy('crdate', 'DESC')
            ->setMaxResults($number)
            ->execute()
            ->fetchAll();

        return $result;
    }

    /**
    * Get root parent of a tt_board record by uid or reference.
    */
    public function getRootParent ($uid, $ref = '', $limit = 99, $calllevel = 0)
    {
        $result = false;
        $error = false;
        $row = null;
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->setRestrictions(GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer::class));

        if ($uid) {
            $field = 'uid';
            $value = $uid;
            $type = \PDO::PARAM_INT;
        } else if ($ref != '') {
            $field = 'reference';
            $value = $ref;
            $type = \PDO::PARAM_STR;
        } else {
            return false;
        }

        if (
            $limit > 0
        ) {
            $rows = $queryBuilder
                ->select('*')
                ->from($this->getTablename())
                ->where(
                    $queryBuilder->expr()->eq(
                        $field,
                        $queryBuilder->createNamedParameter($uid, $type)
                    )
                )
                ->setMaxResults(1)
                ->execute()
                ->fetchAll();

            if (is_array($rows)) {
                $row = $rows['0'];
            }

            if (!empty($row) && is_array($row)) {
                if ($row['parent']) {
                    $tmpRow =
                        $this->getRootParent(
                            $row['parent'],
                            '',
                            $limit - 1,
                            $calllevel + 1
                        );

                    if ($tmpRow) {
                        $result = $tmpRow;
                    }
                } else if (
                    $calllevel > 0 ||
                    $ref != ''
                ) {
                    $result = $row;
                }
            }
        }

        return $result;
    }

    /**
    * Returns next or prev thread in a tree
    */
    public function getThreadRoot ($pidList, $crdate, $type = 'next')
    {
        $result = null;
        $pageIds =  GeneralUtility::intExplode(',', $pidList, true);
        $crdate = intval($crdate);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->setRestrictions(GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer::class));

        $field = 'pid';
        $queryBuilder
            ->select('*')
            ->from($this->getTablename())
            ->where(
                $queryBuilder->expr()->in(
                    $field,
                    $queryBuilder->createNamedParameter(
                        $pageIds,
                        Connection::PARAM_INT_ARRAY
                    )
                )
            );

        if ($type != 'next') {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->gt(
                    'crdate',
                    $queryBuilder->createNamedParameter(
                        $crdate,
                        \PDO::PARAM_INT
                    )
                )
            );
        } else {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->lt(
                    'crdate',
                    $queryBuilder->createNamedParameter(
                        $crdate,
                        \PDO::PARAM_INT
                    )
                )
            );
        }

        $queryBuilder->andWhere(
            $queryBuilder->expr()->eq(
                'parent',
                $queryBuilder->createNamedParameter(
                    0,
                    \PDO::PARAM_INT
                )
            )
        );

        $rows = $queryBuilder
            ->orderBy('crdate', ($type != 'next' ? '' : 'DESC'))
            ->setMaxResults(1)
            ->execute()
            ->fetchAll();

        if (is_array($rows)) {
            $result = $rows['0'];
        }

        return $result;
    }

    /**
    * Returns records in a thread
    */
    public function getSingleThread (
        $uid,
        $ref,
        $descend = 0,
        $recentAtLast = false
    )
    {
        $result = false;
        $row = null;
        $outArray = [];
        if ($uid) {
            $hash = md5($uid . '|' . $ref . '|' . $descend);
            if ($this->cache_thread[$hash]) {
                return $this->cache_thread[$hash];
            }

            $queryBuilder = $this->getQueryBuilder();
            $queryBuilder->setRestrictions(GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer::class));

            $field = 'uid';
            $queryBuilder
                ->select('*')
                ->from($this->getTablename())
                ->where(
                    $queryBuilder->expr()->eq(
                        $field,
                        $queryBuilder->createNamedParameter(
                            $uid,
                            \PDO::PARAM_INT
                        )
                    )
                );

            $whereCount = 1;
            $whereRef = $this->getWhereRef($ref);
            if (!empty($whereRef)) {
                $this->addQueryParameter($queryBuilder, $whereCount, $whereRef);
            }

            $rows = $queryBuilder
                ->setMaxResults(1)
                ->execute()
                ->fetchAll();

            if (is_array($rows)) {
                $row = $rows['0'];
            }

            if (!empty($row)) {

                $outArray[$row['uid']] = $row;
                if ($descend) {
                    $this->getRecordTree(
                        $outArray,
                        $row['uid'],
                        $row['pid'],
                        $ref,
                        $recentAtLast
                    );
                }
            }
        }
        return $outArray;
    }

    /**
    * Returns an array with threads
    */
    public function getThreads (
        $pidList,
        $ref,
        $descend = 0,
        $limit = 100,
        $offset = 0,
        $searchWords = 0,
        $recentAtLast = true
    )
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->setRestrictions(GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer::class));
        $pageIds =  GeneralUtility::intExplode(',', $pidList, true);

        $outArray = [];
        $whereRef = $this->getWhereRef($ref);
        $limitString = '';
        if ($limit) {
            $limitString = intval($limit);
        }

        $offset = intval($offset);
        $field = 'pid';
        $queryBuilder
            ->select('*')
            ->from($this->getTablename())
            ->where(
                $queryBuilder->expr()->in(
                    $field,
                    $queryBuilder->createNamedParameter(
                        $pageIds,
                        Connection::PARAM_INT_ARRAY
                    )
                )
            );
        $whereCount = 1;
        if (!empty($whereRef)) {
            $this->addQueryParameter($queryBuilder, $whereCount, $whereRef);
        }

        if ($searchWords) {
            $where = \JambageCom\Div2007\Database\QueryBuilderApi::searchWhere(
                $searchWords,
                $this->searchFieldList,
                $this->getTablename()
            );

            if (is_object($where) && !empty($where)) {
                $queryBuilder->andWhere($where);
                $whereCount++;
            }

            $statement = $queryBuilder
                ->orderBy('crdate', 'DESC');

            if ($offset) {
                $queryBuilder
                    ->setFirstResult($offset);
            }
            if ($limitString) {
                $queryBuilder
                    ->setMaxResults($limitString);
            }
            $statement = $queryBuilder
                ->execute();

            $set = [];
            while ($row = $statement->fetch()) {
                $rootRow = $this->getRootParent($row['uid']);
                if (!$rootRow) {
                    $rootRow = $row;
                }

                if (is_array($rootRow) && !isset($set[$rootRow['uid']])) {
                    $set[$rootRow['uid']] = 1;
                    $outArray[$rootRow['uid']] = $rootRow;

                    if ($descend) {
                        $this->getRecordTree(
                            $outArray,
                            $rootRow['uid'],
                            $rootRow['pid'],
                            $ref,
                            $recentAtLast
                        );
                    }
                }
            }
        } else {
            $field = 'parent';
            $parent = 0;
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq(
                    'parent',
                    $queryBuilder->createNamedParameter($parent, \PDO::PARAM_INT)
                )
            );

            $queryBuilder->orderBy('crdate', 'DESC');

            if ($offset) {
                $queryBuilder
                    ->setFirstResult($offset);
            }
            if ($limitString) {
                $queryBuilder
                    ->setMaxResults($limitString);
            }
            $statement = $queryBuilder
                ->execute();

            while ($row = $statement->fetch()) {
                $outArray[$row['uid']] = $row;

                if ($descend) {
                    $this->getRecordTree(
                        $outArray,
                        $row['uid'],
                        $row['pid'],
                        $ref,
                        $recentAtLast
                    );
                }
            }
        }
        return $outArray;
    }

    /**
    * Get a record tree of forum items
    */
    public function getRecordTree (
        &$theRows,
        $parent,
        $pidList,
        $ref,
        $recentAtLast = true,
        $treeMarks = ''
    )
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->setRestrictions(GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer::class));
        $pageIds =  GeneralUtility::intExplode(',', $pidList, true);

        if ($treeMarks != '') {
            $treeMarks .= ',';
        }
        $whereRef = $this->getWhereRef($ref);

        $field = 'pid';
        $queryBuilder
            ->count('*')
            ->from($this->getTablename())
            ->where(
                $queryBuilder->expr()->in(
                    $field,
                    $queryBuilder->createNamedParameter(
                        $pageIds,
                        Connection::PARAM_INT_ARRAY
                    )
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'parent',
                    $queryBuilder->createNamedParameter($parent, \PDO::PARAM_INT)
                )
            );

        $whereCount = 1;
        if (!empty($whereRef)) {
            $this->addQueryParameter($queryBuilder, $whereCount, $whereRef);
        }

        $numberRows = $counter = 0;
        $numberRows = $queryBuilder
            ->execute()
            ->fetchColumn(0);

        $queryBuilder
            ->select('*');
        $statement = $queryBuilder
            ->orderBy('crdate')
            ->execute();
        $prevUid = end(array_keys($theRows));

        if ($theRows[$prevUid]['treeMarks'] != '') {
            $theRows[$prevUid]['treeMarks'] .= ',';
        }

        $theRows[$prevUid]['treeMarks'] .=
            (
                $numberRows ?
                TreeMark::THREAD :
                TreeMark::END
            );

        while ($row = $statement->fetch()) {
            $counter++;
            $uid = $row['uid'];

            // check for a loop
            if (isset($theRows[$uid])) {
                break;
            }

            $row['treeMarks'] =
                $treeMarks . (
                    $numberRows == $counter ?
                        TreeMark::JOIN_BOTTOM :
                        TreeMark::JOIN
                );

            // prev/next item:
            $theRows[$prevUid]['nextUid'] = $uid;
            $row['prevUid'] = $theRows[$prevUid]['uid'];
            $theRows[$uid] = $row;

                // get the branch
            $this->getRecordTree(
                $theRows,
                $uid,
                $row['pid'],
                $ref,
                $recentAtLast,
                $treeMarks . ($numberRows == $counter ? TreeMark::BLANK : TreeMark::LINE)
            );
            $prevUid = $uid;
        }
    }

    /**
    * Returns ORDER BY field
    */
    static public function orderBy ($desc = '', $stringFormat = true)
    {
        $result = [];
        $result[] = 'crdate ';
        if (in_array($desc, ['ASC', 'DESC'])) {
            $result[] = $desc;
        }
        if ($stringFormat) {
            $result = implode(' ', $result);
        }
        return $result;
    }

    /**
    * Returns recent date from a tt_board record
    */
    static public function recentDate ($row)
    {
        return $row['tstamp'];
    }
}
