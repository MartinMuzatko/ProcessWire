<?php namespace ProcessWire;

/**
 * ProcessWire Pages ($pages API variable)
 *
 * Manages Page instances, providing find, load, save and delete capabilities,
 * some of which are delegated to other classes but this provides the interface to them.
 *
 * This is the most used object in the ProcessWire API. 
 *
 * ProcessWire 3.x (development), Copyright 2015 by Ryan Cramer
 * https://processwire.com
 *
 * @link http://processwire.com/api/variables/pages/ Offical $pages Documentation
 * @link http://processwire.com/api/selectors/ Official Selectors Documentation
 * 
 * PROPERTIES
 * ==========
 * @property bool cloning Whether or not a clone() operation is currently active
 * @property bool outputFormatting Current default output formatting mode.
 * 
 * HOOKABLE METHODS
 * ================
 * @method PageArray find() find($selectorString, array $options = array()) Find and return all pages matching the given selector string. Returns a PageArray.
 * @method bool save() save(Page $page) Save any changes made to the given $page. Same as : $page->save() Returns true on success
 * @method bool saveField() saveField(Page $page, $field) Save just the named field from $page. Same as : $page->save('field')
 * @method bool trash() trash(Page $page, $save = true) Move a page to the trash. If you have already set the parent to somewhere in the trash, then this method won't attempt to set it again.
 * @method bool restore(Page $page, $save = true) Restore a trashed page to its original location. 
 * @method int emptyTrash() Empty the trash and return number of pages deleted. 
 * @method bool delete() delete(Page $page, $recursive = false) Permanently delete a page and it's fields. Unlike trash(), pages deleted here are not restorable. If you attempt to delete a page with children, and don't specifically set the $recursive param to True, then this method will throw an exception. If a recursive delete fails for any reason, an exception will be thrown.
 * @method Page|NullPage clone(Page $page, Page $parent = null, $recursive = true, $options = array()) Clone an entire page, it's assets and children and return it.
 * @method Page|NullPage add($template, $parent, $name = '', array $values = array())
 * @method setupNew(Page $page) Setup new page that does not yet exist by populating some fields to it. 
 * @method string setupPageName(Page $page, array $options = array()) Determine and populate a name for the given page.
 * 
 * METHODS PURELY FOR HOOKS
 * ========================
 * You can hook these methods, but you should not call them directly. 
 * See the phpdoc in the actual methods for more details about arguments and additional properties that can be accessed.
 * 
 * @method saveReady(Page $page) Hook called just before a page is saved. 
 * @method saved(Page $page, array $changes = array(), $values = array()) Hook called after a page is successfully saved. 
 * @method added(Page $page) Hook called when a new page has been added. 
 * @method moved(Page $page) Hook called when a page has been moved from one parent to another. 
 * @method templateChanged(Page $page) Hook called when a page template has been changed. 
 * @method trashed(Page $page) Hook called when a page has been moved to the trash. 
 * @method restored(Page $page) Hook called when a page has been moved OUT of the trash. 
 * @method deleteReady(Page $page) Hook called just before a page is deleted. 
 * @method deleted(Page $page) Hook called after a page has been deleted. 
 * @method cloneReady(Page $page, Page $copy) Hook called just before a page is cloned. 
 * @method cloned(Page $page, Page $copy) Hook called after a page has been successfully cloned. 
 * @method renamed(Page $page) Hook called after a page has been successfully renamed. 
 * @method statusChangeReady(Page $page) Hook called when a page's status has changed and is about to be saved.
 * @method statusChanged(Page $page) Hook called after a page status has been changed and saved. 
 * @method publishReady(Page $page) Hook called just before an unpublished page is published. 
 * @method published(Page $page) Hook called after an unpublished page has just been published. 
 * @method unpublishReady(Page $page) Hook called just before a pubished page is unpublished. 
 * @method unpublished(Page $page) Hook called after a published page has just been unpublished. 
 * @method saveFieldReady(Page $page, Field $field) Hook called just before a saveField() method saves a page fied. 
 * @method savedField(Page $page, Field $field) Hook called after saveField() method successfully executes. 
 * @method found(PageArray $pages, array $details) Hook called at the end of a $pages->find().
 * @method unknownColumnError($column) Called when a page-data loading query encounters an unknown column.
 *
 * TO-DO
 * =====
 * @todo Add a getCopy method that does a getById($id, array('cache' => false) ?
 * @todo Some methods here (find, save, etc.) need their own dedicated classes. 
 * @todo Update saveField to accept array of field names as an option. 
 *
 */

class Pages extends Wire {

	/**
	 * Max length for page name
	 * 
	 */
	const nameMaxLength = 128;

	/**
	 * Instance of PageFinder (when cached)
	 *
	 */
	protected $pageFinder = null; 

	/**
	 * Instance of Templates
	 *
	 */
	protected $templates; 

	/**
	 * Instance of PagesSortfields
	 *
	 */
	protected $sortfields;

	/**
	 * Pages that have been cached, indexed by ID
	 *
	 */
	protected $pageIdCache = array();

	/**
	 * Cached selector strings and the PageArray that was found.
	 *
	 */
	protected $pageSelectorCache = array();

	/**
	 * Controls the outputFormatting state for pages that are loaded
	 *
	 */
	protected $outputFormatting = false; 

	/**
	 * Runtime debug log of Pages class activities, see getDebugLog()
	 *
	 */
	protected $debugLog = array();

	/**
	 * Shortcut to $config API var
	 *
	 */
	protected $config = null;

	/**
	 * Are we currently cloning a page?
	 * 
	 * This is true only when the clone() method is currently in progress. 
	 * 
	 * @var bool
	 * 
	 */
	protected $cloning = false;

	/**
	 * Autojoin allowed?
	 * 
	 * @var bool
	 * 
	 */
	protected $autojoin = true; 

	/**
	 * Name for autogenerated page names when fields to generate name aren't populated
	 * 
	 * @var string
	 * 
	 */
	protected $untitledPageName = 'untitled';

	/**
	 * Enable 2.x compatibility mode?
	 * 
	 * @var bool
	 * 
	 */
	protected $compat2x = false;

	/**
	 * Create the Pages object
	 * 
	 * @param ProcessWire $wire
	 *
	 */
	public function __construct(ProcessWire $wire) {
		$this->setWire($wire);
		$this->config = $this->wire('config');
		$this->templates = $this->wire('templates');
		$this->sortfields = $this->wire(new PagesSortfields());
		$this->compat2x = $this->config->compat2x;
	}

	/**
	 * Initialize $pages API var by preloading some pages 
	 * 
	 */
	public function init() {
		$this->getById($this->config->preloadPageIDs); 
	}

	/**
	 * Given a Selector string, return the Page objects that match in a PageArray. 
	 * 
	 * Non-visible pages are excluded unless an include=hidden|unpublished|all mode is specified in the selector string, 
	 * or in the $options array. If 'all' mode is specified, then non-accessible pages (via access control) can also be included. 
	 *
	 * @param string|int|array $selectorString Specify selector string (standard usage), but can also accept page ID or array of page IDs.
	 * @param array|string $options Optional one or more options that can modify certain behaviors. May be assoc array or key=value string.
	 *	- findOne: boolean - apply optimizations for finding a single page 
	 *  - findAll: boolean - find all pages with no exculsions (same as include=all option)
	 *	- getTotal: boolean - whether to set returning PageArray's "total" property (default: true except when findOne=true)
	 *	- loadPages: boolean - whether to populate the returned PageArray with found pages (default: true). 
	 *		The only reason why you'd want to change this to false would be if you only needed the count details from 
	 *		the PageArray: getTotal(), getStart(), getLimit, etc. This is intended as an optimization for Pages::count().
	 * 		Does not apply if $selectorString argument is an array. 
	 *  - caller: string - optional name of calling function, for debugging purposes, i.e. pages.count
	 * 	- include: string - Optional inclusion mode of 'hidden', 'unpublished' or 'all'. Default=none. Typically you would specify this 
	 * 		directly in the selector string, so the option is mainly useful if your first argument is not a string. 
	 * 	- loadOptions: array - Optional assoc array of options to pass to getById() load options.
	 * @return PageArray
	 *
	 */
	public function ___find($selectorString, $options = array()) {
		
		if(is_string($options)) $options = Selectors::keyValueStringToArray($options); 
		$loadOptions = isset($options['loadOptions']) && is_array($options['loadOptions']) ? $options['loadOptions'] : array();

		if(is_array($selectorString)) {
			if(ctype_digit(implode('', array_keys($selectorString))) && ctype_digit(implode('', $selectorString))) {
				// if given a regular array of page IDs, we delegate that to getById() method, but with access/visibility control
				return $this->filterListable(
					$this->getById($selectorString), 
					(isset($options['include']) ? $options['include'] : ''), 
					$loadOptions); 
			} else {
				// some other type of array/values that we don't yet recognize
				// @todo add support for array selectors, per Selectors::arrayToSelectorString()
				return $this->newPageArray($loadOptions);
			}
		}

		$loadPages = true;
		$debug = $this->wire('config')->debug;

		if(array_key_exists('loadPages', $options)) $loadPages = (bool) $options['loadPages'];
		if(!strlen($selectorString)) return $this->newPageArray($loadOptions);
		if($selectorString === '/' || $selectorString === 'path=/') $selectorString = 1;

		if($selectorString[0] == '/') {
			// if selector begins with a slash, then we'll assume it's referring to a path
			$selectorString = "path=$selectorString";

		} else if(strpos($selectorString, ",") === false && strpos($selectorString, "|") === false) {
			// there is just one param. Lets see if we can find a shortcut. 
			if(ctype_digit("$selectorString") || strpos($selectorString, "id=") === 0) {
				// if selector is just a number, or a string like "id=123" then we're going to do a shortcut
				$s = str_replace("id=", '', $selectorString); 
				if(ctype_digit("$s")) {
					$value = $this->getById(array((int) $s), $loadOptions);
					if(empty($options['findOne'])) $value = $this->filterListable(
						$value, (isset($options['include']) ? $options['include'] : ''), $loadOptions);
					if($debug) $this->debugLog('find', $selectorString . " [optimized]", $value); 
					return $value; 
				}
			}
		}

		if(isset($options['include']) && in_array($options['include'], array('hidden', 'unpublished', 'all'))) {
			$selectorString .= ", include=$options[include]";
		}
		// see if this has been cached and return it if so
		$pages = $this->getSelectorCache($selectorString, $options); 
		if(!is_null($pages)) {
			if($debug) $this->debugLog('find', $selectorString, $pages . ' [from-cache]'); 
			return $pages; 
		}

		// check if this find has already been executed, and return the cached results if so
		// if(null !== ($pages = $this->getSelectorCache($selectorString, $options))) return clone $pages; 

		// if a specific parent wasn't requested, then we assume they don't want results with status >= Page::statusUnsearchable
		// if(strpos($selectorString, 'parent_id') === false) $selectorString .= ", status<" . Page::statusUnsearchable; 

		$caller = isset($options['caller']) ? $options['caller'] : 'pages.find';
		$selectors = $this->wire(new Selectors()); 
		$selectors->init($selectorString);
		$pageFinder = $this->getPageFinder();
		if($debug) Debug::timer("$caller($selectorString)", true); 
		$pagesInfo = $pageFinder->find($selectors, $options); 

		// note that we save this pagination state here and set it at the end of this method
		// because it's possible that more find operations could be executed as the pages are loaded
		$total = $pageFinder->getTotal();
		$limit = $pageFinder->getLimit();
		$start = $pageFinder->getStart();

		if($loadPages) { 
			// parent_id is null unless a single parent was specified in the selectors
			$parent_id = $pageFinder->getParentID();

			$idsSorted = array(); 
			$idsByTemplate = array();

			// organize the pages by template ID
			foreach($pagesInfo as $page) {
				$tpl_id = $page['templates_id']; 
				if(!isset($idsByTemplate[$tpl_id])) $idsByTemplate[$tpl_id] = array();
				$idsByTemplate[$tpl_id][] = $page['id'];
				$idsSorted[] = $page['id'];
			}

			if(count($idsByTemplate) > 1) {
				// perform a load for each template, which results in unsorted pages
				$unsortedPages = $this->newPageArray($loadOptions);
				foreach($idsByTemplate as $tpl_id => $ids) {
					$opt = $loadOptions; 
					$opt['template'] = $this->templates->get($tpl_id); 
					$opt['parent_id'] = $parent_id; 
					$unsortedPages->import($this->getById($ids, $opt)); 
				}

				// put pages back in the order that the selectorEngine returned them in, while double checking that the selector matches
				$pages = $this->newPageArray($loadOptions);
				foreach($idsSorted as $id) {
					foreach($unsortedPages as $page) { 
						if($page->id == $id) {
							$pages->add($page); 
							break;
						}
					}
				}
			} else {
				// there is only one template used, so no resorting is necessary	
				$pages = $this->newPageArray($loadOptions);
				reset($idsByTemplate); 
				$opt = $loadOptions; 
				$opt['template'] = $this->templates->get(key($idsByTemplate)); 
				$opt['parent_id'] = $parent_id; 
				$pages->import($this->getById($idsSorted, $opt)); 
			}

		} else {
			$pages = $this->newPageArray($loadOptions);
		}

		$pages->setTotal($total); 
		$pages->setLimit($limit); 
		$pages->setStart($start); 
		$pages->setSelectors($selectors); 
		$pages->setTrackChanges(true);
		
		if($loadPages) $this->selectorCache($selectorString, $options, $pages); 
		if($this->config->debug) $this->debugLog('find', $selectorString, $pages);
		
		if($debug) {
			$count = $pages->count();
			$note = ($count == $total ? $count : $count . "/$total") . " page(s)";
			if($count) {
				$note .= ": " . $pages->first()->path; 
				if($count > 1) $note .= " ... " . $pages->last()->path;  
			}
			Debug::saveTimer("$caller($selectorString)", $note); 
			foreach($pages as $item) {
				if($item->_debug_loaded) continue;
				$item->setQuietly('_debug_loader', "$caller($selectorString)");
			}
		}
	
		if($this->hasHook('found()')) $this->found($pages, array(
			'pageFinder' => $pageFinder, 
			'pagesInfo' => $pagesInfo, 
			'options' => $options
			));

		return $pages; 
	}


