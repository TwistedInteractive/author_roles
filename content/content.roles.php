<?php
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.fieldmanager.php');
	
	Class contentExtensionRole_modelRoles extends AdministrationPage
	{
		protected $_uri = null;
		protected $_driver = null;
		protected $_action = null;
		protected $_data = array();
		protected $_id_role = 0;
		
		function __construct(&$parent){
			parent::__construct($parent);
			$this->_uri = URL . '/symphony/extension/role_model/';
			$this->_driver = Symphony::ExtensionManager()->create('role_model');
		}
		
		public function build($context)
		{
			$this->_action = isset($context[0]) ? $context[0] : false;
			if($this->_action == 'edit' && isset($context[1]) && is_numeric($context[1]))
			{
				// Load the data:
				$this->_id_role = $context[1];
				$this->_data = $this->_driver->getData($this->_id_role);
			}
			if(isset($_POST['save'])) {
				// Save:				
				$this->_id_role = $this->_driver->saveData($_POST);
				$this->_data = $this->_driver->getData($this->_id_role);
				$this->pageAlert(__('Role successfully created/updated.'), Alert::SUCCESS);
			}
			parent::addStylesheetToHead(URL . '/extensions/role_model/assets/role_model.css', 'screen', 70);
			parent::addScriptToHead(URL . '/extensions/role_model/assets/role_model.js', 71);
			parent::build($context);
		}
		
		public function view()
		{
			$this->setTitle('Symphony &ndash; Roles');
			switch($this->_action) {
				case 'new' :
					{
						$this->viewForm();
						break;
					}
				case 'edit' :
					{
						$this->viewForm();
						break;
					}
				case 'delete' :
					{
						$this->viewDelete();
						break;
					}
			}
		}
		
		public function viewForm()
		{
			$this->setPageType('form');
			$this->appendSubheading(__('Roles'));
			
			// Create a group for the essential settings (for now, this is only the role name):
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', __('Essentials')));

			$div = new XMLElement('div');
			$div->setAttribute('class', 'group');
			
			$label = Widget::Label(__('Role Name'));
			// The name input field:
			$label->appendChild(
				Widget::Input('name', $this->getValue('name'))
			);
			$div->appendChild($label);
			$group->appendChild($div);			
			$this->Form->appendChild($group);
			
			// Create a group for the table (this is where the magic happens):
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', __('Sections')));

			$div = new XMLElement('div');
			$div->setAttribute('class', 'group');
			
			// The main table:
			// Set the table head:
			$tableHead = array(
				array(__('Name'), null, array('class'=>'name')),
				array('<span rel="visible">'.__('Visible').'</span>', null, array('class'=>'center')),
				array('<span rel="create">'.__('Create').'</span>', null, array('class'=>'center')),
				array('<span rel="edit">'.__('Edit').'</span>', null, array('class'=>'center')),
				array('<span rel="delete">'.__('Delete').'</span>', null, array('class'=>'center')),
				array(__('Fields'), null, array('class'=>'center')),
				array(__('Entries'), null, array('class'=>'center'))
			);
			
			// Set the table body:
			$tableBody = array();
			
			// Get the sections:
			$sm = new SectionManager($this);
			$fm = new FieldManager($this);
			$sections = $sm->fetch();
			
			foreach($sections as $section)
			{
				$id = $section->get('id');
				// Create a row for each section, with a rowspan:				
				$row = new XMLElement('tr');				
				$row->appendChild(new XMLElement('td', $section->get('name'), array('rowspan'=>2)));
				$row->appendChild($this->tdCheckBox('section['.$id.'][visible]', $this->checkSection($id, 'visible') == 1));
				$row->appendChild($this->tdCheckBox('section['.$id.'][create]', $this->checkSection($id, 'create') == 1));
				$row->appendChild($this->tdCheckBox('section['.$id.'][edit]', $this->checkSection($id, 'edit') == 1));
				$row->appendChild($this->tdCheckBox('section['.$id.'][delete]', $this->checkSection($id, 'delete') == 1));
				$row->appendChild(new XMLElement('td', '<a href="#" rel="fields">Edit</a>', array('class'=>'checkbox')));
				$row->appendChild(new XMLElement('td', '<a href="#" rel="entries">Edit</a>', array('class'=>'checkbox')));
				$tableBody[] = $row;				
				
				// Extra row for the fields and entries options:
				$row = new XMLElement('tr');
				$td  = new XMLElement('td', null, array('colspan'=>6, 'class'=>'options'));
				$divFields = new XMLElement('div', null, array('class'=>'sub fields'));
				$divFields->appendChild(new XMLElement('h3', __('Fields')));
				
				// Put all the fields in here:
				$thead = array(
					array(__('Name'), null, array('class'=>'name')),
					array(__('Hide'))
				);
				$tBody = array();
				
				// Get the fields:
				$fields = $fm->fetch(null, $id);
				foreach($fields as $field)
				{
					$id_field = $field->get('id');
					$required = $field->get('required');
					$fieldRow = new XMLElement('tr');
					$fieldRow->appendChild(new XMLElement('td', $field->get('label')));					
					if($required == 'no') {
						$fieldRow->appendChild($this->tdCheckBox('section['.$id.'][fields]['.$id_field.'][hidden]', $this->checkFields($id_field, 'hidden') == 1));
					} else {
						$fieldRow->appendChild(new XMLElement('td', __('required').' *', array('class'=>'required')));
					}
					$tBody[] = $fieldRow;					
				}
				$divFields->appendChild(Widget::Table(Widget::TableHead($thead), null, Widget::TableBody($tBody)));
				$divFields->appendChild(new XMLElement('p', '* '.__('You cannot hide required fields'), array('class'=>'info')));
				$td->appendChild($divFields);
				
				// Entry options:
				$divEntries = new XMLElement('div', null, array('class'=>'sub entries'));
				$label = Widget::Label();
				// check if this checkbox is checked:
				$attributes = $this->checkSection($id, 'own_entries') == 1 ? array('checked'=>'checked') : null;
				$input = Widget::Input('section['.$id.'][own_entries]', null, 'checkbox', $attributes);
				$label->setValue($input->generate() . ' ' . __('Author can view/edit own entries only'));
				$divEntries->appendChild($label);
				
				$label = Widget::Label();
				$attributes = $this->checkSection($id, 'use_filter') == 1 ? array('checked'=>'checked') : null;
				$input = Widget::Input('section['.$id.'][use_filter]', null, 'checkbox', $attributes);
				$label->setValue($input->generate() . ' ' . __('Use filter'));
				$divEntries->appendChild($label);
				
				$filterRule = new XMLElement('div', null, array('class'=>'filter'));
				$label = Widget::Label(__('Filter type'));
				$rule  = explode(':', $this->_data['sections'][$id]['filter_rule']);
				$options = array(
					array('show', $rule[0] == 'show', 'show'),
					array('hide', $rule[0] == 'hide', 'hide')
				);
				$label->appendChild(Widget::Select('section['.$id.'][filter_rule_type]', $options));
				$filterRule->appendChild($label);
				
				$label = Widget::Label(__('ID\'s'));
				$rule  = isset($rule[1]) ? $rule[1] : '';
				$label->appendChild(Widget::Input('section['.$id.'][filter_rule]', $rule));
				$filterRule->appendChild($label);
				$filterRule->appendChild(new XMLElement('p', __('Enter a list of comma-seperated ID\'s. Define ranges with a hyphen.<br />Example: <code>2,3,6-11,13</code>'), array('class'=>'info')));
				
				$divEntries->appendChild($filterRule);
				
				$td->appendChild($divEntries);
				
				$row->appendChild($td);
				$tableBody[] = $row;
			}
			
			// Create the table element:
			$table = Widget::Table(Widget::TableHead($tableHead), null, Widget::TableBody($tableBody), 'role_model');
			$div->appendChild($table);
			
			$group->appendChild($div);			
			$this->Form->appendChild($group);
			
			// Add the actions:
			$actions = new XMLElement('div');
			$actions->setAttribute('class', 'actions');
			if($this->_id_role == 0) {
				$buttonText = __('Create Role');
			} else {
				$buttonText = __('Save Changes');
			}
			// Add the ID:
			$actions->appendChild(Widget::Input('id_role', $this->_id_role, 'hidden'));
			$actions->appendChild(Widget::Input('save', $buttonText, 'submit'));
			$this->Form->appendChild($actions);
		}
		
		private function tdCheckBox($name, $checked = false)
		{
			$td = new XMLElement('td', null, array('class'=>'checkbox'));
			$attributes = $checked ? array('checked'=>'checked') : null;
			$td->appendChild(Widget::Input($name, null, 'checkbox', $attributes));
			return $td;
		}
		
		private function checkSection($id, $value)
		{
			if(isset($this->_data['sections']) && isset($this->_data['sections'][$id]) && isset($this->_data['sections'][$id][$value]))
			{
				return $this->_data['sections'][$id][$value];
			} else {
				return false;
			}
		}
		
		private function checkFields($id, $value)
		{
			if(isset($this->_data['fields']) && isset($this->_data['fields'][$id]) && isset($this->_data['fields'][$id][$value]))
			{
				return $this->_data['fields'][$id][$value];
			} else {
				return false;
			}
		}
		
		private function getValue($item_name)
		{
			if(isset($this->_data[$item_name]))
			{
				return $this->_data[$item_name];
			} else {
				return '';
			}
		}
	}
?>