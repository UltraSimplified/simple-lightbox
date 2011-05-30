<?php
require_once 'class.base.php';
require_once 'class.fields.php';

/**
 * Option object
 * @package Simple Lightbox
 * @subpackage Options
 * @author SM
 */
class SLB_Option extends SLB_Field {

	/**
	 * Child mapping
	 * @see SLB_Field_Base::map
	 * @var array
	 */
	var $map = array(
		'default'	=> 'data',
		'attr'		=> 'properties'
	);
	
	/* Init */
	
	function SLB_Option($id, $title = '', $default = '') {
		$args = func_get_args();
		call_user_func_array(array(&$this, '__construct'), $args);
	}
	
	/**
	 * @see SLB_Field::__construct()
	 * @uses parent::__construct() to initialize instance
	 * @param $id
	 * @param $title
	 * @param $default
	 */
	function __construct($id, $title = '', $default = '') {
		//Normalize properties
		$args = func_get_args();
		$props = SLB_Utilities::func_get_options($args);
		$props = wp_parse_args($props, array ('id' => $id, 'title' => $title, 'default' => $default));
		//Send to parent constructor
		parent::__construct($props);
	}
	
	/* Getters/Setters */
	
	/**
	 * Retrieve default value for option
	 * @return mixed Default option value
	 */
	function get_default($context = '') {
		return $this->get_data($context, false);	
	}
	
	/**
	 * Sets parent based on default value
	 */
	function set_parent($parent = null) {
		$p = $this->get_parent();
		if ( empty($parent) && empty($p) ) {
			$parent = 'text';
			$d = $this->get_default();
			if ( is_bool($d) )
				$parent = 'checkbox';
			$parent = 'option_' . $parent;
		} elseif ( !empty($p) && !is_object($p) ) {
			$parent =& $p;
		}
		parent::set_parent($parent);
	}
	
	/* Formatting */
	
	/**
	 * Format data as string for browser output
	 * @see SLB_Field_Base::format()
	 * @param mixed $value Data to format
	 * @param string $context (optional) Current context
	 * @return string Formatted value
	 */
	function format_display($value, $context = '') {
		if ( !is_string($value) ) {
			if ( is_bool($value) )
				$value = ( $value ) ? 'Enabled' : 'Disabled';
			elseif ( is_null($value) )
				$value = '';
			else
				$value = strval($value);
		}
		return htmlentities($value);
	}
}

/**
 * Options collection
 * @package Simple Lightbox
 * @subpackage Options
 * @author SM
 * @uses SLB_Field_Collection
 */
class SLB_Options extends SLB_Field_Collection {
	
	/* Properties */

	var $item_type = 'SLB_Option';
	
	/* Init */
	
	function SLB_Options($id, $props = array()) {
		$args = func_get_args();
		call_user_func_array(array(&$this, '__construct'), $args);
	}
	
	function __construct($id, $props = array()) {
		$args = func_get_args();
		call_user_func_array(array(parent, '__construct'), $args);
	}
	
	function register_hooks() {
		parent::register_hooks();
		//Register fields
		add_action($this->add_prefix('register_fields'), $this->m('register_fields'));
		//Set option parents
		add_action($this->add_prefix('fields_registered'), $this->m('set_parents'));
	}
	
	/**
	 * Migrate options from old versions to current version
	 */
	function migrate() {
		//Legacy options
		$oid = 'enabled_single';
		$d = null;
		$this->load_data();
		if ( ($o = get_option($oid, $d)) && $o !== $d ) {
			$this->set_data('enabled_post', $o, false);
			$this->set_data('enabled_page', $o, false);
		}
		//Migrate separate options to unified option
		$items =& $this->get_items();
		foreach ( $items as $id => $opt ) {
			$oid = $this->add_prefix($id);
			$o = get_option($oid, $d);
			if ( $o !== $d ) {
				//Migrate value to data array
				$this->set_data($id, $o, false);
				//Delete legacy option
				delete_option($oid);
			}
		}
		//Remove any remaining legacy items
		if ( is_array($this->properties_init) && isset($this->properties_init['legacy']) && is_array($this->properties_init['legacy']) ) {
			foreach( $this->properties_init['legacy'] as $opt )
				delete_option($this->add_prefix($opt));
		}
		//Save changes
		$this->save();
	}
	
	/* Option setup */
	
	/**
	 * Register option-specific fields
	 * @param SLB_Fields $fields Reference to global fields object
	 * @return void
	 */
	function register_fields(&$fields) {
		//Layouts
		$layout_label = '<label for="{field_id}" class="title block">{label}</label>';
		$label_ref = '{label ref_base="layout"}';
		$field_pre = '<div class="input block">';
		$field_post = '</div>';
		$opt_pre = '<div class="' . $this->add_prefix('option_item') . '">';
		$opt_post = '</div>';
		$layout_form = '<{form_attr ref_base="layout"} /> (Default: {data context="display" top="0"})'; 
		
		//Text input
		$otxt = new SLB_Field_Type('option_text', 'text');
		$otxt->set_property('class', '{inherit} code');
		$otxt->set_property('size', null);
		$otxt->set_property('value', '{data context="form"}');
		$otxt->set_layout('label', $layout_label);
		$otxt->set_layout('form', $opt_pre . $label_ref . $field_pre . $layout_form . $field_post . $opt_post);
		$fields->add($otxt);
		
		//Checkbox
		$ocb = new SLB_Field_Type('option_checkbox', 'checkbox');
		$ocb->set_layout('label', $layout_label);
		$ocb->set_layout('form', $opt_pre . $label_ref . $field_pre . $layout_form . $field_post . $opt_post);
		$fields->add($ocb);
		
		//Theme
		$othm = new SLB_Field_Type('option_theme', 'select');
		$othm->set_layout('label', $layout_label);
		$othm->set_layout('form_start', $field_pre . '{inherit}');
		$othm->set_layout('form_end', '{inherit}' . $field_post);
		$othm->set_layout('form', $opt_pre . '{inherit}' . $opt_post);
		$fields->add($othm);
	}
	
