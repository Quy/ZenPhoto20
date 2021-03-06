<?php

function newArticle($titlelink, $allowCreate = NULL) {
	return new Article($titlelink, $allowCreate);
}

/**
 * zenpage news class
 *
 * @author Malte Müller (acrylian)
 * @package plugins
 * @subpackage zenpage
 */
if (!defined('NEWS_POSITION_NORMAL')) { // No idea why this is needed, but clones get already defined errors.
	define('NEWS_POSITION_NORMAL', 0);
	define('NEWS_POSITION_STICKY', 1);
	define('NEWS_POSITION_STICK_TO_TOP', 9);
}

class Article extends CMSItems {

	var $manage_rights = MANAGE_ALL_NEWS_RIGHTS;
	var $manage_some_rights = ZENPAGE_NEWS_RIGHTS;
	var $access_rights = ALL_NEWS_RIGHTS;
	var $categories = NULL;
	var $index = NULL;

	function __construct($titlelink, $allowCreate = NULL) {
		if (is_array($titlelink)) {
			$titlelink = $titlelink['titlelink'];
		}
		$new = $this->instantiate('news', array('titlelink' => $titlelink), 'titlelink', true, empty($titlelink), $allowCreate);
		if ($new || empty($titlelink)) {
			$this->setPermalink(1);
			$this->setDateTime(date('Y-m-d H:i:s'));
		}
		$this->exists = $this->loaded;
	}

	/**
	 * Gets the categories assigned to an news article
	 *
	 * @param int $article_id ID od the article
	 * @return array
	 */
	function getCategories() {
		if (is_null($this->categories)) {
			$this->categories = query_full_array("SELECT * FROM " . prefix('news_categories') . " as cat," . prefix('news2cat') . " as newscat WHERE newscat.cat_id = cat.id AND newscat.news_id = " . $this->getID() . " ORDER BY cat.titlelink", false, 'title');
			if (!$this->categories) {
				$this->categories = array();
			}
		}
		return $this->categories;
	}

	function setCategories($categories) {
		$result = query('DELETE FROM ' . prefix('news2cat') . ' WHERE `news_id`=' . $this->getID());
		$result = query_full_array("SELECT * FROM " . prefix('news_categories') . " ORDER BY titlelink");
		foreach ($result as $cat) {
			if (in_array($cat['titlelink'], $categories)) {
				query("INSERT INTO " . prefix('news2cat') . " (cat_id, news_id) VALUES ('" . $cat['id'] . "', '" . $this->getID() . "')");
			}
		}
	}

	/**
	 * Returns true if the article is sticky
	 *
	 * @return bool
	 */
	function getSticky() {
		return $this->get('sticky');
	}

	function setSticky($v) {
		$this->set('sticky', $v);
	}

	function getTruncation() {
		return $this->get('truncation');
	}

	function setTruncation($v) {
		$this->set('truncation', $v);
	}

	/**
	 * duplicates an article
	 * @param string $newtitle the title for the new article
	 */
	function copy($newtitle) {
		$newID = $newtitle;
		$id = parent::copy(array('titlelink' => $newID));
		if (!$id) {
			$newID = $newtitle . ':' . seoFriendly(date('Y-m-d_H-i-s'));
			$id = parent::copy(array('titlelink' => $newID));
		}
		if ($id) {
			$newobj = newArticle($newID);
			$newobj->setTitle($newtitle);
			$newobj->setTags($this->getTags(false));
			$newobj->setShow(0);
			$newobj->setDateTime('');
			$newobj->save();
			$categories = array();
			foreach ($this->getCategories() as $cat) {
				$categories[] = $cat['cat_id'];
			}
			$result = query_full_array("SELECT * FROM " . prefix('news_categories') . " ORDER BY titlelink");
			foreach ($result as $cat) {
				if (in_array($cat['id'], $categories)) {
					query("INSERT INTO " . prefix('news2cat') . " (cat_id, news_id) VALUES ('" . $cat['id'] . "', '" . $id . "')");
				}
			}
			return $newobj;
		}
		return false;
	}

