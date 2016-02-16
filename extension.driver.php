<?php
Class extension_author_roles extends Extension
{

	// Set the delegates:
	public function getSubscribedDelegates()
	{
		return array(
			array(
				'page' => '/backend/',
				'delegate' => 'NavigationPreRender',
				'callback' => 'extendNavigation'
			),
			array(
				'page' => '/system/authors/',
				'delegate' => 'AuthorPreDelete',
				'callback' => 'deleteAuthorRole'
			),
			array(
				'page' => '/system/authors/',
				'delegate' => 'AuthorPostCreate',
				'callback' => 'saveAuthorRole'
			),
			array(
				'page' => '/system/authors/',
				'delegate' => 'AuthorPostEdit',
				'callback' => 'saveAuthorRole'
			),
			array(
				'page' => '/system/authors/',
				'delegate' => 'AddElementstoAuthorForm',
				'callback' => 'addRolePicker'
			),
			array(
				'page' => '/backend/',
				'delegate' => 'AdminPagePreGenerate',
				'callback' => 'checkCallback'
			),
			array(
				'page' => '/system/authors/',
				'delegate' => 'AddDefaultAuthorAreas',
				'callback' => 'modifyAreas'
			),
			array(
				'page' => '/publish/',
				'delegate' => 'AdjustPublishFiltering',
				'callback' => 'adjustPublishFiltering'
			),
			array(
				'page' => '/publish/edit/',
				'delegate' => 'EntryPreRender',
				'callback' => 'entryPreRender'
			),
			array(
				'page' => '/publish/edit/',
				'delegate' => 'EntryPreEdit',
				'callback' => 'entryPreEdit'
			),
			array(
				'page' => '/publish/new/',
				'delegate' => 'EntryPreCreate',
				'callback' => 'entryPreCreate'
			),
			array(
				'page' => '/publish/',
				'delegate' => 'EntryPreDelete',
				'callback' => 'entryPreDelete'
			),
		);
	}

	/**
	 * Check if the current user is the Author of the entry
	 * @param $entry - the entry to be checked
	 * @param $rules - the rules applying to this section
	 * @return boolean
	 */
	private function isOwnEntry($entry,$rules){
		$sectionId = $entry->get('section_id');

		$authorId = Symphony::Author()->get('id');
		$authorName = Symphony::Author()->get('first_name') . ' ' . Symphony::Author()->get('last_name');

		$canAccess = true;

		$fieldId = FieldManager::fetchFieldIDFromElementName('authors',$sectionId);
		// $field = FieldManager::fetch($fieldId);

		$fieldType = FieldManager::fetchFieldTypeFromID($fieldId);

		if ($field){
			
			$fieldData = $entry->getData($fieldId);

			if ($fieldType == 'author'){ 
				//use symphony author
				$canAccess = ($fieldData['author_id'] == $authorId);
			} else {
				$fieldValue = $fieldData['value'];
				if (!is_array($fieldValue)){
					$fieldValue = array($fieldValue);
				}
				$fieldValue = array_map('strtolower', $fieldValue);

				if ( in_array(strtolower($authorName), $fieldValue)){
					$canAccess = true;
				} else {
					$canAccess = false;
				}
			}
		} else {
			//use symphony author
			$canAccess  = ($entry->get('author_id') == $authorId);
		}

		return $canAccess;
	}

	/**
	 * Check if the rules permit or deny access to the entry id
	 * @param $entryId - the entry id to be checked
	 * @param $rules - the rules applying to this section
	 * @return boolean
	 */
	private function filterCanAccess($entryId,$rules){
		$rule = explode(':', $rules['filter_rule']);

		$canAccess = true;

		if(count($rule) == 2) {
			// Valid filter, now get the ID's:
			$filteredIDs = array();
			$action = $rule[0];
			$idsArr = explode(',', $rule[1]);

			foreach($idsArr as $idExpr) {
				$a = explode('-', trim($idExpr));

				if(count($a)==1) {
					// Regular ID
					$filteredIDs[] = $a[0];
				}
				elseif(count($a)==2) {
					// Range
					$from = $a[0];
					$to   = $a[1];

					if($to >= $from) {
						for($i = $from; $i <= $to; $i++){
							$filteredIDs[] = $i;
						}
					}
				}
			}

			switch($action) {
				case 'show' :
					// Only show the given ids.
					if ( in_array($entryId, $filteredIDs)){
						$canAccess = true;
					} else {
						$canAccess = false;
					}
					break;
				case 'hide' :
					// Only show entries which do not have the given ids.
					if ( !in_array($entryId, $filteredIDs)){
						$canAccess = true;
					} else {
						$canAccess = false;
					}
					break;
			}
		}

		return $canAccess;
	}

	/**
	 * Check that the current user has access prior to deleting an entry
	 */
	public function entryPreDelete($context) {
		$data = $this->getCurrentAuthorRoleData();

		if($data == false || Symphony::Author()->isDeveloper()) {
			return;
		}

		$section = Symphony::Engine()->getPageCallback()['context']['section_handle'];

		foreach($data['sections'] as $id_section => $rules) {
			if($rules['handle'] == $section) {

				$canAccess = true;

				if($rules['delete'] == 0) {
					$canAccess = false;
				}
				
				if ( !$canAccess ){
					//throw custom error if access to entry is not allowed
					Administration::instance()->throwCustomError(
						__('Restricted Access'),
						__('You are not allowed to delete entries in this section'),
						Page::HTTP_STATUS_NOT_FOUND
					);
				}
			}
		}
	}

	/**
	 * Check that the current user has access prior to showing creating an entry
	 */
	public function entryPreCreate($context) {

		$data = $this->getCurrentAuthorRoleData();

		if($data == false || Symphony::Author()->isDeveloper()) {
			return;
		}

		$section = Symphony::Engine()->getPageCallback()['context']['section_handle'];

		foreach($data['sections'] as $id_section => $rules) {
			if($rules['handle'] == $section) {

				$canAccess = true;

				if($rules['create'] == 0) {
					$canAccess = false;
				}
				
				if ( !$canAccess ){
					//throw custom error if access to entry is not allowed
					Administration::instance()->throwCustomError(
						__('Restricted Access'),
						__('You are not allowed to create entries in this section'),
						Page::HTTP_STATUS_NOT_FOUND
					);
				}
			}

		}
	}

	/**
	 * Check that the current user has access prior to saving entry edits
	 */
	public function entryPreEdit($context) {

		$data = $this->getCurrentAuthorRoleData();

		if($data == false || Symphony::Author()->isDeveloper()) {
			return;
		}

		$section = Symphony::Engine()->getPageCallback()['context']['section_handle'];

		foreach($data['sections'] as $id_section => $rules) {
			if($rules['handle'] == $section) {

				$canAccess = true;

				if($rules['edit'] == 0) {
					$canAccess = false;
				}

				if($rules['own_entries'] == 1 && $canAccess) {
					$canAccess = $this->isOwnEntry();
				}

				// Add or remove ID's from the filter:
				if($rules['use_filter'] == 1 && $canAccess) {
					$canAccess = $this->filterCanAccess($context['entry']->get('id'),$rules);
				}
				
				if ( !$canAccess ){
					//throw custom error if access to entry is not allowed
					Administration::instance()->throwCustomError(
						__('Restricted Access'),
						__('You do not have permissions to edit entry %s.', array($context['entry']->get('id'))),
						Page::HTTP_STATUS_NOT_FOUND
					);
				}
			}

		}
	}

	/**
	 * Check that the current user has view or edit access prior to showing the entry edit screen
	 */
	public function entryPreRender($context) {

		$data = $this->getCurrentAuthorRoleData();

		if($data == false || Symphony::Author()->isDeveloper()) {
			return;
		}

		$section = Symphony::Engine()->getPageCallback()['context']['section_handle'];

		foreach($data['sections'] as $id_section => $rules) {
			if($rules['handle'] == $section) {

				$canAccess = true;

				if($rules['visible'] == 0 && $rules['edit'] == 0) {
					$canAccess = false;
				}

				if($rules['own_entries'] == 1 && $canAccess) {
					$canAccess = $this->isOwnEntry();
				}

				// Add or remove ID's from the filter:
				if($rules['use_filter'] == 1 && $canAccess) {
					$canAccess = $this->filterCanAccess($context['entry']->get('id'),$rules);
				}
				
				if ( !$canAccess ){
					//throw custom error if access to entry is not allowed
					Administration::instance()->throwCustomError(
						__('Unknown Entry'),
						__('The Entry, %s, could not be found.', array($context['entry']->get('id'))),
						Page::HTTP_STATUS_NOT_FOUND
					);
				}
			}

		}
		// var_dump($context);die;
	}

	/**
	* Ensure that any required filtering is done to show only entries which the user has access to in the list views
	*/
	public function adjustPublishFiltering($context) {
		// var_dump($context);die;

		$authorId = Symphony::Author()->get('id');
		$authorName = Symphony::Author()->get('first_name') . ' ' . Symphony::Author()->get('last_name');

		$data = $this->getCurrentAuthorRoleData();

		if($data == false || Symphony::Author()->isDeveloper()) {
			return;
		}

		$section = Symphony::Engine()->getPageCallback()['context']['section_handle'];

		foreach($data['sections'] as $id_section => $rules) {
			if($rules['handle'] == $section) {

				if($rules['visible'] == 0) {
					Administration::instance()->throwCustomError(
						__('Unknown Section'),
						__('The Section, %s, could not be found.', array($section)),
						Page::HTTP_STATUS_NOT_FOUND
					);
				}

				if($rules['own_entries'] == 1) {
					// Only get the ID's of the current author to begin with:

					//if section has field Author -- defined by config
					// $sectionObject = SectionManager::fetch($id_section);
					
					$fieldId = FieldManager::fetchFieldIDFromElementName('authors',$id_section);
					$field = FieldManager::fetch($fieldId);

					$fieldType = FieldManager::fetchFieldTypeFromID($fieldId);

					if ($field){
						$context['joins'] .= " LEFT JOIN `tbl_entries_data_{$fieldId}` as `a{$fieldId}` on `e`.`id` = `a{$fieldId}`.`entry_id` ";
						if ($fieldType == 'author'){ 
							//use symphony author
							$context['where'] .= " AND `a{$fieldId}`.`author_id` = '{$authorId}'";
						} else {
							$context['where'] .= " AND `a{$fieldId}`.`value` = '{$authorName}'";
						}
					} else {
						//use symphony author
						$context['where'] .= " AND `e`.`author_id` = '{$authorId}'";
					}
					// $results = Symphony::Database()->fetch('SELECT `id` FROM `tbl_entries` WHERE `author_id`');
				}


				// Add or remove ID's from the filter:
				if($rules['use_filter'] == 1) {
					$rule = explode(':', $rules['filter_rule']);

					if(count($rule) == 2) {
						// Valid filter, now get the ID's:
						$filteredIDs = array();
						$action = $rule[0];
						$idsArr = explode(',', $rule[1]);

						foreach($idsArr as $idExpr) {
							$a = explode('-', trim($idExpr));

							if(count($a)==1) {
								// Regular ID
								$filteredIDs[] = $a[0];
							}
							elseif(count($a)==2) {
								// Range
								$from = $a[0];
								$to   = $a[1];

								if($to >= $from) {
									for($i = $from; $i <= $to; $i++){
										$filteredIDs[] = $i;
									}
								}
							}
						}

						$filteredIDs = MySQL::cleanValue(implode(',',$filteredIDs));

						switch($action) {
							case 'show' :
								// Only show the given ids.
								$context['where'] .= " AND `e`.`id` IN ('{$filteredIDs}')";
								break;
							case 'hide' :
								// Only show entries which do not have the given ids.
								$context['where'] .= " AND `e`.`id` NOT IN ('{$filteredIDs}')";
								break;
						}
					}
				}



			}
		}
	}

	/**
	* Add Author Roles Navigation Items
	*/
	public function fetchNavigation() {
		return array(
			array(
				'name' => __('Author Roles'),
				'location' => 100,
				'limit' => 'developer'
			)
		);
	}

	/** Extend the navigation. Adds a 'Roles'-button to the system-tab, and hides hidden sections from the navigation
	 * @param	$context
	 *  The context object
	 */
	public function extendNavigation($context) {
		$data = $this->getCurrentAuthorRoleData();

		if($data == false || Symphony::Author()->isDeveloper()) {
			return;
		}

		$custom_elements = explode("\n", $data['custom_elements']);

		foreach($custom_elements as &$elem) {
			$elem = trim($elem);
		}

		// Disable non-visible sections:
		$visible = array();

		foreach($data['sections'] as $id => $rules) {
			if($rules['visible'] == 1) {
				array_push($visible, $id);
			}
		}

		foreach($context['navigation'] as &$group) {
			foreach($group['children'] as &$section) {
				if(isset($section['section'])) {
					if(!in_array($section['section']['id'], $visible)) {
						$section['visible'] = 'no';
					}
				}

				if(isset($section['link'])) {
					if(in_array($section['link'], $custom_elements)) {
						$section['visible'] = 'no';
					}
				}
			}
		}
	}

	/**
	 * Delete the links to this author
	 * @param	$context
	 *  Can be a Symphony Context object, an id of an author, or an array with author-id's
	 */
	public function deleteAuthorRole($context) {
		// When a new author is created $context is false:
		if($context == false) {
			return;
		}

		// When a bulk action delete is performed the ID's are stored in this manner:
		if(isset($context['author_ids'])) {
			$context = $context['author_ids'];
		}

		// When 'Delete' is clicked in the author-edit screen:
		if(isset($context['author_id'])) {
			$context = array($context['author_id']);
		}

		// When a single ID is provided:
		if(!is_array($context)) {
			$context = array($context);
		}

		// Delete the links:
		foreach($context as $id_author) {
			Symphony::Database()->query('DELETE FROM `tbl_author_roles_authors` WHERE `id_author` = '.$id_author.';');
		}
	}

	/**
	 * Add the role field to the author-form:
	 * @param	$context
	 *  The context, providing the form and the author object
	 */
	public function addRolePicker($context) {
		if(Symphony::Author()->isDeveloper()) {
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', __('Author Role')));

			$div = new XMLElement('div');

			$label = Widget::Label(__('This author belongs to'));

			$options = array(
				array(0, false, __('No role assigned'))
			);

			$roles = $this->getRoles();

			// See which role this user has:
			$id_role = $context['author']->get('id') != false ? Symphony::Database()->fetchVar('id_role', 0, 'SELECT `id_role` FROM `tbl_author_roles_authors` WHERE `id_author` = '.$context['author']->get('id').';') : 0;

			foreach($roles as $role) {
				$options[] = array($role['id'], $role['id'] == $id_role, $role['name']);
			}

			$label->appendChild(Widget::Select('fields[role]', $options));

			$div->appendChild($label);
			$div->appendChild(new XMLElement('p', __('<strong>Please note:</strong> According to the role you selected, some sections may not be visible to this author. If the <strong>Default Area</strong> of this author is set to one of these hidden sections, it will result in an authorization error message when he or she logs in. Be aware to set the Default Area to an area the author is allowed to see.')));
			$group->appendChild($div);

			$i = 0;

			foreach($context['form']->getChildren() as $formChild) {
				if($formChild->getName() != 'fieldset') {
					// Inject here:
					$context['form']->insertChildAt($i, $group);
				}

				$i++;
			}
		}
	}

	/**
	 * Perform certain actions according to the page callback
	 * @param	$context
	 *  The context object
	 */
	public function checkCallback($context) {
		$callback = Symphony::Engine()->getPageCallback();

		// Perform an action according to the callback:
		switch($callback['driver']) {
			case 'publish' :
				// The Publish Screen:
				switch($callback['context']['page']) {
					case 'index' :
						// The index:
						$this->adjustIndex($context, $callback);
						break;
					case 'new' :
					case 'edit' :
						// The Entry Editor:
						$this->adjustEntryEditor($context, $callback);
						break;
				}
				break;
		}
	}

	/**
	 * Adjust the publish index screen
	 * @param $context
	 *  The context
	 * @param $callback
	 *  The callback
	 * @return mixed
	 */
	private function adjustIndex($context, $callback) {


		$data = $this->getCurrentAuthorRoleData();

		if($data == false || Symphony::Author()->isDeveloper()) {
			return;
		}

		// Set the hidden fields:
		$hiddenFields = array();

		foreach($data['fields'] as $id_field => $rules) {
			if($rules['hidden'] == 1) {
				// This field is hidden.
				array_push($hiddenFields, $id_field);
			}
		}

		$section = $callback['context']['section_handle'];

		foreach($data['sections'] as $id_section => $rules) {
			if($rules['handle'] == $section) {
				// Check if there are rules:
				if($rules['create'] == 0) {
					// It is not allowed to create new items:
					$topMenu = current($context['oPage']->Context->getChildrenByName('ul'));
					$children = $topMenu->getChildrenByName('li');

					foreach($children as $key => $child) {
						var_dump($child->getValue()->getValue());
						if(strpos($child->getValue()->getValue(),__('Create New')) !== false) {
							$topMenu->removeChildAt($key);
						}
					}
				}
			}
		}
	}

	// return all ancestors of the given element with the given names
	// names can be a comma seperated list or an array of strings
	private static function findChildren($element, $names) {
		if(!is_array($names)) {
			$names = explode(',', $names);
		}

		$children = array();

		if (!is_object($element)){
			return $children;
		}

		foreach($element->getIterator() as $child) {
			$children = array_merge($children, self::findChildren($child,$names));

			if(in_array($child->getName(), $names )) {
				$children[] = $child;
			}
		}

		return $children;
	}

	// replaces the first ancestor of the first argument which
	// name matches the name of the second argument with
	// the second argument
	private static function replaceChild($parent, $child) {
		foreach($parent->getIterator() as $position => $oldChild) {
			if($oldChild->getName() == $child->getName()) {
				$parent->replaceChildAt($position,$child);

				return true;
			}

			if(self::replaceChild($oldChild, $child)) {
				return true;
			}
		}
		return false;
	}
	/** Adjust the entry editor on the publish page
	 * @param	$context
	 *  Provided with a page object
	 * @param	$callback
	 *  The current callback
	 */
	private function adjustEntryEditor($context, $callback) {
		$data = $this->getCurrentAuthorRoleData();

		if($data == false || Symphony::Author()->isDeveloper()) {
			return;
		}

		// Set the hidden fields:
		$hiddenFields = array();

		foreach($data['fields'] as $id_field => $rules) {
			if($rules['hidden'] == 1) {
				// This field is hidden.
				array_push($hiddenFields, $id_field);
			}
		}

		// Fields should be hidden with JavaScript, so the data still gets posted when saving an entry:
		$script = new XMLElement('script', 'var roles_hidden_fields = ['.implode(',', $hiddenFields).'];', array('type'=>'text/javascript'));
		$context['oPage']->addElementToHead($script);
		$context['oPage']->addScriptToHead(URL.'/extensions/author_roles/assets/author_roles.js');

		$section = $callback['context']['section_handle'];

		foreach($data['sections'] as $id_section => $rules) {
			if($rules['handle'] == $section) {
				if($rules['edit'] == 0 || $rules['delete'] == 0 || $rules['create'] == 0) {
					// Hide the delete button when the author is not allowed to delete:
					// Hide the submit-button when the author is not allowed to create or edit:
					$newContents = new XMLElement('div', null, $context['oPage']->Contents->getAttributes());

					foreach($context['oPage']->Contents->getChildren() as $contentsChild) {
						if($contentsChild->getName() == 'form') {
							$newForm = new XMLElement('form', null, $contentsChild->getAttributes());

							foreach($contentsChild->getChildren() as $formChild) {
								if($formChild->getName() == 'div' && $formChild->getAttribute('class') == 'actions') {
									$actionDiv = new XMLElement('div', null, $formChild->getAttributes());

									foreach($formChild->getChildren() as $actionChild) {
										if($actionChild->getAttribute('name') == 'action[save]') {
											// Check whether this is a create or an edit:
											if(
											   ($callback['context']['page'] == 'edit' && $rules['edit'] == 1) ||
											   ($callback['context']['page'] == 'new' && $rules['create'] == 1))
											{
												$actionDiv->appendChild($actionChild);
											}
										}
										elseif($actionChild->getAttribute('name') == 'action[delete]' && $rules['delete'] == 1) {
											$actionDiv->appendChild($actionChild);
										}
									}

									if($rules['edit'] == 1|| $rules['create'] == 1) {
										$newForm->appendChild($actionDiv);
									}
								}
								else {
									$newForm->appendChild($formChild);
								}
							}

							$newContents->appendChild($newForm);
							$context['oPage']->Form = $newForm;
						}
						else {
							$newContents->appendChild($contentsChild);
						}
					}

					$context['oPage']->Contents = $newContents;
				}
			}
		}
	}

	/**
	 * Get the role of the current logged in author
	 * @return array|boolean
	 *  An associated array with all the information you need, or false if no role is assigned
	 */
	private function getCurrentAuthorRoleData() {
		if(Administration::instance()->isLoggedIn()) {
			$id_author = Symphony::Author()->get('id');
			$id_role   = $this->getAuthorRole($id_author);

			if($id_role != false) {
				$data = $this->getData($id_role);
				return $data;
			}
			else {
				return false;
			}
		}
	}

	/**
	 * Change the dropdown with the default author areas
	 * @param	$context
	 *  The context
	 */
	public function modifyAreas($context) {
		$data = $this->getCurrentAuthorRoleData();

		if($data == false || Symphony::Author()->isDeveloper()) {
			return;
		}

		$newOptions = array();

		foreach($data['sections'] as $id_section => $rules) {
			if($rules['visible'] == 1) {
				$newOptions[] = array($id_section, $id_section == $context['default_area'], $rules['name']);
			}
		}

		$context['options'] = $newOptions;
	}

	/**
	 * Save the role to this author. This is send after the save-button is clicked on the author-screen.
	 * @param	$context
	 *  The context
	 */
	public function saveAuthorRole($context) {
		if(Symphony::Author()->isDeveloper()) {
			$id_role = intval($_POST['fields']['role']);
			$id_author = $context['author']->get('id');

			if($id_author == null) {
				// This author has just been created, get the ID of this newly created author:
				$id_author = Symphony::Database()->fetchVar('id', 0, 'SELECT `id` FROM `tbl_authors` WHERE `username` = \''.$context['author']->get('username').'\';');
			}

			// Delete previously set roles:
			$this->deleteAuthorRole($id_author);

			if($id_role != 0) {
				// Insert new role:
				Symphony::Database()->insert(array('id_role'=>$id_role, 'id_author'=>$id_author), 'tbl_author_roles_authors');
			}
		}
	}

	/**
	 * Get the roles
	 * @return	array
	 *  An array with the roles
	 */
	public function getRoles() {
		$roles = Symphony::Database()->fetch('SELECT * FROM `tbl_author_roles` ORDER BY `name` ASC');

		return $roles;
	}

	/**
	 * Get the authors from a specific role:
	 * @param	$id_role
	 *  The ID of the role
	 * @return	array
	 *  An array with the id, first_name and last_name of the authors
	 */
	public function getAuthors($id_role) {
		$authors = Symphony::Database()->fetch('
			SELECT
				A.`id`,
				A.`first_name`,
				A.`last_name`
			FROM
				`tbl_authors` A,
				`tbl_author_roles_authors` B
			WHERE
				B.`id_role` = '.$id_role.' AND
				B.`id_author` = A.`id`;');

		return $authors;
	}

	/**
	 * Get the role of this author
	 * @param	$id_author
	 *  The ID of the author
	 * @return  int
	 *  The role ID
	 */
	public function getAuthorRole($id_author) {
		$id_role = Symphony::Database()->fetchVar('id_role', 0, 'SELECT `id_role` FROM `tbl_author_roles_authors` WHERE `id_author` = '.$id_author.';');

		return $id_role;
	}

	/**
	 * Load the data
	 * @param	$id_role
	 *  int	The ID of the Role
	 * @return array|boolean
	 *  An associated array with all the information you need of this role, or false of no role is supplied
	 */
	public function getData($id_role) {
		if($id_role != false) {
			$data = array();
			// The name of the role:
			$roleResult = Symphony::Database()->fetch('SELECT * FROM `tbl_author_roles` WHERE `id` = '.$id_role.';');
			$data['name'] = $roleResult[0]['name'];
			$data['custom_elements'] = $roleResult[0]['custom_elements'];

			// Get sections from the section manager:
			$availableSections = SectionManager::fetch();
//			print_r($availableSections);

			// The associated sections:
			$sections = Symphony::Database()->fetch('
				SELECT
					A.`id_section`,
					A.`visible`,
					A.`create`,
					A.`edit`,
					A.`delete`,
					A.`own_entries`,
					A.`use_filter`,
					A.`filter_rule`
				FROM
					`tbl_author_roles_sections` A
				WHERE
					A.`id_role` = '.$id_role.'
				;');

			$data['sections'] = array();

			foreach($sections as $section) {
				$id_section = $section['id_section'];
				unset($section['id_section']);

				foreach($availableSections as $s) {
					/* @var $s Section */
					if($s->get('id') == $id_section) {
						$section['name'] = $s->get('name');
						$section['handle'] = $s->get('handle');
					}
				}

				$data['sections'][$id_section] = $section;
			}

			// The fields:
			$fields = Symphony::Database()->fetch('SELECT * FROM `tbl_author_roles_fields` WHERE `id_role` = '.$id_role.';');
			$data['fields'] = array();

			foreach($fields as $field) {
				$id_field = $field['id_field'];
				unset($field['id']);
				unset($field['id_field']);
				$data['fields'][$id_field] = $field;
			}

			// The entries:

			return $data;
		}
		else {
			return false;
		}
	}

	/**
	 * Delete a role
	 * @param	$id
	 *  The ID of the role
	 */
	public function deleteRole($id) {
		Symphony::Database()->query('DELETE FROM `tbl_author_roles` WHERE `id` = '.$id.';');
		Symphony::Database()->query('DELETE FROM `tbl_author_roles_authors` WHERE `id_role` = '.$id.';');
		Symphony::Database()->query('DELETE FROM `tbl_author_roles_sections` WHERE `id_role` = '.$id.';');
		Symphony::Database()->query('DELETE FROM `tbl_author_roles_fields` WHERE `id_role` = '.$id.';');
	}

	/**
	 * Save Role data
	 * @param	$values
	 *  An array with information about the role to be stored
	 * @return int|boolean
	 *  The ID of the role, or false on failure
	 */
	public function saveData($values) {
		// Get the main data:
		$name = isset($values['name']) ? Symphony::Database()->cleanValue($values['name']) : '';
		$custom_elements = isset($values['custom_elements']) ? $values['custom_elements'] : '';

		if(!empty($name) && isset($values['id_role'])) {
			// Create the role:
			if($values['id_role'] == 0) {
				Symphony::Database()->insert(array('name'=>$name, 'custom_elements'=>$custom_elements), 'tbl_author_roles');
				$id_role = Symphony::Database()->getInsertId();
			}
			else {
				$id_role = $values['id_role'];
				Symphony::Database()->update(array('name'=>$name, 'custom_elements'=>$custom_elements), 'tbl_author_roles', '`id` = '.$id_role);
			}

			// First delete all links in the database before new ones are created:
			Symphony::Database()->query('DELETE FROM `tbl_author_roles_sections` WHERE `id_role` = '.$id_role.';');
			Symphony::Database()->query('DELETE FROM `tbl_author_roles_fields` WHERE `id_role` = '.$id_role.';');

			foreach($values['section'] as $id_section => $info) {
				$insert 			= array('id_role'=>$id_role, 'id_section'=>$id_section);
				$insert['visible']	= isset($info['visible']) ? 1 : 0;
				$insert['create']	= isset($info['create']) ? 1 : 0;
				$insert['edit']		= isset($info['edit']) ? 1 : 0;
				$insert['delete']	= isset($info['delete']) ? 1 : 0;
				$insert['own_entries'] = isset($info['own_entries']) ? 1 : 0;
				$insert['use_filter']  = isset($info['use_filter']) ? 1 : 0;

				// Filter rule:
				if(isset($info['filter_rule']) && isset($info['filter_rule_type'])) {
					$insert['filter_rule'] = $info['filter_rule_type'].':'.$info['filter_rule'];
				}

				Symphony::Database()->insert($insert, 'tbl_author_roles_sections');

				// Fields:
				if(isset($info['fields'])) {
					foreach($info['fields'] as $id_field => $value) {
						Symphony::Database()->insert(array('id_role'=>$id_role, 'id_field'=>$id_field, 'hidden'=>1), 'tbl_author_roles_fields');
					}
				}
				// Entries:

			}

			return $id_role;
		}
		else {
			return false;
		}
	}

	/**
	 * Installation
	 */
	public function install() {
		// Roles table:
		Symphony::Database()->query("
			CREATE TABLE IF NOT EXISTS `tbl_author_roles` (
				`id` INT(11) unsigned NOT NULL auto_increment,
				`name` VARCHAR(255) NOT NULL,
				`custom_elements` TEXT NULL,
				PRIMARY KEY (`id`)
			);
		");

		// Roles <-> Authors
		Symphony::Database()->query("
			CREATE TABLE IF NOT EXISTS `tbl_author_roles_authors` (
				`id` INT(11) unsigned NOT NULL auto_increment,
				`id_role` INT(255) unsigned NOT NULL,
				`id_author` INT(255) unsigned NOT NULL,
				PRIMARY KEY (`id`),
				KEY `id_role` (`id_role`),
				KEY `id_author` (`id_author`)
			);
		");

		// Roles <-> Sections
		Symphony::Database()->query("
			CREATE TABLE IF NOT EXISTS `tbl_author_roles_sections` (
				`id` INT(11) unsigned NOT NULL auto_increment,
				`id_role` INT(255) unsigned NOT NULL,
				`id_section` INT(255) unsigned NOT NULL,
				`visible` TINYINT(1) unsigned NOT NULL,
				`create` TINYINT(1) unsigned NOT NULL,
				`edit` TINYINT(1) unsigned NOT NULL,
				`delete` TINYINT(1) unsigned NOT NULL,
				`own_entries` TINYINT(1) unsigned NOT NULL,
				`use_filter` TINYINT(1) unsigned NOT NULL,
				`filter_rule` TEXT NOT NULL,
				PRIMARY KEY (`id`),
				KEY `id_role` (`id_role`),
				KEY `id_section` (`id_section`)
			);
		");

		// Roles <-> Fields
		Symphony::Database()->query("
			CREATE TABLE IF NOT EXISTS `tbl_author_roles_fields` (
				`id` INT(11) unsigned NOT NULL auto_increment,
				`id_role` INT(255) unsigned NOT NULL,
				`id_field` INT(255) unsigned NOT NULL,
				`hidden` TINYINT(1) unsigned NOT NULL,
				PRIMARY KEY (`id`),
				KEY `id_role` (`id_role`),
				KEY `id_field` (`id_field`)
			);
		");
	}

	/**
	 * Uninstallation
	 */
	public function uninstall() {
		// Drop all the tables:
		Symphony::Database()->query("DROP TABLE `tbl_author_roles`");
		Symphony::Database()->query("DROP TABLE `tbl_author_roles_authors`");
		Symphony::Database()->query("DROP TABLE `tbl_author_roles_sections`");
		Symphony::Database()->query("DROP TABLE `tbl_author_roles_fields`");
	}

	/**
	 * Update instructions
	 * @param $previousVersion
	 *  The version that is currently installed in this Symphony installation
	 * @return boolean
	 */
	public function update($previousVersion) {
		if(version_compare($previousVersion, '1.2', '<')) {
			// Update from pre-1.1 to 1.2:
			return Symphony::Database()->query('ALTER TABLE  `tbl_author_roles` ADD  `custom_elements` TEXT NULL;');
		}
	}
}