	/**
	 * Like find() but returns only the first match as a Page object (not PageArray)
	 * 
	 * This is functionally similar to the get() method except that its default behavior is to
	 * filter for access control and hidden/unpublished/etc. states, in the same way that the
	 * find() method does. You can add an "include=..." to your selector string to bypass. 
	 * This method also accepts an $options arrray, whereas get() does not. 
	 *
	 * @param string $selectorString
	 * @param array|string $options See $options for Pages::find
	 * @return Page|NullPage
	 *
	 */
	public function findOne($selectorString, $options = array()) {
		if(empty($selectorString)) return $this->newNullPage();
		if(is_string($options)) $options = Selectors::keyValueStringToArray($options);
		$defaults = array(
			'findOne' => true, // find only one page
			'getTotal' => false, // don't count totals
			'caller' => 'pages.findOne'
		);
		$options = array_merge($defaults, $options);
		$page = $this->find($selectorString, $options)->first();
		if(!$page || !$page->viewable(false)) $page = $this->newNullPage();
		return $page;
	}

	/**
	 * Returns the first page matching the given selector with no exclusions
	 *
	 * @param string $selectorString
	 * @return Page|NullPage Always returns a Page object, but will return NullPage (with id=0) when no match found
	 * 
	 */
	public function get($selectorString) {
		if(empty($selectorString)) return $this->newNullPage();
		$page = $this->getCache($selectorString); 
		if($page) return $page;
		$options = array(
			'findOne' => true, // find only one page
			'findAll' => true, // no exclusions
			'getTotal' => false, // don't count totals
			'caller' => 'pages.get'
		);
		$page = $this->find($selectorString, $options)->first();
		if(!$page) $page = $this->newNullPage();
		return $page; 
	}

	/**
	 * Given an array or CSV string of Page IDs, return a PageArray 
	 *
	 * Optionally specify an $options array rather than a template for argument 2. When present, the 'template' and 'parent_id' arguments may be provided
	 * in the given $options array. These options may be specified: 
	 * 
	 * LOAD OPTIONS (argument 2 array): 
	 * - cache: boolean, default=true. place loaded pages in memory cache?
	 * - getFromCache: boolean, default=true. Allow use of previously cached pages in memory (rather than re-loading it from DB)?
	 * - template: instance of Template (see $template argument)
	 * - parent_id: integer (see $parent_id argument)
	 * - getNumChildren: boolean, default=true. Specify false to disable retrieval and population of 'numChildren' Page property. 
	 * - getOne: boolean, default=false. Specify true to return just one Page object, rather than a PageArray.
	 * - autojoin: boolean, default=true. Allow use of autojoin option?
	 * - joinFields: array, default=empty. Autojoin the field names specified in this array, regardless of field settings (requires autojoin=true).
	 * - joinSortfield: boolean, default=true. Whether the 'sortfield' property will be joined to the page.
	 * - findTemplates: boolean, default=true. Determine which templates will be used (when no template specified) for more specific autojoins.
	 * - pageClass: string, default=auto-detect. Class to instantiate Page objects with. Leave blank to determine from template. 
	 * - pageArrayClass: string, default=PageArray. PageArray-derived class to store pages in (when 'getOne' is false). 
	 * 
	 * Use the $options array for potential speed optimizations:
	 * - Specify a 'template' with your call, when possible, so that this method doesn't have to determine it separately. 
	 * - Specify false for 'getNumChildren' for potential speed optimization when you know for certain pages will not have children. 
	 * - Specify false for 'autojoin' for potential speed optimization in certain scenarios (can also be a bottleneck, so be sure to test). 
	 * - Specify false for 'joinSortfield' for potential speed optimization when you know the Page will not have children or won't need to know the order.
	 * - Specify false for 'findTemplates' so this method doesn't have to look them up. Potential speed optimization if you have few autojoin fields globally.
	 * - Note that if you specify false for 'findTemplates' the pageClass is assumed to be 'Page' unless you specify something different for the 'pageClass' option.
	 *
	 * @param array|WireArray|string $_ids Array of IDs or CSV string of IDs
	 * @param Template|array|null $template Specify a template to make the load faster, because it won't have to attempt to join all possible fields... just those used by the template. 
	 *	Optionally specify an $options array instead, see the method notes above. 
	 * @param int|null $parent_id Specify a parent to make the load faster, as it reduces the possibility for full table scans. 
	 *	This argument is ignored when an options array is supplied for the $template. 
	 * @return PageArray|Page Returns Page only if the 'getOne' option is specified, otherwise always returns a PageArray.
	 * @throws WireException
	 *
	 */
	public function getById($_ids, $template = null, $parent_id = null) {
		
		static $instanceID = 0;
		
		$options = array(
			'cache' => true, 
			'getFromCache' => true, 
			'template' => null,
			'parent_id' => null, 
			'getNumChildren' => true,
			'getOne' => false, 
			'autojoin' => true, 
			'findTemplates' => true, 
			'joinSortfield' => true, 
			'joinFields' => array(),
			'pageClass' => '',  // blank = auto detect
			'pageArrayClass' => 'PageArray', 
			);
		
		if(is_array($template)) {
			// $template property specifies an array of options
			$options = array_merge($options, $template); 
			$template = $options['template'];
			$parent_id = $options['parent_id'];
		} else if(!is_null($template) && !$template instanceof Template) {
			throw new WireException('getById argument 2 must be Template or $options array'); 
		}

		if(!is_null($parent_id) && !is_int($parent_id)) {
			// convert Page object or string to integer id
			$parent_id = (int) ((string) $parent_id);
		}
		
		if(!is_null($template) && !is_object($template)) {
			// convert template string or id to Template object
			$template = $this->wire('templates')->get($template); 
		}

		if(is_string($_ids)) {
			// convert string of IDs to array
			if(strpos($_ids, '|') !== false) $_ids = explode('|', $_ids); 
				else $_ids = explode(",", $_ids);
		} else if(is_int($_ids)) {
			$_ids = array($_ids);
		}
		
		if(!WireArray::iterable($_ids) || !count($_ids)) {
			// return blank if $_ids isn't iterable or is empty
			return $options['getOne'] ? $this->newNullPage() : $this->newPageArray($options);
		}
		
		if(is_object($_ids)) $_ids = $_ids->getArray(); // ArrayObject or the like
		
		$loaded = array(); // array of id => Page objects that have been loaded
		$ids = array(); // sanitized version of $_ids

		// sanitize ids and determine which pages we can pull from cache
		foreach($_ids as $key => $id) {
			
			$id = (int) $id; 
			if($id < 1) continue; 

			if($options['getFromCache'] && $page = $this->getCache($id)) {
				// page is already available in the cache	
				$loaded[$id] = $page; 
			
			} else if(isset(Page::$loadingStack[$id])) {
				// if the page is already in the process of being loaded, point to it rather than attempting to load again.
				// the point of this is to avoid a possible infinite loop with autojoin fields referencing each other.
				$p = Page::$loadingStack[$id];
				if($p) {
					$loaded[$id] = $p;
					// cache the pre-loaded version so that other pages referencing it point to this instance rather than loading again
					$this->cache($loaded[$id]);
				}

			} else {
				$loaded[$id] = ''; // reserve the spot, in this order
				$ids[(int) $key] = $id; // queue id to be loaded
			}
		}

		$idCnt = count($ids); // idCnt contains quantity of remaining page ids to load
		if(!$idCnt) {
			// if there are no more pages left to load, we can return what we've got
			if($options['getOne']) return count($loaded) ? reset($loaded) : $this->newNullPage();
			$pages = $this->newPageArray($options);
			$pages->import($loaded);
			return $pages; 
		}

		$database = $this->wire('database');
		$idsByTemplate = array();

		if(is_null($template) && $options['findTemplates']) {
			
			// template was not defined with the function call, so we determine
			// which templates are used by each of the pages we have to load

			$sql = "SELECT id, templates_id FROM pages WHERE ";
			
			if($idCnt == 1) {
				$sql .= "id=" . (int) reset($ids);
			} else {
				$sql .= "id IN(" . implode(",", $ids) . ")";
			}

			$query = $database->prepare($sql);
			$result = $this->executeQuery($query);
			if($result) {
				/** @noinspection PhpAssignmentInConditionInspection */
				while($row = $query->fetch(\PDO::FETCH_NUM)) {
					list($id, $templates_id) = $row;
					$id = (int) $id;
					$templates_id = (int) $templates_id;
					if(!isset($idsByTemplate[$templates_id])) $idsByTemplate[$templates_id] = array();
					$idsByTemplate[$templates_id][] = $id;
				}
			}
			$query->closeCursor();

		} else if(is_null($template)) { 
			// no template provided, and autojoin not needed (so we don't need to know template)
			$idsByTemplate = array(0 => $ids); 
			
		} else {
			// template was provided
			$idsByTemplate = array($template->id => $ids); 
		}

		foreach($idsByTemplate as $templates_id => $ids) { 

			if($templates_id && (!$template || $template->id != $templates_id)) {
				$template = $this->wire('templates')->get($templates_id);
			}
			
			if($template) {
				$fields = $template->fieldgroup;
			} else {
				$fields = $this->wire('fields'); 
			}
		
			/** @var DatabaseQuerySelect $query */
			$query = $this->wire(new DatabaseQuerySelect());
			$sortfield = $template ? $template->sortfield : ''; 
			$joinSortfield = empty($sortfield) && $options['joinSortfield'];

			$query->select(
				// note that "false AS isLoaded" triggers the setIsLoaded() function in Page intentionally
				"false AS isLoaded, pages.templates_id AS templates_id, pages.*, " . 
				($joinSortfield ? 'pages_sortfields.sortfield, ' : '') . 
				($options['getNumChildren'] ? '(SELECT COUNT(*) FROM pages AS children WHERE children.parent_id=pages.id) AS numChildren' : '')
				); 

			if($joinSortfield) $query->leftjoin('pages_sortfields ON pages_sortfields.pages_id=pages.id'); 
			$query->groupby('pages.id'); 

			if($options['autojoin'] && $this->autojoin) foreach($fields as $field) {
				if(!empty($options['joinFields']) && in_array($field->name, $options['joinFields'])) {
					// joinFields option specified to force autojoin this field
				} else {
					if(!($field->flags & Field::flagAutojoin)) continue; // autojoin not enabled for field
					if($fields instanceof Fields && !($field->flags & Field::flagGlobal)) continue; // non-fieldgroup, autojoin only if global flag is set
				}
				$table = $database->escapeTable($field->table);
				if(!$field->type || !$field->type->getLoadQueryAutojoin($field, $query)) continue; // autojoin not allowed
				$query->leftjoin("$table ON $table.pages_id=pages.id"); // QA
			}

			if(!is_null($parent_id)) $query->where("pages.parent_id=" . (int) $parent_id); 
			if($template) $query->where("pages.templates_id=" . ((int) $template->id)); // QA
			
			$query->where("pages.id IN(" . implode(',', $ids) . ") "); // QA
			$query->from("pages");
			
			$stmt = $query->prepare(); 
			$this->executeQuery($stmt);
		
			$class = $options['pageClass'];
			if(empty($class)) {
				if($template) {
					$class = ($template->pageClass && wireClassExists($template->pageClass)) ? $template->pageClass : 'Page';
				} else {
					$class = 'Page';
				}
			}
			if($class != 'Page' && !wireClassExists($class)) {
				$this->error("Class '$class' for Pages::getById() does not exist", Notice::log);
				$class = 'Page';
			}
			if($this->compat2x && class_exists("\\$class")) $class = "\\$class";

			try {
				$_class = wireClassName($class, true);
				// while($page = $stmt->fetchObject($_class, array($template))) {
				/** @noinspection PhpAssignmentInConditionInspection */
				while($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
					$page = $this->newPage(array(
						'pageClass' => $_class, 
						'template' => $template ? $template : $row['templates_id'], 
					));
					unset($row['templates_id']); 
					foreach($row as $key => $value) $page->set($key, $value);
					$page->instanceID = ++$instanceID;
					$page->setIsLoaded(true);
					$page->setIsNew(false);
					$page->setTrackChanges(true);
					$page->setOutputFormatting($this->outputFormatting);
					$loaded[$page->id] = $page;
					if($options['cache']) $this->cache($page);
				}
			} catch(\Exception $e) {
				$error = $e->getMessage() . " [pageClass=$class, template=$template]";
				$user = $this->wire('user');
				if($user && $user->isSuperuser()) $this->error($error);
				$this->wire('log')->error($error);
				$this->trackException($e, false);
			}
			
			$stmt->closeCursor();
			$template = null;
		}
		
		if($options['getOne']) return count($loaded) ? reset($loaded) : $this->newNullPage();
		$pages = $this->newPageArray($options);
		$pages->import($loaded); 
	
		// debug mode only
		if($this->wire('config')->debug) {
			$_template = is_null($template) ? '' : ", $template";
			$_parent_id = is_null($parent_id) ? '' : ", $parent_id";
			$_ids = count($_ids) > 1 ? "[" . implode(',', $_ids) . "]" : implode('', $_ids);
			foreach($pages as $item) {
				$item->setQuietly('_debug_loader', "getByID($_ids$_template$_parent_id)");
			}
		}
		
		return $pages;
	}
	