	/**
	 * Deletes an news article from the database
	 *
	 */
	function remove() {
		if ($success = parent::remove()) {
			if ($this->id) {
				$success = query("DELETE FROM " . prefix('obj_to_tag') . "WHERE `type`='news' AND `objectid`=" . $this->getID());
				$success = $success && query("DELETE FROM " . prefix('news2cat') . " WHERE news_id = " . $this->getID()); // delete the category association
				$success = $success && query("DELETE FROM " . prefix('comments') . " WHERE ownerid = " . $this->getID() . ' AND type="news"'); // delete any comments
			}
		}
		return $success;
	}

	/**
	 * Checks if an article is in a password protected category and returns TRUE or FALSE
	 * NOTE: This function does not check if the password has been entered! Use checkAccess() for that.
	 *
	 * @param bool $only set to true to know if the news article belongs only to protected categories (i.e. it is protected)
	 *
	 * @return array
	 */
	function inProtectedCategory($only = false) {
		$categories = $this->getCategories();
		if (!empty($categories)) {
			foreach ($categories as $cat) {
				$catobj = newCategory($cat['titlelink']);
				$password = $catobj->getPassword();
				if (!empty($password)) {
					if (!$only)
						return true;
				} else {
					if ($only)
						return false;
				}
			}
			return $only;
		}
		return false;
	}

	/**
	 * returns true if the article resides only in protected categories
	 */
	function isProtected() {
		return $this->inProtectedCategory(true);
	}

	/**
	 *
	 * returns true if the article exists in any published category (or in no categories)
	 */
	function categoryIsVisible() {
		if (zp_loggedin(MANAGE_ALL_NEWS_RIGHTS))
			return true;
		global $_zp_CMS;
		$categories = $this->getCategories(false);
		if (count($categories) > 0) {
			foreach ($categories as $cat) {
				if ($_zp_CMS->visibleCategory($cat)) {
					return true;
				}
			}
			return false;
		}
		return true;
	}

	/**
	 * See if a guest is logged on to the news category.
	 * Note: If any belonging category is plublic or he is logged on, then success.
	 * @param $hint
	 * @param $show
	 */
	function checkforGuest(&$hint = NULL, &$show = NULL) {
		if (!parent::checkForGuest()) {
			return false;
		}
		$categories = $this->getCategories();
		if (empty($categories)) { //	cannot be protected!
			return 'zp_public_access';
		} else {
			foreach ($categories as $cat) {
				$catobj = newCategory($cat['titlelink']);
				$guestaccess = $catobj->checkforGuest($hint, $show);
				if ($guestaccess) {
					return $guestaccess;
				}
			}
		}
		return false;
	}

	function subRights() {
		global $_zp_current_admin_obj;
		if (!is_null($this->subrights)) {
			return $this->subrights;
		}
		$this->subrights = 0;
		if (zp_loggedin()) {
			if (zp_loggedin($this->manage_rights)) {
				$this->subrights = MANAGED_OBJECT_RIGHTS_EDIT | MANAGED_OBJECT_RIGHTS_VIEW;
				return $this->subrights;
			}

			$categories = $this->getCategories();
			if (!empty($categories)) {
				$objects = $_zp_current_admin_obj->getObjects();
				$possible = array();
				foreach ($objects as $object) {
					if ($object['type'] == 'news') {
						$possible[$object['data']] = $object;
					}
				}
				if (!empty($possible)) {
					foreach ($categories as $category) {
						if (array_key_exists($category['titlelink'], $possible)) {
							$this->subrights = $this->subRights() | $possible[$category['titlelink']]['edit'] | MANAGED_OBJECT_MEMBER;
						}
					}
				}
			}
		}
		return $this->subrights;
	}

