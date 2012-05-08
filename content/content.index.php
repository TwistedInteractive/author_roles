<?php
	require_once(TOOLKIT . '/class.administrationpage.php');
	
	Class contentExtensionAuthor_rolesIndex extends AdministrationPage
	{
		protected $_uri = null;
		protected $_driver = null;

		/**
		 * Constructor
		 */
		function __construct(){
			$this->_uri = URL . '/symphony/extension/author_roles/';
			$this->_driver = Symphony::ExtensionManager()->create('author_roles');
			parent::__construct();
		}

		/**
		 * Build the page
		 * @param array $context
		 */
		public function build($context)
		{
			if(Administration::instance()->Author->isDeveloper()) {
				if($_POST['with-selected'] == 'delete' && is_array($_POST['items']))
				{
					foreach($_POST['items'] as $id_role => $value)
					{
						$this->_driver->deleteRole($id_role);
					}
				}
				parent::build($context);
			}
		}

		/**
		 * The view
		 */
		public function view()
		{
			$this->setTitle('Symphony &ndash; Roles');
			$this->__viewIndex();
		}
		
		// The Index:
		public function __viewIndex()
		{
			// Set the page to display as a table:
			$this->setPageType('table');
			$this->appendSubheading(__('Author Roles'), Widget::Anchor(
				__('Create New'), $this->_uri.'roles/new/',
				__('Create a new role'), 'create button'
			));
			
			// Set the table head:
			$tableHead = array(
				array(__('Role Name'), 'col'),
				array(__('Authors with this role'), 'col')
			);
			
			// Set the table body:
			$tableBody = array();
			
			// Fill the table with available roles:
			$roles = $this->_driver->getRoles();
			if(empty($roles))
			{
				// No roles found, create an empty row:
				$tableBody = array(
					Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', null, count($tableHead))))
				);
			} else {
				foreach($roles as $role)
				{
					$row = new XMLElement('tr');
					$td  = new XMLElement('td', '<a href="'.$this->_url.'roles/edit/'.$role['id'].'/">'.$role['name'].'</a>');
					$td->appendChild(Widget::Input('items['.$role['id'].']', NULL, 'checkbox'));
					$row->appendChild($td);
					
					// Authors:
					$authors = $this->_driver->getAuthors($role['id']);
					if(empty($authors)) {
						$row->appendChild(
							new XMLElement('td', '<em>none</em>')
						);
					} else {
						$links = array();
						foreach($authors as $author)
						{
							$links[] = '<a href="'.URL.'/symphony/system/authors/edit/'.$author['id'].'/">'.$author['first_name'].' '.$author['last_name'].'</a>';
						}
						$row->appendChild(
							new XMLElement('td', implode(', ', $links))
						);
					}
					$tableBody[] = $row;
				}
			}
			
			// Create the table element:
			$table = Widget::Table(
				Widget::TableHead($tableHead), null, 
				Widget::TableBody($tableBody), 'selectable'
			);
			$this->Form->appendChild($table);

			$tableActions = new XMLElement('div');
			$tableActions->setAttribute('class', 'actions');

			$options = array(
				array(null, false, __('With Selected...')),
				array('delete', false, __('Delete'), 'confirm', null, array(
					'data-message' => __('Are you sure you want to delete the selected roles?')
				))
			);

			$tableActions->appendChild(Widget::Apply($options));
			$this->Form->appendChild($tableActions);
		}
	}
?>