	/**
	 * Remove pages from already-loaded PageArray aren't visible or accessible
	 *
	 * @param PageArray $items
	 * @param string $includeMode Optional inclusion mode:
	 * 	- 'hidden': Allow pages with 'hidden' status'
	 * 	- 'unpublished': Allow pages with 'unpublished' or 'hidden' status
	 * 	- 'all': Allow all pages (not much point in calling this method)
	 * @param array $options loadOptions 
	 * @return PageArray
	 *
	 */
	protected function filterListable(PageArray $items, $includeMode = '', array $options = array()) {
		if($includeMode === 'all') return $items;
		$itemsAllowed = $this->newPageArray($options);
		foreach($items as $item) {
			if($includeMode === 'unpublished') {
				$allow = $item->status < Page::statusTrash;
			} else if($includeMode === 'hidden') {
				$allow = $item->status < Page::statusUnpublished;
			} else {
				$allow = $item->status < Page::statusHidden;
			}
			if($allow) $allow = $item->listable(); // confirm access
			if($allow) $itemsAllowed->add($item);
		}
		$itemsAllowed->resetTrackChanges(true);
		return $itemsAllowed;
	}

	/**
	 * Add a new page using the given template to the given parent
	 * 
	 * If no name is specified one will be assigned based on the current timestamp.
	 * 
	 * @param string|Template $template Template name or Template object
	 * @param string|int|Page $parent Parent path, ID or Page object
	 * @param string $name Optional name or title of page. If none provided, one will be automatically assigned based on microtime stamp.
	 * 	If you want to specify a different name and title then specify the $name argument, and $values['title']. 
	 * @param array $values Field values to assign to page (optional). If $name is ommitted, this may also be 3rd param. 
	 * @return Page Returned page has output formatting off. 
	 * @throws WireException When some criteria prevents the page from being saved. 
	 * 
	 */
	public function ___add($template, $parent, $name = '', array $values = array()) {
	
		// the $values may optionally be the 3rd argument
		if(is_array($name)) {
			$values = $name;
			$name = isset($values['name']) ? $values['name'] : '';
		}

		if(!is_object($template)) {
			$template = $this->wire('templates')->get($template); 	
			if(!$template) throw new WireException("Unknown template"); 
		}

		$pageClass = wireClassName($template->pageClass ? $template->pageClass : 'Page', true);	
	
		$page = $this->newPage(array(
			'template' => $template, 
			'pageClass' => $pageClass
		));
		$page->parent = $parent; 
		
		$exceptionMessage = "Unable to add new page using template '$template' and parent '{$page->parent->path}'."; 
	
		if(empty($values['title'])) {
			// no title provided in $values, so we assume $name is $title
			// but if no name is provided, then we default to: Untitled Page
			if(!strlen($name)) $name = $this->_('Untitled Page');
			// the setupNew method will convert $page->title to a unique $page->name
			$page->title = $name; 
			
		} else {
			// title was provided
			$page->title = $values['title'];
			// if name is provided we use it
			// otherwise setupNew will take care of assign it from title
			if(strlen($name)) $page->name = $name; 
			unset($values['title']); 
		}
	
		// save page before setting $values just in case any fieldtypes
		// require the page to have an ID already (like file-based)
		if(!$this->save($page)) throw new WireException($exceptionMessage); 
	
		// set field values, if provided
		if(!empty($values)) {
			unset($values['id'], $values['parent'], $values['template']); // fields that may not be set from this array
			foreach($values as $key => $value) $page->set($key, $value);
			$this->save($page); 
		}
		
		return $page; 
	}

	/**
	 * Given an ID return a path to a page, without loading the actual page
	 *
 	 * This is not meant to be public API: You should just use $pages->get($id)->path (or url) instead.
	 * This is just a small optimization function for specific situations (like the PW bootstrap).
	 * This function is not meant to be part of the public $pages API, as I think it only serves 
	 * to confuse with $page->path(). However, if you ever have a situation where you need to get a page
 	 * path and want to avoid loading the page for some reason, this function is your ticket.
	 *
	 * @param int $id ID of the page you want the URL to
	 * @return string URL to page or blank on error
	 *
 	 */
	public function _path($id) {

		if(is_object($id) && $id instanceof Page) return $id->path();
		$id = (int) $id;
		if(!$id) return '';

		// if page is already loaded, then get the path from it
		if(isset($this->pageIdCache[$id])) {
			/** @var Page $page */
			$page = $this->pageIdCache[$id];
			return $page->path();
		}

		if($this->modules->isInstalled('PagePaths')) {
			/** @var PagePaths $pagePaths */
			$pagePaths = $this->modules->get('PagePaths');
			$path = $pagePaths->getPath($id);
			if(is_null($path)) $path = '';
			return $path; 
		}

		$path = '';
		$parent_id = $id; 
		$database = $this->wire('database');
		do {
			$query = $database->prepare("SELECT parent_id, name FROM pages WHERE id=:parent_id"); // QA
			$query->bindValue(":parent_id", (int) $parent_id, \PDO::PARAM_INT); 
			$this->executeQuery($query);
			list($parent_id, $name) = $query->fetch(\PDO::FETCH_NUM);
			$path = $name . '/' . $path;
		} while($parent_id > 1); 

		return '/' . ltrim($path, '/');
	}

	/**
	 * Count and return how many pages will match the given selector string
	 *
	 * @param string $selectorString Specify selector string, or omit to retrieve a site-wide count.
	 * @param array|string $options See $options in Pages::find 
	 * @return int
	 *
	 */
	public function count($selectorString = '', $options = array()) {
		if(is_string($options)) $options = Selectors::keyValueStringToArray($options);
		if(!strlen($selectorString)) {
			if(empty($options)) {
				// optimize away a simple site-wide total count
				return (int) $this->wire('database')->query("SELECT COUNT(*) FROM pages")->fetch(\PDO::FETCH_COLUMN);
			} else {
				// no selector string, but options specified
				$selectorString = "id>0";
			}
		}
		$options['loadPages'] = false; 
		$options['getTotal'] = true; 
		$options['caller'] = 'pages.count';
		$options['returnVerbose'] = false;
		//if($this->wire('config')->debug) $options['getTotalType'] = 'count'; // test count method when in debug mode
		return $this->find("$selectorString, limit=1", $options)->getTotal();
	}