	/**
	 * Checks if user is news author
	 * @param bit $action what the caller wants to do
	 *
	 * returns true of access is allowed
	 */
	function isMyItem($action) {
		global $_zp_current_admin_obj;
		if (parent::isMyItem($action)) {
			return true;
		}
		if (zp_loggedin($action)) {
			if (GALLERY_SECURITY == 'public' && $this->getShow() && $action == LIST_RIGHTS) {
				return true;
			}
			if ($_zp_current_admin_obj->getUser() == $this->getAuthor()) {
				return true; //	he is the author
			}
			$mycategories = $_zp_current_admin_obj->getObjects('news');
			if (!empty($mycategories)) {
				foreach ($this->getCategories() as $category) {
					$cat = newCategory($category['titlelink']);
					if ($cat->isMyItem($action)) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 *
	 * Checks if viewing of object is allowed
	 * @param string $hint
	 * @param string $show
	 */
	function checkAccess(&$hint = NULL, &$show = NULL) {
		if (zp_loggedin(ALL_NEWS_RIGHTS))
			return true;
		$categories = $this->getCategories();
		if (empty($categories)) { //	no protection on un-categorized news articles
			return true;
		}
		foreach ($categories as $category) {
			$catobj = newCategory($category['titlelink']);
			if (!$catobj->isProtected() || $catobj->subRights())
				return true;
		}
		return false;
	}

	/**
	 * Checks if an article is in a category and returns TRUE or FALSE
	 *
	 * @param string $catlink The titlelink of a category
	 * @return bool
	 */
	function inNewsCategory($catlink) {
		if (!empty($catlink)) {
			$categories = $this->getCategories();
			$count = 0;
			foreach ($categories as $cat) {
				if ($catlink == $cat['titlelink']) {
					$count = 1;
					break;
				}
			}
			return $count == 1;
		} else {
			return false;
		}
	}

	/**
	 * Checks if an article is in a sub category of $catlink
	 *
	 * @param string $catlink The titlelink of a category
	 * @return bool
	 */
	function inSubNewsCategoryOf($catlink) {
		if (!empty($catlink)) {
			$categories = $this->getCategories();
			$count = 0;
			foreach ($categories as $cat) {
				$catobj = newCategory($cat['titlelink']);
				$parentid = $catobj->getParentID();
				$parentcats = $catobj->getParents();
				foreach ($parentcats as $parentcat) {
					if ($catlink == $parentcat) {
						$count = 1;
						break;
					}
				}
			}
			return $count == 1;
		} else {
			return false;
		}
	}

	/**
	 * Returns the url to a news article
	 *
	 *
	 * @return string
	 */
	function getLink() {
		return zp_apply_filter('getLink', rewrite_path(_NEWS_ . '/' . $this->getTitlelink(), '/index.php?p=news&title=' . $this->getTitlelink()), $this, NULL);
	}

	/**
	 * Get the index of this article
	 *
	 * @param string $sortorder
	 * @param bool $sortdirection TRUE for ascending, FALSE for descending
	 * @param bool $sticky If sticky article should come first
	 * @return int
	 */
	function getIndex($sortorder, $sortdirection, $sticky) {
		global $_zp_CMS;
		if ($this->index == NULL) {
			$articles = $_zp_CMS->getArticles(0, NULL, true, $sortorder, $sortdirection, $sticky);
			for ($i = 0; $i < count($articles); $i++) {
				$article = $articles[$i];
				if ($this->getTitlelink() == $article['titlelink']) {
					$this->index = $i;
					break;
				}
			}
		}
		return $this->index;
	}

	/**
	 * Return the previous article
	 *
	 * @param string $sortorder
	 * @param bool $sortdirection TRUE for ascending, FALSE for descending
	 * @param bool $sticky If sticky article should come first
	 * @return object
	 */
	function getPrevArticle($sortorder = 'date', $sortdirection = false, $sticky = true) {
		global $_zp_CMS;
		$index = $this->getIndex($sortorder, $sortdirection, $sticky);
		$article = $_zp_CMS->getArticle($index - 1);
		return $article;
	}

	/**
	 * Returns the next article.
	 *
	 * @param string $sortorder
	 * @param bool $sortdirection TRUE for ascending, FALSE for descending
	 * @param bool $sticky If sticky article should come first
	 * @return object
	 */
	function getNextArticle($sortorder = 'date', $sortdirection = false, $sticky = true) {
		global $_zp_CMS;
		$index = $this->getIndex($sortorder, $sortdirection, $sticky);
		$article = $_zp_CMS->getArticle($index + 1);
		return $article;
	}

}

// zenpage news class end
?>