	/**
	 * Set parent field types for options
	 * Parent only set for Admin pages
	 * @uses SLB_Option::set_parent() to set parent field for each option item
	 * @uses is_admin() to determine if current request is admin page
	 * @param array $fields Default field types
	 * @return void
	 */
	function set_parents(&$fields) {
		if ( !is_admin() )
			return false;
		$items =& $this->get_items();
		foreach ( $items as $opt ) {
			$opt->set_parent();
		}
	}
	
	/* Processing */
	
	function validate($values) {
		if ( is_array($values) ) {
			//Get option group being validated
			/*
			$group = '';
			$filter = 'sanitize_option_';
			$option = str_replace($filter, '', current_filter());
			if ( $this->get_id() == $this->remove_prefix($option) ) {
				$group = '';
			} else {
				$group = substr($option, strlen($this->add_prefix($this->get_id())) + 1); 
			}
			*/
			//Format data based on option type (bool, string, etc.)
			foreach ( $values as $id => $val ) {
				//Get default
				$d = $this->get_default($id);
				if ( is_bool($d) && !empty($val) )
					$values[$id] = true;
			}
			//Merge in additional options that are not in post data
			//Missing options (e.g. disabled checkboxes) & defaults
			$items =& $this->get_items();
			foreach ( $items as $id => $opt ) {
				//Add options that were not included in form submission
				if ( !array_key_exists($id, $values) ) {
					if ( is_bool($opt->get_default()) )
						$values[$id] = false;
					else
						$values[$id] = $opt->get_default();
				}
			}
		}
		
		//Return value
		return $values;
	}
	
	/* Data */
	
	/**
	 * Retrieve options from database
	 * @return array Options data
	 */
	function fetch_data($sanitize = true) {
		$data = get_option($this->get_key(), null);
		if ( $sanitize && is_array($data) ) {
			//Sanitize loaded data based on default values
			foreach ( $data as $id => $val ) {
				if ( $this->has($id) ) {
					$opt = $this->get($id);
					if ( is_bool($opt->get_default()) )
						$data[$id] = !!$val;
				} else {
					unset($data[$id]);
				}
			}
		}
		return $data;
	}
	
	/**
	 * Retrieves option data for collection
	 * @see SLB_Field_Collection::load_data()
	 */
	function load_data() {
		static $fetched = false;
		if ( !$fetched ) {
			$fetched = true;
			//Retrieve data
			$this->data = $this->fetch_data();
		}
	}
	
	/**
	 * Resets option values to their default values
	 * @param bool $hard Reset all options if TRUE (default), Reset only unset options if FALSE
	 */
	function reset($hard = true) {
		$this->load_data();
		//Reset data
		if ( $hard ) {
			$this->data = null;
		}
		//Save
		$this->save();
	}
	
	/**
	 * Save options data to database
	 */
	function save() {
		$opts =& $this->get_items();
		$data = array();
		foreach ( $opts as $id => $opt ) {
			$data[$id] = $opt->get_data();
		}
		$this->data = $data;
		update_option($this->get_key(), $data);
	}

	/* Collection */
	
	/**
	 * Build key for saving/retrieving data to options table
	 * @return string Key
	 */
	function get_key() {
		return $this->add_prefix($this->get_id());
	}
	
	/**
	 * Add option to collection
	 * @uses SLB_Field_Collection::add() to add item
	 * @param string $id Unique item ID
	 * @param string $title Item title
	 * @param mixed $default Default value
	 * @param string $group (optional) Group ID to add item to
	 * @return SLB_Option Option instance reference
	 */
	function &add($id, $title = '', $default = '', $group = null) {
		//Build properties array
		$properties = $this->make_properties($title, array('title' => $title, 'group' => $group, 'default' => $default));
		
		//Create item
		/**
		 * @var SLB_Option
		 */
		$item =& parent::add($id, $properties);
		
		return $item;
	}
	
	/**
	 * Retrieve option value
	 * @uses get_data() to retrieve option data
	 * @param string $option Option ID to retrieve value for
	 * @param string $context (optional) Context for formatting data
	 * @return mixed Option value
	 */
	function get_value($option, $context = '') {
		return $this->get_data($option, $context);
	}
	
	/**
	 * Retrieve option's default value
	 * @uses get_data() to retrieve option data
	 * @param string $option Option ID to retrieve value for
	 * @param string $context (optional) Context for formatting data
	 * @return mixed Option's default value
	 */
	function get_default($option, $context = '') {
		return $this->get_data($option, $context, false);
	}
	
	/* Output */
	
	function build_group($group) {
		if ( !$this->group_exists($group) )
			return false;
		$group =& $this->get_group($group);
		//Stop processing if group contains no items
		if ( !count($this->get_items($group)) )
			return false;
		
		//Group header
		echo '<h4 class="subhead">' . $group->title . '</h4>';
		//Build items
		echo $this->build_items($group);
	}
}