	/**
	 * Is the given page in a state where it can be saved?
	 *
	 * @param Page $page
	 * @param string $reason Text containing the reason why it can't be saved (assuming it's not saveable)
	 * @param string|Field $fieldName Optional fieldname to limit check to. 
	 * @param array $options Options array given to the original save method (optional)
	 * @return bool True if saveable, False if not
	 *
	 */
	public function isSaveable(Page $page, &$reason, $fieldName = '', array $options = array()) {

		$saveable = false; 
		$outputFormattingReason = "Call \$page->setOutputFormatting(false) before getting/setting values that will be modified and saved. "; 
		$corrupted = array(); 
		
		if($fieldName && is_object($fieldName)) {
			/** @var Field $fieldName */
			$fieldName = $fieldName->name;
			/** @var string $fieldName */
		}
		
		if($page->hasStatus(Page::statusCorrupted)) {
			$corruptedFields = $page->_statusCorruptedFields; 
			foreach($page->getChanges() as $change) {
				if(isset($corruptedFields[$change])) $corrupted[] = $change;
			}
			// if focused on a specific field... 
			if($fieldName && !in_array($fieldName, $corrupted)) $corrupted = array();
		}

		if($page instanceof NullPage) $reason = "Pages of type NullPage are not saveable";
			else if((!$page->parent || $page->parent instanceof NullPage) && $page->id !== 1) $reason = "It has no parent assigned"; 
			else if(!$page->template) $reason = "It has no template assigned"; 
			else if(!strlen(trim($page->name)) && $page->id != 1) $reason = "It has an empty 'name' field"; 
			else if(count($corrupted)) $reason = $outputFormattingReason . " [Page::statusCorrupted] fields: " . implode(', ', $corrupted);
			else if($page->id == 1 && !$page->template->useRoles) $reason = "Selected homepage template cannot be used because it does not define access.";
			else if($page->id == 1 && !$page->template->hasRole('guest')) $reason = "Selected homepage template cannot be used because it does not have the required 'guest' role in it's access settings.";
			else $saveable = true; 

		// check if they could corrupt a field by saving
		if($saveable && $page->outputFormatting) {
			// iternate through recorded changes to see if any custom fields involved
			foreach($page->getChanges() as $change) {
				if($fieldName && $change != $fieldName) continue; 
				if($page->template->fieldgroup->getField($change) !== null) {
					$reason = $outputFormattingReason . " [$change]";	
					$saveable = false;
					break;
				}
			}
			// iterate through already-loaded data to see if any are objects that have changed
			if($saveable) foreach($page->getArray() as $key => $value) {
				if($fieldName && $key != $fieldName) continue; 
				if(!$page->template->fieldgroup->getField($key)) continue; 
				if(is_object($value) && $value instanceof Wire && $value->isChanged()) {
					$reason = $outputFormattingReason . " [$key]";
					$saveable = false; 
					break;
				}
			}
		}

		// FAMILY CHECKS
		// check for a parent change and whether it is allowed
		if($saveable && $page->parentPrevious && $page->parentPrevious->id != $page->parent->id && empty($options['ignoreFamily'])) {
			// page was moved
			if($page->template->noMove && ($page->hasStatus(Page::statusSystem) || $page->hasStatus(Page::statusSystemID) || !$page->isTrash())) {
				// make sure the page's template allows moves. only move laways allowed is to the trash, unless page has system status
				$saveable = false;
				$reason = "Pages using template '{$page->template}' are not moveable (template::noMove)";

			} else if($page->parent->template->noChildren) {
				$saveable = false;
				$reason = "Chosen parent '{$page->parent->path}' uses template that does not allow children.";

			} else if($page->parent->id && $page->parent->id != $this->config->trashPageID && count($page->parent->template->childTemplates) && !in_array($page->template->id, $page->parent->template->childTemplates)) {
				// make sure the new parent's template allows pages with this template
				$saveable = false;
				$reason = "Can't move '{$page->name}' because Template '{$page->parent->template}' used by '{$page->parent->path}' doesn't allow children with this template.";

			} else if(count($page->template->parentTemplates) && $page->parent->id != $this->config->trashPageID && !in_array($page->parent->template->id, $page->template->parentTemplates)) {
				$saveable = false;
				$reason = "Can't move '{$page->name}' because Template '{$page->parent->template}' used by '{$page->parent->path}' is not allowed by template '{$page->template->name}'.";

			} else if(count($page->parent->children("name={$page->name}, id!=$page->id, include=all"))) { 
				$saveable = false;
				$reason = "Chosen parent '{$page->parent->path}' already has a page named '{$page->name}'"; 
			}
		}

		return $saveable; 
	}

	/**
	 * Auto-populate some fields for a new page that does not yet exist
	 *
	 * Currently it does this: 
	 * - Sets up a unique page->name based on the format or title if one isn't provided already. 
	 * - Assigns a 'sort' value'. 
	 * 
	 * @param Page $page
	 *
	 */
	public function ___setupNew(Page $page) {

		$parent = $page->parent();
		if(!$parent->id) {
			// auto-assign a parent, if we can find one in family settings

			$parentTemplates = $page->template->parentTemplates; 
			$parent = null;

			if(!empty($parentTemplates)) {
				$idStr = implode('|', $parentTemplates); 
				$parent = $this->get("include=hidden, template=$idStr"); 
				if(!$parent->id) $parent = $this->get("include=all, template=$idStr"); 
			}

			if($parent->id) $page->parent = $parent; 
		}

		if(!strlen($page->name)) $this->setupPageName($page); 

		if($page->sort < 0) {
			// auto assign a sort
			$page->sort = $page->parent->numChildren();
		}

		foreach($page->template->fieldgroup as $field) {
			if($page->isLoaded($field->name)) continue; // value already set
			if(!$page->hasField($field)) continue; // field not valid for page
			if(!strlen($field->defaultValue)) continue; // no defaultValue property defined with Fieldtype config inputfields
			try {
				$blankValue = $field->type->getBlankValue($page, $field);
				if(is_object($blankValue) || is_array($blankValue)) continue; // we don't currently handle complex types
				$defaultValue = $field->type->getDefaultValue($page, $field);
				if(is_object($defaultValue) || is_array($defaultValue)) continue; // we don't currently handle complex types
				if("$blankValue" !== "$defaultValue") {
					$page->set($field->name, $defaultValue);
				}
			} catch(\Exception $e) {
				$this->trackException($e, false, true); 
			}
		}
	}

	/**
	 * Auto-assign a page name to this page
	 * 
	 * Typically this would be used only if page had no name or if it had a temporary untitled name.
	 * 
	 * Page will be populated with the name given. This method will not populate names to pages that
	 * already have a name, unless the name is "untitled"
	 * 
	 * @param Page $page
	 * @param array $options 
	 * 	- format: Optionally specify the format to use, or leave blank to auto-determine.
	 * @return string If a name was generated it is returned. If no name was generated blank is returned. 
	 * 
	 */
	public function ___setupPageName(Page $page, array $options = array()) {
		
		$defaults = array(
			'format' => '', 
			);
		$options = array_merge($defaults, $options); 
		$format = $options['format']; 
		
		if(strlen($page->name)) {
			// make sure page starts with "untitled" or "untitled-"
			if($page->name != $this->untitledPageName && strpos($page->name, "$this->untitledPageName-") !== 0) {
				// page already has a name and it's not a temporary/untitled one
				// so we do nothing
				return '';
			}
			// page starts with our untitled name, but is it in the exact format we use?
			if($page->name != $this->untitledPageName) {
				$parts = explode('-', $page->name);
				array_shift($parts); // shift off 'untitled';
				$parts = implode('', $parts); // put remaining back together
				// if we were left with something other than digits, 
				// this is not an auto-generated name, so leave as-is
				if(!ctype_digit($parts)) return '';
			}
		}

		if(!strlen($format)) {
			$parent = $page->parent();
			if($parent && $parent->id) $format = $parent->template->childNameFormat;
		}
		
		if(!strlen($format)) {
			if(strlen($page->title)) {
				// default format is title
				$format = 'title';
			} else {
				// if page has no title, default format is date
				$format = 'Y-m-d H:i:s';
			}
		}
		
		$pageName = '';
		
		if(strlen($format)) {
			// @todo add option to auto-gen name from any page property/field

			if($format == 'title') {
				if(strlen($page->title)) $pageName = $page->title;
					else $pageName = $this->untitledPageName;
				
			} else if(!ctype_alnum($format) && !preg_match('/^[-_a-zA-Z0-9]+$/', $format)) {
				// it is a date format
				$pageName = date($format);
			} else {
				
				// predefined format
				$pageName = $format;
			}

		} else if(strlen($page->title)) {
			$pageName = $page->title;

		} else {
			// no name will be assigned
		}
		
		if($pageName == $this->untitledPageName && strpos($page->name, $this->untitledPageName) === 0) {
			// page already has untitled name, and there's no need to re-assign the untitled name
			return '';
		}

		$name = '';
		if(strlen($pageName)) {
			// make the name unique

			$pageName = $this->wire('sanitizer')->pageName($pageName, Sanitizer::translate);
			$numChildren = $page->parent->numChildren();
			$n = 0;

			do {
				$name = $pageName;
				if($n > 0) {
					$nStr = "-" . ($numChildren + $n);
					if(strlen($name) + strlen($nStr) > self::nameMaxLength) $name = substr($name, 0, self::nameMaxLength - strlen($nStr));
					$name .= $nStr;
				}
				$n++;
			} while($n < 100 && $this->count("parent=$page->parent, name=$name, include=all"));

			$page->name = $name;
			$page->set('_hasAutogenName', true); // for savePageQuery, provides adjustName behavior for new pages
		}
		
		return $name;
	}
	
	/**
	 * Save a page object and it's fields to database. 
	 *
	 * If the page is new, it will be inserted. If existing, it will be updated. 
	 *
	 * This is the same as calling $page->save()
	 *
	 * If you want to just save a particular field in a Page, use $page->save($fieldName) instead. 
	 *
	 * @param Page $page
	 * @param array $options Optional array with the following optional elements:
	 * 		'uncacheAll' => boolean - Whether the memory cache should be cleared (default=true)
	 * 		'resetTrackChanges' => boolean - Whether the page's change tracking should be reset (default=true)
	 * 		'quiet' => boolean - When true, modified date and modified_users_id won't be updated (default=false)
	 *		'adjustName' => boolean - Adjust page name to ensure it is unique within its parent (default=false)
	 * 		'forceID' => integer - use this ID instead of an auto-assigned on (new page) or current ID (existing page)
	 * 		'ignoreFamily' => boolean - Bypass check of allowed family/parent settings when saving (default=false)
	 * @return bool True on success, false on failure
	 * @throws WireException
	 *
	 */
	public function ___save(Page $page, $options = array()) {

		$defaultOptions = array(
			'uncacheAll' => true,
			'resetTrackChanges' => true,
			'adjustName' => false, 
			'forceID' => 0,
			'ignoreFamily' => false, 
			);

		if(is_string($options)) $options = Selectors::keyValueStringToArray($options);
		$options = array_merge($defaultOptions, $options); 
		$user = $this->wire('user');
		$languages = $this->wire('languages'); 
		$language = null;

		// if language support active, switch to default language so that saved fields and hooks don't need to be aware of language
		if($languages && $page->id != $user->id) {
			$language = $user->language && $user->language->id ? $user->language : null; 
			if($language) $user->language = $languages->getDefault();
		} 

		$reason = '';
		$isNew = $page->isNew();
		if($isNew) $this->setupNew($page);

		if(!$this->isSaveable($page, $reason, '', $options)) {
			if($language) $user->language = $language;
			throw new WireException("Can't save page {$page->id}: {$page->path}: $reason"); 
		}

		if($page->hasStatus(Page::statusUnpublished) && $page->template->noUnpublish) $page->removeStatus(Page::statusUnpublished); 

		if($page->parentPrevious) {
			if($page->isTrash() && !$page->parentPrevious->isTrash()) $this->trash($page, false); 
				else if($page->parentPrevious->isTrash() && !$page->parent->isTrash()) $this->restore($page, false); 
		}

		if(!$this->savePageQuery($page, $options)) return false;
		$result = $this->savePageFinish($page, $isNew, $options);
		if($language) $user->language = $language; // restore language
		return $result;
	}

	/**
	 * Execute query to save to pages table
	 * 
	 * triggers hooks: saveReady, statusChangeReady (when status changed)
	 * 
	 * @param Page $page
	 * @param array $options
	 * @return bool
	 * @throws WireException|\Exception
	 * 
	 */
	protected function savePageQuery(Page $page, array $options) {
	
		$isNew = $page->isNew();		
		$database = $this->wire('database');
		$user = $this->wire('user');
		$config = $this->wire('config');
		$userID = $user ? $user->id : $config->superUserPageID;
		$systemVersion = $config->systemVersion;
		if(!$page->created_users_id) $page->created_users_id = $userID;
		if($page->isChanged('status')) $this->statusChangeReady($page); 
		$extraData = $this->saveReady($page);
		$sql = '';
	
		if(strpos($page->name, $this->untitledPageName) === 0) $this->setupPageName($page); 

		$data = array(
			'parent_id' => (int) $page->parent_id,
			'templates_id' => (int) $page->template->id,
			'name' => $page->name,
			'status' => (int) $page->status,
			'sort' =>  ($page->sort > -1 ? (int) $page->sort : 0)
			);

		if(is_array($extraData)) foreach($extraData as $column => $value) {
			$column = $database->escapeCol($column);
			$data[$column] = (strtoupper($value) === 'NULL' ? NULL : $value);
		}

		if($isNew) {
			if($page->id) $data['id'] = (int) $page->id;
			$data['created_users_id'] = (int) $userID;
		}
		
		if($options['forceID']) $data['id'] = (int) $options['forceID'];

		if($page->template->allowChangeUser) {
			$data['created_users_id'] = (int) $page->created_users_id;
		}
		
		if(empty($options['quiet'])) {
			$sql = 'modified=NOW()';
			$data['modified_users_id'] = (int) $userID; 
		} else {
			// quiet option, use existing values already populated to page, when present
			$data['modified_users_id'] = (int) ($page->modified_users_id ? $page->modified_users_id : $userID); 
			$data['created_users_id'] = (int) ($page->created_users_id ? $page->created_users_id : $userID);
			if($page->modified > 0) $data['modified'] = date('Y-m-d H:i:s', $page->modified); 
				else if($isNew) $sql = 'modified=NOW()';
			if(!$isNew && $page->created > 0) $data['created'] = date('Y-m-d H:i:s', $page->created); 
		}
		
		if(isset($data['modified_users_id'])) $page->modified_users_id = $data['modified_users_id'];
		if(isset($data['created_users_id'])) $page->created_users_id = $data['created_users_id']; 
		
		if(!$page->isUnpublished() && ($isNew || ($page->statusPrevious && ($page->statusPrevious & Page::statusUnpublished)))) {
			// page is being published
			if($systemVersion >= 12) {
				$sql .= ($sql ? ', ' : '') . 'published=NOW()';
			}
		}
		
		foreach($data as $column => $value) {
			$sql .= ", $column=" . (is_null($value) ? "NULL" : ":$column");
		}
		
		$sql = trim($sql, ", "); 

		if($isNew) {
			$query = $database->prepare("INSERT INTO pages SET $sql, created=NOW()");
		}  else {
			$query = $database->prepare("UPDATE pages SET $sql WHERE id=:page_id");
			$query->bindValue(":page_id", (int) $page->id, \PDO::PARAM_INT);
		}

		foreach($data as $column => $value) {
			if(is_null($value)) continue; // already bound above
			$query->bindValue(":$column", $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
		}

		$n = 0;
		$tries = 0;
		$maxTries = 100;

		do { 
			$result = false; 
			$errorCode = 0;

			try { 	
				$result = false;
				$result = $this->executeQuery($query);

			} catch(\Exception $e) {

				$errorCode = $e->getCode();

				// while setupNew() already attempts to uniqify a page name with an incrementing
				// number, there is a chance that two processes running at once might end up with
				// the same number, so we account for the possibility here by re-trying queries
				// that trigger duplicate-entry exceptions 

				if($errorCode == 23000 && ($page->_hasAutogenName || $options['adjustName'])) {
					// Integrity constraint violation: 1062 Duplicate entry 'background-3552' for key 'name3894_parent_id'
					// attempt to re-generate page name
					$nameField = 'name';
					// account for the duplicate possibly being a multi-language name field
					if($this->wire('languages') && preg_match('/\b(name\d*)_parent_id\b/', $e->getMessage(), $matches)) $nameField = $matches[1]; 
					// get either 'name' or 'name123' (where 123 is language ID)
					$pageName = $page->$nameField;
					// determine if current name format already has a trailing number
					if(preg_match('/^(.+?)-(\d+)$/', $pageName, $matches)) {
						// page already has a trailing number
						$n = (int) $matches[2]; 
						$pageName = $matches[1]; 
					}
					$nStr = '-' . (++$n);
					if(strlen($pageName) + strlen($nStr) > self::nameMaxLength) $pageName = substr($pageName, 0, self::nameMaxLength - strlen($nStr));
					$page->name = $pageName . $nStr;
					$query->bindValue(":$nameField", $page->name); 
					
				} else {
					// a different exception that we don't catch, so re-throw it
					throw $e;
				}
			}

		} while($errorCode == 23000 && (++$tries < $maxTries)); 

		if($result && ($isNew || !$page->id)) $page->id = $database->lastInsertId();
		if($options['forceID']) $page->id = (int) $options['forceID'];
		
		return $result; 
	}

	/**
	 * Save individual Page fields and supporting actions
	 * 
	 * triggers hooks: saved, added, moved, renamed, templateChanged
	 * 
	 * @param Page $page
	 * @param bool $isNew
	 * @param array $options
	 * @return bool
	 * 
	 */
	protected function savePageFinish(Page $page, $isNew, array $options) {
		$changes = $page->getChanges();
		$changesValues = $page->getChanges(true); 
	
		// update children counts for current/previous parent
		if($isNew) {
			$page->parent->numChildren++;
		} else {
			if($page->parentPrevious && $page->parentPrevious->id != $page->parent->id) {
				$page->parentPrevious->numChildren--;
				$page->parent->numChildren++;
			}
		}

		// if page hasn't changed, don't continue further
		if(!$page->isChanged() && !$isNew) {
			$this->debugLog('save', '[not-changed]', true);
			$this->saved($page, array());
			return true;
		}
		
		// if page has a files path (or might have previously), trigger filesManager's save
		if(PagefilesManager::hasPath($page)) $page->filesManager->save();

		// disable outputFormatting and save state
		$of = $page->of();
		$page->of(false);
	
		// when a page is statusCorrupted, it records what fields are corrupted in _statusCorruptedFields array
		$corruptedFields = $page->hasStatus(Page::statusCorrupted) ? $page->_statusCorruptedFields : array();

		// save each individual Fieldtype data in the fields_* tables
		foreach($page->fieldgroup as $field) {
			if(isset($corruptedFields[$field->name])) continue; // don't even attempt save of corrupted field
			if(!$field->type) continue;
			if(!$page->hasField($field)) continue; // field not valid for page
			try {
				$field->type->savePageField($page, $field);
			} catch(\Exception $e) {
				$error = sprintf($this->_('Error saving field "%s"'), $field->name) . ' - ' . $e->getMessage();
				$this->trackException($e, true, $error); 
			}
		}

		// return outputFormatting state
		$page->of($of);

		if(empty($page->template->sortfield)) $this->sortfields->save($page);
		if($options['resetTrackChanges']) $page->resetTrackChanges();
	
		// determine whether we'll trigger the added() hook
		if($isNew) {
			$page->setIsNew(false);
			$triggerAddedPage = $page;
		} else $triggerAddedPage = null;

		// check for template changes
		if($page->templatePrevious && $page->templatePrevious->id != $page->template->id) {
			// the template was changed, so we may have data in the DB that is no longer applicable
			// find unused data and delete it
			foreach($page->templatePrevious->fieldgroup as $field) {
				if($page->hasField($field)) continue;
				$field->type->deletePageField($page, $field);
				$this->message("Deleted field '$field' on page {$page->url}", Notice::debug);
			}
		}

		if($options['uncacheAll']) $this->uncacheAll($page);

		// determine whether the pages_access table needs to be updated so that pages->find()
		// operations can be access controlled. 
		if($isNew || $page->parentPrevious || $page->templatePrevious) $this->wire(new PagesAccess($page));

		// lastly determine whether the pages_parents table needs to be updated for the find() cache
		// and call upon $this->saveParents where appropriate. 
		if($page->parentPrevious && $page->numChildren > 0) {
			// page is moved and it has children
			$this->saveParents($page->id, $page->numChildren);
			if($page->parent->numChildren == 1) $this->saveParents($page->parent_id, $page->parent->numChildren);

		} else if(($page->parentPrevious && $page->parent->numChildren == 1) ||
			($isNew && $page->parent->numChildren == 1) ||
			($page->_forceSaveParents)) {
			// page is moved and is the first child of it's new parent
			// OR page is NEW and is the first child of it's parent
			// OR $page->_forceSaveParents is set (debug/debug, can be removed later)
			$this->saveParents($page->parent_id, $page->parent->numChildren);
		}

		if($page->parentPrevious && $page->parentPrevious->numChildren == 0) {
			// $page was moved and it's previous parent is now left with no children, this ensures the old entries get deleted
			$this->saveParents($page->parentPrevious->id, 0);
		}

		// trigger hooks
		$this->saved($page, $changes, $changesValues);
		if($triggerAddedPage) $this->added($triggerAddedPage);
		if($page->namePrevious && $page->namePrevious != $page->name) $this->renamed($page);
		if($page->parentPrevious) $this->moved($page);
		if($page->templatePrevious) $this->templateChanged($page);
		if(in_array('status', $changes)) $this->statusChanged($page); 

		$this->debugLog('save', $page, true);

		return true; 
	}

	/**
	 * Save just a field from the given page as used by Page::save($field)
	 *
	 * This function is public, but the preferred manner to call it is with $page->save($field)
	 *
	 * @param Page $page
	 * @param string|Field $field Field object or name (string)
	 * @param array|string $options Specify option 'quiet' => true, to bypass updating of modified_users_id and modified time. 
	 * @return bool True on success
	 * @throws WireException
	 *
	 */
	public function ___saveField(Page $page, $field, $options = array()) {

		$reason = '';
		if(is_string($options)) $options = Selectors::keyValueStringToArray($options);
		if($page->isNew()) throw new WireException("Can't save field from a new page - please save the entire page first"); 
		if(!$this->isSaveable($page, $reason, $field, $options)) throw new WireException("Can't save field from page {$page->id}: {$page->path}: $reason"); 
		if($field && (is_string($field) || is_int($field))) $field = $this->wire('fields')->get($field);
		if(!$field instanceof Field) throw new WireException("Unknown field supplied to saveField for page {$page->id}");
		if(!$page->fieldgroup->hasField($field)) throw new WireException("Page {$page->id} does not have field {$field->name}"); 

		$value = $page->get($field->name); 
		if($value instanceof Pagefiles || $value instanceof Pagefile) $page->filesManager()->save();
		$page->trackChange($field->name); 	

		$this->saveFieldReady($page, $field); 
		if($field->type->savePageField($page, $field)) { 
			$page->untrackChange($field->name); 
			if(empty($options['quiet'])) {
				$user = $this->wire('user');
				$userID = (int) ($user ? $user->id : $this->config->superUserPageID);
				$database = $this->wire('database');
				$query = $database->prepare("UPDATE pages SET modified_users_id=:userID, modified=NOW() WHERE id=:pageID"); 
				$query->bindValue(':userID', $userID, \PDO::PARAM_INT);
				$query->bindValue(':pageID', $page->id, \PDO::PARAM_INT);
				$this->executeQuery($query);
			}
			$return = true; 
			$this->savedField($page, $field); 
		} else {
			$return = false; 
		}

		$this->debugLog('saveField', "$page:$field", $return);
		return $return;
	}


	/**
	 * Save references to the Page's parents in pages_parents table, as well as any other pages affected by a parent change
	 *
	 * Any pages_id passed into here are assumed to have children
	 *
	 * @param int $pages_id ID of page to save parents from
	 * @param int $numChildren Number of children this Page has
	 * @param int $level Recursion level, for debugging.
	 * @return bool
	 *
	 */
	protected function saveParents($pages_id, $numChildren, $level = 0) {

		$pages_id = (int) $pages_id; 
		if(!$pages_id) return false; 
		$database = $this->wire('database');

		$query = $database->prepare("DELETE FROM pages_parents WHERE pages_id=:pages_id"); 
		$query->bindValue(':pages_id', $pages_id, \PDO::PARAM_INT); 
		$query->execute();

		if(!$numChildren) return true; 

		$insertSql = ''; 
		$id = $pages_id; 
		$cnt = 0;
		$query = $database->prepare("SELECT parent_id FROM pages WHERE id=:id"); 

		do {
			if($id < 2) break; // home has no parent, so no need to do that query
			$query->bindValue(":id", $id, \PDO::PARAM_INT);
			$query->execute();
			list($id) = $query->fetch(\PDO::FETCH_NUM); 
			$id = (int) $id; 
			if($id < 2) break; // no need to record 1 for every page, since it is assumed
			$insertSql .= "($pages_id, $id),";
			$cnt++; 

		} while(1); 

		if($insertSql) {
			$sql = "INSERT INTO pages_parents (pages_id, parents_id) VALUES" . rtrim($insertSql, ","); 
			$database->exec($sql);
		}

		// find all children of $pages_id that themselves have children
		$sql = 	"SELECT pages.id, COUNT(children.id) AS numChildren " . 
				"FROM pages " . 
				"JOIN pages AS children ON children.parent_id=pages.id " . 
				"WHERE pages.parent_id=:pages_id " . 
				"GROUP BY pages.id ";
		
		$query = $database->prepare($sql);
		$query->bindValue(':pages_id', $pages_id, \PDO::PARAM_INT); 
		$this->executeQuery($query);

		/** @noinspection PhpAssignmentInConditionInspection */
		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
			$this->saveParents($row['id'], $row['numChildren'], $level+1);
		}
		$query->closeCursor();

		return true; 	
	}

	/**
	 * Sets a new Page status and saves the page, optionally recursive with the children, grandchildren, and so on.
	 *
	 * While this can be performed with other methods, this is here just to make it fast for internal/non-api use. 
	 * See the trash and restore methods for an example. 
	 *
	 * @param int $pageID 
	 * @param int $status Status per flags in Page::status* constants. Status will be OR'd with existing status, unless $remove option is set.
	 * @param bool $recursive Should the status descend into the page's children, and grandchildren, etc?
	 * @param bool $remove Should the status be removed rather than added?
	 *
	 */
	protected function savePageStatus($pageID, $status, $recursive = false, $remove = false) {
		
		$pageID = (int) $pageID;
		$status = (int) $status;
		$sql = $remove ? "status & ~$status" : $sql = "status|$status";
		$database = $this->wire('database');

		$query = $database->prepare("UPDATE pages SET status=$sql WHERE id=:page_id");
		$query->bindValue(":page_id", $pageID, \PDO::PARAM_INT);
		$this->executeQuery($query);

		if($recursive) {
			$parentIDs = array($pageID);

			do {
				$parentID = array_shift($parentIDs);

				// update all children to have the same status
				$query = $database->prepare("UPDATE pages SET status=$sql WHERE parent_id=:parent_id");
				$query->bindValue(":parent_id", $parentID, \PDO::PARAM_INT);
				$this->executeQuery($query);

				// locate children that themselves have children
				$query = $database->prepare(
					"SELECT pages.id FROM pages " .
					"JOIN pages AS pages2 ON pages2.parent_id=pages.id " .
					"WHERE pages.parent_id=:parent_id " .
					"GROUP BY pages.id " .
					"ORDER BY pages.sort"
				);
				$query->bindValue(':parent_id', $parentID, \PDO::PARAM_INT);
				$this->executeQuery($query);
				/** @noinspection PhpAssignmentInConditionInspection */
				while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
					$parentIDs[] = (int) $row['id'];
				}
				$query->closeCursor();
			} while(count($parentIDs));
		}	
	}

	/**
	 * Is the given page deleteable?
	 *
	 * Note: this does not account for user permission checking. It only checks if the page is in a state to be saveable via the API. 
	 *
	 * @param Page $page
	 * @return bool True if deleteable, False if not
	 *
	 */
	public function isDeleteable(Page $page) {

		$deleteable = true; 
		if(!$page->id || $page->status & Page::statusSystemID || $page->status & Page::statusSystem) $deleteable = false; 
			else if($page instanceof NullPage) $deleteable = false;

		return $deleteable;
	}

	/**
	 * Move a page to the trash
	 *
	 * If you have already set the parent to somewhere in the trash, then this method won't attempt to set it again. 
	 *
	 * @param Page $page
	 * @param bool $save Set to false if you will perform the save() call, as is the case when called from the Pages::save() method.
	 * @return bool
	 * @throws WireException
	 *
	 */
	public function ___trash(Page $page, $save = true) {
		if(!$this->isDeleteable($page) || $page->template->noTrash) throw new WireException("This page may not be placed in the trash"); 
		if(!$trash = $this->get($this->config->trashPageID)) {
			throw new WireException("Unable to load trash page defined by config::trashPageID"); 
		}
		$page->addStatus(Page::statusTrash); 
		if(!$page->parent->isTrash()) {
			$parentPrevious = $page->parent; 
			$page->parent = $trash;
		} else if($page->parentPrevious && $page->parentPrevious->id != $page->parent->id) {
			$parentPrevious = $page->parentPrevious; 
		} else {
			$parentPrevious = null;
		}
		if(!preg_match('/^' . $page->id . '(\.\d+\.\d+)?_.+/', $page->name)) {
			// make the name unique when in trash, to avoid namespace collision and maintain parent restore info
			$name = $page->id; 
			if($parentPrevious && $parentPrevious->id) {
				$name .= "." . $parentPrevious->id;
				$name .= "." . $page->sort; 
			}
			$name .= "_" . $page->name; 
			$page->name = $name;
		}
		if($save) $this->save($page); 
		$this->savePageStatus($page->id, Page::statusTrash, true, false); 
		$this->trashed($page);
		$this->debugLog('trash', $page, true); 
		return true; 
	}

	/**
	 * Restore a page from the trash back to a non-trash state
	 *
	 * Note that this method assumes already have set a new parent, but have not yet saved.
	 * If you do not set a new parent, then it will restore to the original parent, when possible.
	 *
	 * @param Page $page
	 * @param bool $save Set to false if you only want to prep the page for restore (i.e. being saved elsewhere)
	 * @return bool
	 *
	 */
	protected function ___restore(Page $page, $save = true) {
		
		if(preg_match('/^(' . $page->id . ')((?:\.\d+\.\d+)?)_(.+)$/', $page->name, $matches)) {
	
			if($matches[2]) {
				/** @noinspection PhpUnusedLocalVariableInspection */
				list($unused, $parentID, $sort) = explode('.', $matches[2]);
				$parentID = (int) $parentID;
				$sort = (int) $sort;
			} else {
				$parentID = 0;
				$sort = 0;
			}
			$name = $matches[3]; 
			
			if($parentID && $page->parent->isTrash() && !$page->parentPrevious) {
				// no new parent was defined, so use the one in the page name
				$newParent = $this->get($parentID); 
				if($newParent->id && $newParent->id != $page->id) {
					$page->parent = $newParent; 
					$page->sort = $sort; 
				}
			}
			if(!count($page->parent->children("name=$name, include=all"))) {
				$page->name = $name;  // remove namespace collision info if no collision
			}
		}
	
		if(!$page->parent->isTrash()) {
			$page->removeStatus(Page::statusTrash);
			if($save) $page->save();
			$this->savePageStatus($page->id, Page::statusTrash, true, true);
			$this->restored($page);
			$this->debugLog('restore', $page, true);
		} else {
			if($save) $page->save();
		}
		
		return true; 
	}

	/**
	 * Delete all pages in the trash
	 *
	 * Populates error notices when there are errors deleting specific pages.
	 *
	 * @return int Returns total number of pages deleted from trash.
	 * 	This number is negative or 0 if not all pages could be deleted and error notices may be present.
	 *
	 */
	public function ___emptyTrash() {

		$trashPage = $this->get($this->wire('config')->trashPageID);
		$selector = "include=all, has_parent=$trashPage, children.count=0, status=" . Page::statusTrash;
		$totalDeleted = 0;
		$lastTotalInTrash = 0;
		$numBatches = 0;
		
		do {
			set_time_limit(60 * 10);
			$totalInTrash = $this->count($selector);
			if(!$totalInTrash || $totalInTrash == $lastTotalInTrash) break;
			$lastTotalInTrash = $totalInTrash;
			$items = $this->find("$selector, limit=100");
			$cnt = $items->count();
			foreach($items as $item) {
				try {
					$totalDeleted += $this->delete($item, true);
				} catch(\Exception $e) {
					$this->error($e->getMessage());
				}
			}
			$this->uncacheAll();
			$numBatches++;
		} while($cnt);
		
		// just in case anything left in the trash, use a backup method
		$trashPage = $this->get($trashPage->id); // fresh copy
		$trashPages = $trashPage->children("include=all");
		foreach($trashPages as $t) {
			try {
				$totalDeleted += $this->delete($t, true);
			} catch(\Exception $e) {
				$this->error($e->getMessage());
			}
		}

		$this->uncacheAll();
		if($totalDeleted) {
			$totalInTrash = $this->count("has_parent=$trashPage, include=all, status=" . Page::statusTrash);
			if($totalInTrash) $totalDeleted = $totalDeleted * -1;
		}

		return $totalDeleted;
	}

	/**
	 * Permanently delete a page and it's fields. 
	 *
	 * Unlike trash(), pages deleted here are not restorable. 
	 *
	 * If you attempt to delete a page with children, and don't specifically set the $recursive param to True, then 
	 * this method will throw an exception. If a recursive delete fails for any reason, an exception will be thrown.
	 *
	 * @param Page $page
	 * @param bool $recursive If set to true, then this will attempt to delete all children too.
	 * @param array $options Optional settings to change behavior (for the future)
	 * @return bool|int Returns true (success), or integer of quantity deleted if recursive mode requested.
	 * @throws WireException on fatal error
	 *
	 */
	public function ___delete(Page $page, $recursive = false, array $options = array()) {

		if($options) {} // to ignore unused parameter inspection
		if(!$this->isDeleteable($page)) throw new WireException("This page may not be deleted");
		$numDeleted = 0;

		if($page->numChildren) {
			if(!$recursive) {
				throw new WireException("Can't delete Page $page because it has one or more children."); 
			} else foreach($page->children("include=all") as $child) {
				/** @var Page $child */
				if($this->delete($child, true)) {
					$numDeleted++;
				} else {
					throw new WireException("Error doing recursive page delete, stopped by page $child");
				}
			}
		}

		// trigger a hook to indicate delete is ready and WILL occur
		$this->deleteReady($page); 
	
		foreach($page->fieldgroup as $field) {
			if(!$field->type->deletePageField($page, $field)) {
				$this->error("Unable to delete field '$field' from page '$page'"); 
			}
		}

		try { 
			if(PagefilesManager::hasPath($page)) $page->filesManager->emptyAllPaths(); 
		} catch(\Exception $e) { 
		}

		$access = $this->wire(new PagesAccess());	
		$access->deletePage($page); 

		$database = $this->wire('database');
			
		$query = $database->prepare("DELETE FROM pages_parents WHERE pages_id=:page_id"); 
		$query->bindValue(":page_id", $page->id, \PDO::PARAM_INT); 
		$query->execute();
		
		$query = $database->prepare("DELETE FROM pages WHERE id=:page_id LIMIT 1"); // QA
		$query->bindValue(":page_id", $page->id, \PDO::PARAM_INT); 
		$query->execute();
			
		$this->sortfields->delete($page); 
		$page->setTrackChanges(false); 
		$page->status = Page::statusDeleted; // no need for bitwise addition here, as this page is no longer relevant
		$this->deleted($page);
		$numDeleted++;
		$this->uncacheAll($page);
		$this->debugLog('delete', $page, true);

		return $recursive ? $numDeleted : true;
	}


	/**
	 * Clone an entire page, it's assets and children and return it. 
	 *
	 * @param Page $page Page that you want to clone
	 * @param Page $parent New parent, if different (default=same parent)
	 * @param bool $recursive Clone the children too? (default=true)
	 * @param array|string $options Optional options that can be passed to clone or save
	 * 	- forceID (int): force a specific ID
	 * 	- set (array): Array of properties to set to the clone (you can also do this later)
	 * 	- recursionLevel (int): recursion level, for internal use only. 
	 * @return Page the newly cloned page or a NullPage() with id=0 if unsuccessful.
	 * @throws WireException|\Exception on fatal error
	 *
	 */
	public function ___clone(Page $page, Page $parent = null, $recursive = true, $options = array()) {
		
		if(is_string($options)) $options = Selectors::keyValueStringToArray($options);
		if(!isset($options['recursionLevel'])) $options['recursionLevel'] = 0; // recursion level
			
		// if parent is not changing, we have to modify name now
		if(is_null($parent)) {
			$parent = $page->parent; 
			$n = 1; 
			$name = $page->name . '-' . $n; 
		} else {
			$name = $page->name; 
			$n = 0; 
		}

		// make sure that we have a unique name
		
		while(count($parent->children("name=$name, include=all"))) {
			$name = $page->name; 
			$nStr = "-" . (++$n);
			if(strlen($name) + strlen($nStr) > self::nameMaxLength) $name = substr($name, 0, self::nameMaxLength - strlen($nStr));
			$name .= $nStr;
		}
		
		// Ensure all data is loaded for the page
		foreach($page->fieldgroup as $field) {
			if($page->hasField($field->name)) $page->get($field->name); 
		}

		// clone in memory
		$copy = clone $page; 
		$copy->id = isset($options['forceID']) ? (int) $options['forceID'] : 0; 
		$copy->setIsNew(true); 
		$copy->name = $name; 
		$copy->parent = $parent; 
		
		// set any properties indicated in options	
		if(isset($options['set']) && is_array($options['set'])) {
			foreach($options['set'] as $key => $value) {
				$copy->set($key, $value); 
			}
			if(isset($options['set']['modified'])) {
				$options['quiet'] = true; // allow for modified date to be set
				if(!isset($options['set']['modified_users_id'])) {
					// since 'quiet' also allows modified user to be set, make sure that it
					// is still updated, if not specifically set. 
					$copy->modified_users_id = $this->wire('user')->id; 
				}
			}
			if(isset($options['set']['modified_users_id'])) {
				$options['quiet'] = true; // allow for modified user to be set
				if(!isset($options['set']['modified'])) {
					// since 'quiet' also allows modified tie to be set, make sure that it
					// is still updated, if not specifically set. 
					$copy->modified = time();
				}
			}
		}

		// tell PW that all the data needs to be saved
		foreach($copy->fieldgroup as $field) {
			if($copy->hasField($field)) $copy->trackChange($field->name); 
		}

		$o = $copy->outputFormatting; 
		$copy->setOutputFormatting(false); 
		$this->cloneReady($page, $copy); 
		try {
			$this->cloning = true; 
			$options['ignoreFamily'] = true; // skip family checks during clone
			$this->save($copy, $options);
		} catch(\Exception $e) {
			$this->cloning = false;
			throw $e;
		}
		$this->cloning = false;
		$copy->setOutputFormatting($o); 

		// check to make sure the clone has worked so far
		if(!$copy->id || $copy->id == $page->id) return $this->newNullPage();

		// copy $page's files over to new page
		if(PagefilesManager::hasFiles($page)) {
			$copy->filesManager->init($copy); 
			$page->filesManager->copyFiles($copy->filesManager->path()); 
		}

		// if there are children, then recurisvely clone them too
		if($page->numChildren && $recursive) {
			$start = 0;
			$limit = 200;
			do {
				$children = $page->children("include=all, start=$start, limit=$limit");
				$numChildren = $children->count();
				foreach($children as $child) {
					/** @var Page $child */
					$this->clone($child, $copy, true, array('recursionLevel' => $options['recursionLevel'] + 1));
				}
				$start += $limit;
				$this->uncacheAll();
			} while($numChildren);
		}

		$copy->parentPrevious = null;
		
		// update pages_parents table, only when at recursionLevel 0 since pagesParents is already recursive
		if($recursive && $options['recursionLevel'] === 0) {
			$this->saveParents($copy->id, $copy->numChildren);
		}
			
		$copy->resetTrackChanges();
		$this->cloned($page, $copy); 
		$this->debugLog('clone', "page=$page, parent=$parent", $copy);
	
		return $copy; 	
	}


	/**
	 * Given a Page ID, return it if it's cached, or NULL of it's not. 
	 *
	 * If no ID is provided, then this will return an array copy of the full cache.
	 *
	 * You may also pass in the string "id=123", where 123 is the page_id
	 *
	 * @param int|string|null $id 
	 * @return Page|array|null
	 *
	 */
	public function getCache($id = null) {
		if(!$id) return $this->pageIdCache; 
		if(!ctype_digit("$id")) $id = str_replace('id=', '', $id); 
		if(ctype_digit("$id")) $id = (int) $id; 
		if(!isset($this->pageIdCache[$id])) return null; 
		/** @var Page $page */
		$page = $this->pageIdCache[$id];
		$page->setOutputFormatting($this->outputFormatting); 
		return $page; 
	}

	/**
	 * Cache the given page. 
	 *
	 * @param Page $page
	 *
	 */
	public function cache(Page $page) {
		if($page->id) $this->pageIdCache[$page->id] = $page; 
	}

	/**
	 * Remove the given page from the cache. 
	 *
	 * Note: does not remove pages from selectorCache. Call uncacheAll to do that. 
	 *
	 * @param Page $page Page to uncache
	 * @param array $options Additional options to modify behavior: 
	 * 	- shallow (bool): By default, this method also calls $page->uncache(). 
	 * 	  To prevent call to $page->uncache(), set 'shallow' => true. 
	 *
	 */
	public function uncache(Page $page, array $options = array()) {
		if(empty($options['shallow'])) $page->uncache();
		unset($this->pageIdCache[$page->id]); 
	}

	/**
	 * Remove all pages from the cache. 
	 * 
	 * @param Page $page Optional Page that initiated the uncacheAll
	 *
	 */
	public function uncacheAll(Page $page = null) {

		if($page) {} // to ignore unused parameter inspection
		$this->pageFinder = null;
		$user = $this->wire('user');
		$language = $this->wire('languages') ? $user->language : null;

		unset($this->sortfields); 
		$this->sortfields = $this->wire(new PagesSortfields());

		if($this->config->debug) $this->debugLog('uncacheAll', 'pageIdCache=' . count($this->pageIdCache) . ', pageSelectorCache=' . count($this->pageSelectorCache)); 

		foreach($this->pageIdCache as $id => $page) {
			if($id == $user->id || ($language && $language->id == $id)) continue;
			if(!$page->numChildren) $this->uncache($page); 
		}

		$this->pageIdCache = array();
		$this->pageSelectorCache = array();

		Page::$loadingStack = array();
		Page::$instanceIDs = array(); 
	}

	/**
	 * Cache the given selector string and options with the given PageArray
	 *
	 * @param string $selector
	 * @param array $options
	 * @param PageArray $pages
	 * @return bool True if pages were cached, false if not
	 *
	 */
	protected function selectorCache($selector, array $options, PageArray $pages) {

		// get the string that will be used for caching
		$selector = $this->getSelectorCache($selector, $options, true); 		

		// optimization: don't cache single pages that have an unpublished status or higher
		if(count($pages) && !empty($options['findOne']) && $pages->first()->status >= Page::statusUnpublished) return false; 

		$this->pageSelectorCache[$selector] = clone $pages; 

		return true; 
	}

	/**
	 * Retrieve any cached page IDs for the given selector and options OR false if none found.
	 *
	 * You may specify a third param as TRUE, which will cause this to just return the selector string (with hashed options)
	 *
	 * @param string $selector
	 * @param array $options
	 * @param bool $returnSelector default false
	 * @return array|null|string
	 *
	 */
	protected function getSelectorCache($selector, $options, $returnSelector = false) {

		if(count($options)) {
			$optionsHash = '';
			ksort($options);		
			foreach($options as $key => $value) {
				if(is_array($value)) $value = print_r($value, true); 
				$optionsHash .= "[$key:$value]";
			}
			$selector .= "," . $optionsHash;
		} else $selector .= ",";

		// optimization to use consistent conventions for commonly interchanged names
		$selector = str_replace(array('path=/,', 'parent=/,'), array('id=1,', 'parent_id=1,'), $selector); 

		// optimization to filter out common status checks for pages that won't be cached anyway
		if(!empty($options['findOne'])) {
			$selector = str_replace(array("status<" . Page::statusUnpublished, "status<" . Page::statusMax, 'start=0', 'limit=1', ',', ' '), '', $selector); 
			$selector = trim($selector, ", "); 
		}
	
		// cache non-default languages separately
		if($this->wire('languages')) {
			$language = $this->wire('user')->language;
			if(!$language->isDefault()) {
				$selector .= ", _lang=$language->id"; // for caching purposes only, not recognized by PageFinder
			}
		}

		if($returnSelector) return $selector; 
		if(isset($this->pageSelectorCache[$selector])) return $this->pageSelectorCache[$selector]; 

		return null; 
	}

	/**
	 * For internal Page instance access, return the Pages sortfields property
	 *
	 * @return PagesSortFields
	 *
	 */
	public function sortfields() {
		return $this->sortfields; 
	}

	/**	
 	 * Return a fuel or other property set to the Pages instance
	 * 
	 * @param string $key
	 * @return mixed
	 *
	 */
	public function __get($key) {
		if($key == 'outputFormatting') return $this->outputFormatting; 
		if($key == 'cloning') return $this->cloning; 
		return parent::__get($key); 
	}

	/**
	 * Set whether loaded pages have their outputFormatting turn on or off
	 *
	 * By default, it is turned on. 
	 * 
	 * @param bool $outputFormatting
	 *
	 */
	public function setOutputFormatting($outputFormatting = true) {
		$this->outputFormatting = $outputFormatting ? true : false; 
	}

	/**
	 * Log a Pages class event
	 *
	 * Only active in debug mode. 
	 *
	 * @param string $action Name of action/function that occurred.
	 * @param string $details Additional details, like a selector string. 
	 * @param string|object The value that was returned.
	 *
	 */
	protected function debugLog($action = '', $details = '', $result = '') {
		if(!$this->config->debug) return;
		$this->debugLog[] = array(
			'time' => microtime(),
			'action' => (string) $action, 
			'details' => (string) $details, 
			'result' => (string) $result
			);
	}

	/**
	 * Get the Pages class debug log
	 *
	 * Only active in debug mode
	 *
	 * @param string $action Optional action within the debug log to find
	 * @return array
	 *
	 */
	public function getDebugLog($action = '') {
		if(!$this->config->debug) return array();
		if(!$action) return $this->debugLog; 
		$debugLog = array();
		foreach($this->debugLog as $item) if($item['action'] == $action) $debugLog[] = $item; 
		return $debugLog; 
	}

	/**
	 * Return a PageFinder object, ready to use
	 *
	 * @return PageFinder
	 *
	 */
	public function getPageFinder() {
		return $this->wire(new PageFinder());
	}

	/**
	 * Enable or disable use of autojoin for all queries
	 * 
	 * Default should always be true, and you may use this to turn it off temporarily, but
	 * you should remember to turn it back on
	 * 
	 * @param bool $autojoin
	 * 
	 */
	public function setAutojoin($autojoin = true) {
		$this->autojoin = $autojoin ? true : false;
	}	

	/**
	 * Execute a PDO statement, with additional error handling
	 * 
	 * @param \PDOStatement $query
	 * @param bool $throw Whether or not to throw exception on query error (default=true)
	 * @param int $maxTries Max number of times it will attempt to retry query on error
	 * @return bool
	 * 
	 */
	public function executeQuery(\PDOStatement $query, $throw = true, $maxTries = 3) {
		
		$tryAgain = 0;
		$_throw = $throw;
		
		do {
			try {
				$result = $query->execute();
				
			} catch(\PDOException $e) {
				
				$result = false;
				$error = $e->getMessage();
				$throw = false; // temporarily disable while we try more
				
				if($tryAgain === 0) {
					// setup retry loop
					$tryAgain = $maxTries;
				} else {
					// decrement retry loop
					$tryAgain--;
				}
				
				if(stripos($error, 'MySQL server has gone away') !== false) {
					// forces reconection on next query
					$this->wire('database')->closeConnection(); 
					
				} else if($query->errorCode() == '42S22') {
					// unknown column error
					$errorInfo = $query->errorInfo();
					if(preg_match('/[\'"]([_a-z0-9]+\.[_a-z0-9]+)[\'"]/i', $errorInfo[2], $matches)) {
						$this->unknownColumnError($matches[1]);
					}
					
				} else {
					// some other error that we don't have retry plans for
					// tryAgain=0 will force the loop to stop
					$tryAgain = 0;
				}
				
				if($tryAgain < 1) {
					// if at end of retry loop, restore original throw state
					$throw = $_throw;
				}

				if($throw) {
					throw $e;
				} else {
					$this->error($error);
				}
			}
			
		} while($tryAgain && !$result); 
		
		return $result;
	}

	/**
	 * Return a new/blank PageArray
	 * 
	 * @param array $options Optionally specify array('pageArrayClass' => 'YourPageArrayClass')
	 * @return PageArray
	 * 
	 */
	public function newPageArray(array $options = array()) {
		$class = 'PageArray';
		if(!empty($options['pageArrayClass'])) $class = $options['pageArrayClass'];
		if($this->compat2x && strpos($class, "\\") === false) {
			if(class_exists("\\$class")) $class = "\\$class";
		}
		$class = wireClassName($class, true);
		$pageArray = $this->wire(new $class());
		if(!$pageArray instanceof PageArray) $pageArray = $this->wire(new PageArray());
		return $pageArray;
	}

	/**
	 * Return a new/blank Page object (in memory only)
	 *
	 * @param array $options Optionally specify array('pageClass' => 'YourPageClass')
	 * @return Page
	 *
	 */
	public function newPage(array $options = array()) {
		$class = 'Page';
		if(!empty($options['pageClass'])) $class = $options['pageClass'];
		if($this->compat2x && strpos($class, "\\") === false) {
			if(class_exists("\\$class")) $class = "\\$class";
		}
		$class = wireClassName($class, true);
		if(isset($options['template'])) {
			$template = $options['template'];
			if(!is_object($template)) {
				$template = empty($template) ? null : $this->wire('templates')->get($template);
			}
		} else {
			$template = null;
		}
		$page = $this->wire(new $class($template));
		if(!$page instanceof Page) $page = $this->wire(new Page($template));
		return $page;
	}

	/**
	 * Return a new NullPage
	 * 
	 * @return NullPage
	 * 
	 */
	public function newNullPage() {
		if($this->compat2x && class_exists("\\NullPage")) {
			$page = new \NullPage();
		} else {
			$page = new NullPage();
		}
		$this->wire($page);
		return $page;
	}

	/**
	 * Called when a page-data loading query encounters an unknown column
	 * 
	 * @param string $column Column format tableName.columnName
	 * 
	 */
	protected function ___unknownColumnError($column) {
		if($this->wire('modules')->isInstalled('LanguageSupport')) {
			if(!$this->wire('languages')) $this->wire('modules')->get('LanguageSupport');
		}
		if($this->wire('languages')) {
			$this->wire('languages')->unknownColumnError($column);
		}
	}

	/**
	 * Enables use of $pages(123), $pages('/path/') or $pages('selector string')
	 * 
	 * When given an integer or page path string, it calls $pages->get(key); 
	 * When given a string, it calls $pages->find($key);
	 * When given an array, it calls $pages->getById($key);
	 * 
	 * @param string|int|array $key
	 * @return Page|PageArray
	 *
	 */
	public function __invoke($key) {
		if(empty($key)) return $this;
		if(is_int($key)) return $this->get($key); 
		if(is_array($key)) return $this->getById($key); 
		if(strpos($key, '/') === 0 && ctype_alnum(str_replace(array('/', '-', '_', '.'), '', $key))) return $this->get($key);
		return $this->find($key);
	}

	/**
	 * Save to pages activity log, if enabled in config
	 * 
	 * @param $str
	 * @param Page|null Page to log
	 * @return WireLog
	 * 
	 */
	public function log($str, Page $page) {
		if(!in_array('pages', $this->wire('config')->logs)) return parent::___log();
		if($this->wire('process') != 'ProcessPageEdit') $str .= " [From URL: " . $this->wire('input')->url() . "]";
		$options = array('name' => 'pages', 'url' => $page->path); 
		return parent::___log($str, $options); 
	}

	/**
	 * Hook called after a page is successfully saved
	 *
	 * This is the same as Pages::save, except that it occurs before other save-related hooks (below),
	 * Whereas Pages::save occurs after. In most cases, the distinction does not matter. 
	 * 
	 * @param Page $page The page that was saved
	 * @param array $changes Array of field names that changed
	 * @param array $values Array of values that changed, if values were being recorded, see Wire::getChanges(true) for details.
	 *
	 */
	public function ___saved(Page $page, array $changes = array(), $values = array()) { 
		$str = "Saved page";
		if(count($changes)) $str .= " (Changes: " . implode(', ', $changes) . ")";
		$this->log($str, $page);
		$this->wire('cache')->maintenance($page);
		if($page->className() != 'Page') {
			$manager = $page->getPagesManager();
			if($manager instanceof PagesType) $manager->saved($page, $changes, $values);
		}
	}

	/**
	 * Hook called when a new page has been added
	 * 
	 * @param Page $page
	 *
	 */
	public function ___added(Page $page) { 
		$this->log("Added page", $page);
		if($page->className() != 'Page') {
			$manager = $page->getPagesManager();
			if($manager instanceof PagesType) $manager->added($page);
		}
	}

	/**
	 * Hook called when a page has been moved from one parent to another
	 *
	 * Note the previous parent is in $page->parentPrevious
	 * 
	 * @param Page $page
	 *
	 */
	public function ___moved(Page $page) { 
		if($page->parentPrevious) {
			$this->log("Moved page from {$page->parentPrevious->path}$page->name/", $page);
		} else {
			$this->log("Moved page", $page); 
		}
	}

	/**
	 * Hook called when a page's template has been changed
	 *
	 * Note the previous template is in $page->templatePrevious
	 * 
	 * @param Page $page
	 *
	 */
	public function ___templateChanged(Page $page) {
		if($page->templatePrevious) {
			$this->log("Changed template on page from '$page->templatePrevious' to '$page->template'", $page);
		} else {
			$this->log("Changed template on page to '$page->template'", $page);
		}
	}

	/**
	 * Hook called when a page has been moved to the trash
	 * 
	 * @param Page $page
	 *
	 */
	public function ___trashed(Page $page) { 
		$this->log("Trashed page", $page);
	}

	/**
	 * Hook called when a page has been moved OUT of the trash
	 * 
	 * @param Page $page
	 *
	 */
	public function ___restored(Page $page) { 
		$this->log("Restored page", $page); 
	}

	/**
	 * Hook called just before a page is saved
	 *
	 * May be preferable to a before(save) hook because you know for sure a save will 
	 * be executed immediately after this is called. Whereas you don't necessarily know
 	 * that when before(save) is called, as an error may prevent it. 
	 *
	 * @param Page $page The page about to be saved
	 * @return array Optional extra data to add to pages save query.
	 *
	 */
	public function ___saveReady(Page $page) {
		$data = array();
		if($page->className() != 'Page') {
			$manager = $page->getPagesManager();
			if($manager instanceof PagesType) $data = $manager->saveReady($page);
		}
		return $data;
	}

	/**
	 * Hook called when a page is about to be deleted, but before data has been touched
	 *
	 * This is different from a before(delete) hook because this hook is called once it has 
	 * been confirmed that the page is deleteable and WILL be deleted. 
	 * 
	 * @param Page $page
	 *
	 */
	public function ___deleteReady(Page $page) {
		if($page->className() != 'Page') {
			$manager = $page->getPagesManager();
			if($manager instanceof PagesType) $manager->deleteReady($page);
		}
	}

	/**
	 * Hook called when a page and it's data have been deleted
	 * 
	 * @param Page $page
	 *
	 */
	public function ___deleted(Page $page) { 
		$this->log("Deleted page", $page); 
		$this->wire('cache')->maintenance($page);
		if($page->className() != 'Page') {
			$manager = $page->getPagesManager();
			if($manager instanceof PagesType) $manager->deleted($page);
		}
	}

	/**
	 * Hook called when a page is about to be cloned, but before data has been touched
	 *
	 * @param Page $page The original page to be cloned
	 * @param Page $copy The actual clone about to be saved
	 *
	 */
	public function ___cloneReady(Page $page, Page $copy) { }

	/**
	 * Hook called when a page has been cloned
	 *
	 * @param Page $page The original page to be cloned
	 * @param Page $copy The completed cloned version of the page
	 *
	 */
	public function ___cloned(Page $page, Page $copy) { 
		$this->log("Cloned page to $copy->path", $page); 
	}

	/**
	 * Hook called when a page has been renamed (i.e. had it's name field change)
	 *
	 * The previous name can be accessed at $page->namePrevious;
	 * The new name can be accessed at $page->name
	 *
	 * This hook is only called when a page's name changes. It is not called when
	 * a page is moved unless the name was changed at the same time. 
	 *
	 * @param Page $page The $page that was renamed
	 *
	 */
	public function ___renamed(Page $page) { 
		$this->log("Renamed page from '$page->namePrevious' to '$page->name'", $page); 
	}

	/**
	 * Hook called when a page status has been changed and saved
	 *
	 * Previous status may be accessed at $page->statusPrevious
	 *
	 * @param Page $page 
	 *
	 */
	public function ___statusChanged(Page $page) {
		$status = $page->status; 
		$statusPrevious = $page->statusPrevious; 
		$isPublished = !$page->isUnpublished();
		$wasPublished = !($statusPrevious & Page::statusUnpublished);
		if($isPublished && !$wasPublished) $this->published($page);
		if(!$isPublished && $wasPublished) $this->unpublished($page);
	
		$from = array();
		$to = array();
		foreach(Page::getStatuses() as $name => $flag) {
			if($flag == Page::statusUnpublished) continue; // logged separately
			if($statusPrevious & $flag) $from[] = $name;
			if($status & $flag) $to[] = $name; 
		}
		if(count($from) || count($to)) {
			$added = array();
			$removed = array();
			foreach($from as $name) if(!in_array($name, $to)) $removed[] = $name;
			foreach($to as $name) if(!in_array($name, $from)) $added[] = $name;
			$str = '';
			if(count($added)) $str = "Added status '" . implode(', ', $added) . "'";
			if(count($removed)) {
				if($str) $str .= ". ";
				$str .= "Removed status '" . implode(', ', $removed) . "'";
			}
			if($str) $this->log($str, $page);
		}
	}

	/**
	 * Hook called when a page's status is about to be changed and saved
	 *
	 * Previous status may be accessed at $page->statusPrevious
	 *
	 * @param Page $page 
	 *
	 */
	public function ___statusChangeReady(Page $page) {
		$isPublished = !$page->isUnpublished();
		$wasPublished = !($page->statusPrevious & Page::statusUnpublished);
		if($isPublished && !$wasPublished) $this->publishReady($page);
		if(!$isPublished && $wasPublished) $this->unpublishReady($page);
	}

	/**
	 * Hook called after an unpublished page has just been published
	 *
	 * @param Page $page 
	 *
	 */
	public function ___published(Page $page) { 
		$this->log("Published page", $page); 
	}

	/**
	 * Hook called after published page has just been unpublished
	 *
	 * @param Page $page 
	 *
	 */
	public function ___unpublished(Page $page) { 
		$this->log("Unpublished page", $page); 
	}

	/**
	 * Hook called right before an unpublished page is published and saved
	 *
	 * @param Page $page 
	 *
	 */
	public function ___publishReady(Page $page) { }

	/**
	 * Hook called right before a published page is unpublished and saved
	 *
	 * @param Page $page 
	 *
	 */
	public function ___unpublishReady(Page $page) { }

	/**
	 * Hook called at the end of a $pages->find(), includes extra info not seen in the resulting PageArray
	 *
	 * @param PageArray $pages The pages that were found
	 * @param array $details Extra information on how the pages were found, including: 
	 * 	- PageFinder $pageFinder The PageFinder instance that was used
	 * 	- array $pagesInfo The array returned by PageFinder
	 * 	- array $options Options that were passed to $pages->find()
	 *
	 */
	public function ___found(PageArray $pages, array $details) { }

	/**
	 * Hook called when Pages::saveField is going to execute
	 * 
	 * @param Page $page
	 * @param Field $field
	 * 
	 */
	public function ___saveFieldReady(Page $page, Field $field) { }

	/**
	 * Hook called after Pages::saveField successfully executes
	 * 
	 * @param Page $page
	 * @param Field $field
	 * 
	 */
	public function ___savedField(Page $page, Field $field) { 
		$this->log("Saved page field '$field->name'", $page); 
	}

}


