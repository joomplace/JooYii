<?php
/**
 * @package     Joomplace\Library\JooYii
 *
 * @copyright   Alexandr Kosarev
 * @license     GPL2
 */

namespace Joomplace\Library\JooYii;

jimport('joomla.database.table');

/**
 * Model class for implementing M letter of new Joomla!CMS MVC
 * (JooYii MVC)
 * Both Table and Table Entry description
 *
 * @package Joomplace\Library\JooYii
 *
 * @since   1.0
 */
class Model extends \JTable
{
	/**
	 * Array of tables names as key and state of integrety as value
	 * @var array $_integrety_checked
	 *
	 * @since 1.0
	 */
	protected static $_integrety_checked = array();
	/** @var  \JDatabaseDriver $_db */
	protected $_db;
	/**
	 * Array of tables columns existing in DB
	 * @var array $_columns
	 *
	 * @since 1.0
	 */
	protected $_columns;
	/** @var  \Joomla\Registry\Registry $_user_state */
	protected $_user_state;
	/** @var string $_table */
	protected $_table = '#__jtable_test';
	protected $_total = 0;
	protected $_offset = 0;
	protected $_limit = 0;
	/**
	 * Used for storing last list results
	 * @var array $_cache
	 *
	 * @since 1.0
	 */
	protected $_cache = array(
		'conditioner' => null,
		'limit'       => null,
		'limitstart'  => null,
		'list'        => null,
		'pagination'  => null,
	);
	/** @var string $_primary_key */
	protected $_primary_key = 'id';
	/**
	 * Table and table fields charset
	 * @var string $_charset
	 *
	 * @since 1.0
	 */
	protected $_charset = 'utf8';
	/**
	 * Table and table fields collation
	 * @var string $_collation
	 *
	 * @since 1.0
	 */
	protected $_collation = 'unicode_ci';
	/**
	 * List of field names as key for field describing arrays
	 * @var array $_field_defenitions
	 *
	 * @since 1.0
	 */
	protected $_field_defenitions = array(
		'id'       => array(
			/** DB field type */
			'mysql_type' => 'int(10) unsigned',
			/** Joomla FormField type */
			'type'       => 'hidden',
			/** Joomla FormField filter */
			'filter'     => 'integer',
			/** group for xml purposes */
			'group'      => '',
			/** XML and FormField fieldset */
			'fieldset'   => 'basic',
			/** class for input */
			'class'      => '',
			/** read only; disabled */
			'read_only'  => null,
			/** can be null */
			'nullable'   => false,
			/** DB column default value and FormField defaul */
			'default'    => null,
			/** for DB column purposes */
			'extra'      => 'auto_increment',
		),
		/** asset id is used for permissions rules linking */
		'asset_id' => array(
			'mysql_type' => 'int(10) unsigned',
			'type'       => 'hidden',
			'filter'     => 'unset',
			'group'      => '',
			'fieldset'   => 'basic',
			'class'      => '',
			'read_only'  => null,
			'nullable'   => false,
			'default'    => 0,
			/** use hide_at to hide from auto genarating in some context */
			'hide_at'    => array('list', 'read', 'form'),
		),
	);
	/**
	 * Use this field to extend $_field_defenitions
	 * in final class impementation
	 * @var array $_fields
	 *
	 * @since 1.0
	 */
	protected $_fields = array();

	/**
	 * Model constructor.
	 *
	 * @since 1.0
	 */
	public function __construct()
	{
//	    \JPluginHelper::importPlugin( 'jooyii' );
//	    $dispatcher = \JEventDispatcher::getInstance();
//	    $results = $dispatcher->trigger( 'onCdAddedToLibrary', array( &$artist, &$title ) );
		$db             = $this->_db = \JFactory::getDbo();
		$this->_charset = ($db->hasUTF8mb4Support()) ? 'utf8mb4' : 'utf8';
		if (!$this->onBeforeInit())
		{
			/*
			 * TODO: Raise ERRORs
			 */
			return false;
		}
		if (isset($this->field_defenitions))
		{
			$this->_field_defenitions = array_merge($this->_field_defenitions, $this->_fields);
		}
		$this->checkIntegrety();
		$this->_columns = $db->getTableColumns($this->_table, false);
		parent::__construct($this->_table, $this->_primary_key, $db);
		if (!$this->onAfterInit())
		{
			/*
			 * TODO: Raise ERRORs
			 */
			return false;
		}
	}

	/**
	 * Use for tweaking of initiation process
	 *
	 * @return bool State of initiation
	 *
	 * @since 1.0
	 */
	protected function onBeforeInit()
	{
		return true;
	}

	/**
	 * Triggers integrety fixing
	 *
	 * @param bool $force A flag for forcing recheck
	 *
	 * @return bool Integrety check status
	 *
	 * @since 1.0
	 */
	protected function checkIntegrety($force = false)
	{
		if (!$this->isIntegretyChecked() || $force)
		{
			$tables = $this->_db->getTableList();
			if (!in_array(str_replace('#__', $this->_db->getPrefix(), $this->_table), $tables))
			{
				if (!$this->createTable())
				{
					/*
					 * TODO: Raise ERRORs
					 */
				}
			}
			$this->_columns = $this->_db->getTableColumns($this->_table, false);
			foreach ($this->_field_defenitions as $field => $defenition)
			{
				$this->checkField($field, $defenition['mysql_type'], $defenition['nullable'], $defenition['default'], base64_encode(json_encode($defenition)), (isset($defenition['extra']) ? $defenition['extra'] : ''));
			}
			self::$_integrety_checked[$this->_table] = true;
		}

		return $this->isIntegretyChecked();
	}

	/**
	 * Return table integrety status
	 * for current model table
	 *
	 * @return bool
	 *
	 * @since 1.0
	 */
	public function isIntegretyChecked()
	{
		return isset(self::$_integrety_checked[$this->_table]);
	}

	/**
	 * Attempt to create table
	 *
	 * @return mixed Table creation attempt result
	 *
	 * @since 1.0
	 */
	protected function createTable()
	{
		$db  = $this->_db;
		$sql = "CREATE TABLE " . $db->qn($this->_table) . " (
				" . $db->qn($this->_primary_key) . " int(10) unsigned NOT NULL AUTO_INCREMENT, 
				PRIMARY KEY (" . $db->qn($this->_primary_key) . ")
			) ENGINE=InnoDB DEFAULT CHARSET=" . $this->_charset . " COLLATE=" . $this->_charset . "_" . $this->_collation . "";

		return $db->setQuery($sql)->execute();
	}

	/**
	 * Checking columns state
	 *
	 * @param        $name    Column name
	 * @param string $type    Column type
	 * @param bool   $is_null Is nullable
	 * @param bool   $default Column default value
	 * @param string $comment Comment for JooYii
	 * @param string $extra   Column extas
	 *
	 *
	 * @since 1.0
	 */
	protected function checkField($name, $type = 'text', $is_null = false, $default = false, $comment = '', $extra = '')
	{
		/** @var \JDatabaseDriver $db */
		$db     = $this->_db;
		$column = isset($this->_columns[$name]) ? ((array) $this->_columns[$name]) : array();
		$sql    = $this->fieldSql($name, $type, $is_null, $default, $comment, $extra);
		$chitem = \JSchemaChangeitem::getInstance($db, null, $sql);
		if ($chitem->checkQueryExpected)
		{
			if ($chitem->check() !== -2)
			{
				/*
				 * check isn't failed need to check deeper
				 */
				if ($column['Type'] != $type)
				{
					$chitem->checkStatus = -2;
				}
				elseif ($column['Collation'] && $column['Collation'] != $this->_charset . '_' . $this->_collation)
				{
					$chitem->checkStatus = -2;
				}
				elseif (($column['Null'] == 'NO' && !$is_null) || ($column['Null'] == 'YES' && $is_null))
				{
					$chitem->checkStatus = -2;
				}
				elseif ($column['Default'] != $default)
				{
					$chitem->checkStatus = -2;
				}
				elseif ($column['Comment'] != $comment)
				{
					$chitem->checkStatus = -2;
				}
			}
			if ($chitem->checkStatus === -2)
			{
				$chitem->fix();
			}
		}
	}

	/**
	 * Generating alter table SQL for column
	 *
	 * @param        $name    Column name
	 * @param string $type    Column type
	 * @param bool   $is_null Is nullable
	 * @param bool   $default Column default value
	 * @param string $comment Comment for JooYii
	 * @param string $extra   Column extas
	 *
	 * @return string ALTER TABLE SQL
	 *
	 * @since 1.0
	 */
	protected function fieldSql($name, $type = 'text', $is_null = false, $default = false, $comment = '', $extra = '')
	{
		$db  = $this->_db;
		$sql = 'ALTER TABLE ' . $db->qn($this->_table) . ' ' . (array_key_exists($name, $this->_columns) ? 'MODIFY' : 'ADD COLUMN') . ' ';
		if (strpos($type, 'text') !== false)
		{
			$type .= ' COLLATE ' . $this->_charset . '_' . $this->_collation;
		}
		elseif (strpos($type, 'varchar') !== false)
		{
			$type .= ' CHARACTER SET ' . $this->_charset . ' COLLATE ' . $this->_charset . '_' . $this->_collation;
		}
		$sql .= $db->qn($name) . ' ' . $type . ' ' . ($is_null ? 'NULL' : 'NOT NULL') . ' ' . (is_null($default) ? '' : ('DEFAULT ' . $db->q($default)));
		$sql .= ' COMMENT ' . $db->q($comment);
		$sql .= ' ' . $extra;

		return $sql;
	}

	/**
	 * Use for tweaking of initiation process
	 *
	 * @return bool State of initiation
	 *
	 * @since 1.0
	 */
	protected function onAfterInit()
	{
		return true;
	}

	/**
	 *  Reset current state of Objects public properties
	 *
	 * @since 1.0
	 */
	public function reset()
	{
		// Get the default values for the class from the table.
		foreach ($this->getFields() as $k => $v)
		{
			$this->$k = $v->Default;
		}

		// Reset table errors
		$this->_errors = array();
	}

	/**
	 * Get Joomla form base on xml generated for current model (table defenition)
	 *
	 * @param string $form_name Grouping prefix for fields
	 *
	 * @return \JForm Joomla form object
	 *
	 * @since 1.0
	 */
	public function getForm($form_name = 'jform')
	{
		$key        = $this->_primary_key;
		$name       = str_replace('#__', $this->_db->getPrefix(), $this->_table) . ($this->$key ? ('.' . $this->$key) : '');
		$xml        = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><form></form>');
		$fieldset   = $xml->addChild('fieldset');
		$set_values = ($this->$key) ? 1 : 0;
		foreach ($this->_field_defenitions as $key => $defenition)
		{
			if (!isset($defenition['label']))
			{
				$defenition['label'] = 'FORMFIELD_' . strtoupper($key) . '_LABEL';
			}
			if (!isset($defenition['description']))
			{
				$defenition['description'] = 'FORMFIELD_' . strtoupper($key) . '_DESC';
			}
			if ($set_values)
			{
				$defenition['default'] = $this->$key;
			}
			$defenition['name'] = $form_name . '[' . $key . ']';
			$defenition['id']   = $form_name . '_' . $key . '';
			$field              = $fieldset->addChild('field');
			foreach ($defenition as $attr => $attr_value)
			{
				if (in_array($attr, array('option')))
				{
					foreach ($attr_value as $kopt => $opt)
					{
						$option = $field->addChild('option', $opt)->addAttribute('value', $kopt);
					}
				}
				else
				{
					$field->addAttribute($attr, $attr_value);
				}
			}
		}
		$form = Form::getInstance($name, $xml->asXML(), array(), true, false);
		/*
		 * Register additional field types paths
		 */
		$form::addFieldPath(\Joomplace\Library\JooYii\Loader::getPathByPsr4(Helper::getClassNameSpace($this) . '\\Fields', '/', 'field'));
		$this->preprocessForm($form);

		return $form;
	}

	/**
	 * Use for needed form customizations
	 *
	 * @param Form $form Joomla Form
	 *
	 *
	 * @since 1.0
	 */
	protected function preprocessForm(Form &$form)
	{

	}

	/**
	 * Clear cache conditions
	 *
	 * @since 1.0
	 */
	public function clearCache()
	{
		$this->_cache['conditioner'] = null;
	}

	/**
	 * Get list of objects matching passed conditions
	 *
	 * @param bool   $limitstart  Start offset
	 * @param bool   $limit       Limit
	 * @param array  $conditioner Conditions for select
	 * @param string $by          Column name to use as array key
	 *
	 * @return mixed Array of current instances
	 *
	 * @since 1.0
	 */
	public function getList($limitstart = false, $limit = false, $conditioner = array(), $by = '')
	{
		if ($limit === false)
		{
			$limit = $this->getState('list.limit');
		}
		if ($limitstart === false)
		{
			$limitstart = $this->getState('list.limitstart');
		}
		if ($this->_cache['conditioner'] === $conditioner && $this->_cache['limit'] === $limit && $this->_cache['by'] === $by && $this->_cache['limitstart'] === $limitstart)
		{
			return $this->_cache['list'];
		}
		else
		{
			$this->_cache['conditioner'] = $conditioner;
			$this->_cache['list']        = null;
			$this->_cache['pagination']  = null;
			$this->_cache['by']          = '';
		}
		/** @var \JDatabaseDriverMysqli $db */
		$db    = $this->_db;
		$query = $this->getListQuery($conditioner);

		if ($limit)
		{
			/*
			 * prepare pagination
			 */
			$this->_limit = $limit;
			$cquery       = clone $query;
			if ($cquery->type == 'select'
				&& $cquery->group === null
				&& $cquery->union === null
				&& $cquery->unionAll === null
				&& $cquery->having === null
			)
			{
				$cquery->clear('select')->clear('order')->clear('limit')->clear('offset')->select('COUNT(*)');
				$this->_total = $db->setQuery($cquery)->loadResult();
			}
			else
			{
				$cquery->clear('limit')->clear('offset');
				$db->setQuery($cquery);
				$db->execute();
				$this->_total = (int) $db->getNumRows();
			}
			$offset = $this->getStart($limitstart, $limit);
			$db->setQuery($query, $offset, $limit);
			$this->setState('list.limit', $limit, true);
			$this->setState('list.limitstart', $limitstart, true);
		}
		else
		{
			$db->setQuery($query);
		}
		$this->_cache['list'] = $db->loadObjectList($by, get_class($this));

		return $this->_cache['list'];
	}

	/**
	 * Get user state or it's specific entry
	 *
	 * @param string $var     Path
	 * @param null   $default Default value
	 *
	 * @return \Joomla\Registry\Registry|mixed
	 *
	 * @since 1.0
	 */
	public function getState($var = '', $default = null)
	{
		$this->populateState();
		if ($var)
		{
			return $this->_user_state->get($var, $default);
		}
		else
		{
			return $this->_user_state;
		}
	}

	/**
	 * State auto-population
	 *
	 * @since 1.0
	 */
	public function populateState()
	{
		if (!$this->_user_state)
		{
			$this->_user_state = new \Joomla\Registry\Registry();
			$this->_user_state->set('list.limitstart', \JFactory::getApplication()->getUserStateFromRequest('list.limitstart', 'limitstart', 0));
			$this->_user_state->set('list.limit', \JFactory::getApplication()->getUserStateFromRequest('list.limit', 'limit', 20));
			$this->_user_state->set('list.ordering', \JFactory::getApplication()->getUserStateFromRequest('list.ordering', 'filter_order', 'id'));
			$this->_user_state->set('list.direction', \JFactory::getApplication()->getUserStateFromRequest('list.direction', 'filter_order_Dir', 'asc'));
//            $this->_user_state->set('list.ordering',\JFactory::getApplication()->getUserStateFromRequest('order','filter_order','id'));
//            $this->_user_state->set('list.direction',\JFactory::getApplication()->getUserStateFromRequest('direction','filter_order_Dir','asc'));
		}
	}

	/**
	 * Generate query for conditions
	 *
	 * @param array $conditioner Conditions
	 *
	 * @return \JDatabaseQuery Query
	 *
	 * @since 1.0
	 */
	public function getListQuery($conditioner = array())
	{
		if (!is_array($conditioner))
		{
			$conditioner = array($this->_primary_key => $conditioner);
		}

		/** @var \JDatabaseDriverMysqli $db */
		$db = $this->_db;

		/*
		 * Initialise the query
		 */
		$query  = $db->getQuery(true)
			->select('*')
			->from($this->_table);
		$fields = array_keys($this->getProperties());

		foreach ($conditioner as $field => $value)
		{
			/*
			 *  Check that $field is in the table.
			 */
			if (!in_array($field, $fields))
			{
				throw new \UnexpectedValueException(sprintf('Missing field in database: %s &#160; %s.', get_class($this), $field));
			}

			if (is_int($value))
			{
				$query->where($this->_db->quoteName($field) . ' = ' . $this->_db->quote($value));
			}
			else if (is_string($value))
			{
				$query->where($this->_db->quoteName($field) . ' LIKE ' . $this->_db->quote($value));
			}
			else if (is_array($value))
			{
				foreach ($value as &$v)
				{
					$v = $this->_db->q($v);
				}
				$query->where($this->_db->quoteName($field) . ' IN (' . implode(',', $value) . ')');
			}
		}

		$listOrd = $this->getState('list.ordering');
		$listDir = $this->getState('list.direction');
		if (!in_array($listOrd, $this->getColumns('list', true)))
		{
			$listOrd = $this->_primary_key;
			$listDir = 'ASC';
		}

		$query->order($db->qn($listOrd) . ' ' . $listDir);

		return $query;
	}

	/**
	 * Get columns visiable according to context
	 *
	 * @param string $lrf            Context
	 * @param bool   $include_hidden Force hidden to be included
	 *
	 * @return array Columns names
	 *
	 * @since 1.0
	 */
	public function getColumns($lrf = 'list', $include_hidden = false)
	{
		if (!$include_hidden)
		{
			$fields = array_filter($this->_field_defenitions, function ($field) use ($lrf)
			{
				if ($field['type'] == 'hidden' || (isset($field['hide_at']) && in_array($lrf, $field['hide_at'])))
				{
					return false;
				}

				return true;
			});
		}
		else
		{
			$fields = $this->_field_defenitions;
		}
		$columns = array_keys($fields);

		return $columns;
	}

	/**
	 * Recalculate limit start according to total
	 *
	 * @param $start Limit start
	 * @param $limit Limit
	 *
	 * @return int|mixed New limit start
	 *
	 * @since 1.0
	 */
	protected function getStart($start, $limit)
	{
		$this->_offset = $start;
		if ($start > $this->_total - $limit)
		{
			$this->_offset = max(0, (int) (ceil($this->_total / $limit) - 1) * $limit);
		}

		return $this->_offset;
	}

	/**
	 * Set value for specific state entry
	 *
	 * @param      $var            Path
	 * @param      $value          Value
	 * @param bool $set_user_state Flag of need to set as User state
	 *
	 * @since 1.0
	 */
	public function setState($var, $value, $set_user_state = false)
	{
		$this->populateState();
		$this->_user_state->set($var, $value);
		if ($set_user_state)
		{
			\JFactory::getApplication()->setUserState($var, $value);
		}
	}

	/**
	 * Get total count of matches for current conditions
	 *
	 * @param array $conditioner Conditions
	 *
	 * @return int|mixed Total
	 *
	 * @since 1.0
	 */
	public function getTotal($conditioner = array())
	{
		if (!$this->_total && $conditioner === $this->_cache['conditioner'])
		{
			/** @var \JDatabaseDriverMysqli $db */
			$db     = $this->_db;
			$cquery = $this->getListQuery($conditioner);
			if ($cquery->type == 'select'
				&& $cquery->group === null
				&& $cquery->union === null
				&& $cquery->unionAll === null
				&& $cquery->having === null
			)
			{
				$cquery->clear('select')->clear('order')->clear('limit')->clear('offset')->select('COUNT(*)');
				$this->_total = $db->setQuery($cquery)->loadResult();
			}
			else
			{
				$cquery->clear('limit')->clear('offset');
				$db->setQuery($cquery);
				$db->execute();
				$this->_total = (int) $db->getNumRows();
			}
		}

		return $this->_total;
	}

	/**
	 * Get current pagination
	 *
	 * @return mixed JPagination object
	 *
	 * @since 1.0
	 */
	public function getPagination()
	{
		if (!$this->_cache['pagination'])
		{
			$this->_cache['pagination'] = new \JPagination($this->_total, $this->_offset, $this->_limit);
		}

		return $this->_cache['pagination'];
	}

	/**
	 * Render control for the list view
	 *
	 * @param $field Column name
	 *
	 * @return string Control Html
	 *
	 * @since 1.0
	 */
	public function renderListControl($field)
	{
		$defenition = $this->_field_defenitions[$field];
		ob_start();
		$field_processer = '_renderListControl' . $field;
		if (method_exists($this, $field_processer))
		{
			$this->$field_processer($field);
		}
		else
		{
			switch ($defenition['type'])
			{
				case 'user':
					$this->_renderListControlUser($field);
					break;
				case 'text':
					$this->_renderListControlText($field);
					break;
				case 'editor':
					$this->_renderListControlEditor($field);
					break;
				case 'radio':
					$this->_renderListControlRadio($field);
					break;
				default:
					echo "<pre>";
					print_r($this->_field_defenitions[$field]);
					echo "</pre>";
					break;
			}
		}
		$layout = ob_get_contents();
		ob_end_clean();

		return $layout;
	}

	/**
	 * Control for User field type
	 *
	 * @param $field Column name
	 *
	 *
	 * @since 1.0
	 */
	protected function _renderListControlUser($field)
	{
		$user = \JFactory::getUser($this->$field);
		if ($user->id)
		{
			echo $user->name . ' (' . $user->username . ')';
		}
		else
		{
			echo \JText::_('ANONYMOUS');
		}
	}

	/**
	 * Control for Text field type
	 *
	 * @param $field Column name
	 *
	 *
	 * @since 1.0
	 */
	protected function _renderListControlText($field)
	{
		echo Helper::trimText($this->$field, 75);
	}

	/**
	 * Control for Editor field type
	 *
	 * @param $field Column name
	 */
	protected function _renderListControlEditor($field)
	{
		$this->_renderListControlText($field);
	}

	/**
	 * Control for Radio field type
	 *
	 * @param $field Column name
	 *
	 *
	 * @since 1.0
	 */
	protected function _renderListControlRadio($field, $active = false)
	{
		if (in_array($field, array('published', 'featured')))
		{
			/*
			 * TODO: check permissions to verb
			 */
			$active = true;
		}
		$paths = Loader::getPathByPsr4('Joomplace\\Library\\JooYii\\Layouts\\', '/');
		if ($active)
		{
			$layout = new \JLayoutFile('publish');
			$layout->addIncludePaths($paths);
			$key = $this->_primary_key;
			echo $layout->render(array('value' => $this->$field, 'task' => 'publish-task', 'id' => $this->$key));
		}
		else
		{
			$layout = new \JLayoutFile('state');
			$layout->addIncludePaths($paths);
			echo $layout->render($this->$field);
		}
	}

	/**
	 * Control for list item actions
	 *
	 * @param string $task  Task to trigger
	 * @param string $value Text for the link
	 * @param string $class Class of html entry
	 *
	 *
	 * @since 1.0
	 */
	public function renderListControlActionLink($task, $value, $class = '')
	{
		$paths  = Loader::getPathByPsr4('Joomplace\\Library\\JooYii\\Layouts\\', '/');
		$layout = new \JLayoutFile('action-btn');
		$layout->addIncludePaths($paths);
		$key = $this->_primary_key;
		echo $layout->render(array('value' => $value, 'task' => $task, 'class' => $class, 'id' => $this->$key));
	}

	/**
	 * Reroute for publish action
	 *
	 * @param null $pks    Ids
	 * @param int  $state  State
	 * @param int  $userId User
	 *
	 * @return mixed
	 *
	 * @since 1.0
	 */
	public function publish($pks = null, $state = 1, $userId = 0)
	{
		/*
		 * Hack (reroute) because of JTable::publish doesn't suite by params
		 */
		return Helper::callBindedFunction($this, 'setPublished');
	}

	/**
	 * Alias for setPublished with state 0
	 *
	 * @param array $cid Ids
	 *
	 * @return int
	 *
	 * @since 1.0
	 */
	public function unpublish(Array $cid)
	{
		$counter = $this->setPublished($cid, $state = 0);
		if ($counter)
		{
			\JFactory::getApplication()->enqueueMessage(\JText::sprintf('ITEMS_UNPUBLISHED', $counter));
		}

		return $counter;
	}

	/**
	 * Change publish state
	 *
	 * @param array $cid   Ids
	 * @param int   $state New state
	 *
	 * @return int Count of changed entries
	 *
	 * @since 1.0
	 */
	public function setPublished(Array $cid, $state = 1)
	{
		$counter = 0;
		foreach ($cid as $id)
		{
			$this->load($id, true);
			if ($this->published != $state)
			{
				$this->published = $state;
				if ($this->store())
				{
					$counter++;
				}
			}
		}
		if ($state && $counter)
		{
			\JFactory::getApplication()->enqueueMessage(\JText::sprintf('ITEMS_PUBLISHED', $counter));
		}

		return $counter;
	}

	/**
	 * Store current Object into DB
	 *
	 * @param bool $updateNulls Is update of null columns should
	 *                          be triggered
	 *
	 * @return bool Storing attempt result
	 *
	 * @since 1.0
	 */
	public function store($updateNulls = false)
	{
		if (array_key_exists('ordering', $this->_field_defenitions) && !$this->ordering)
		{
			$this->ordering = $this->getNextOrder();
		}

		return parent::store($updateNulls);
	}

	/**
	 * Saves the manually set order of records.
	 *
	 * @param   array   $pks   An array of primary key ids.
	 * @param   integer $order +1 or -1
	 *
	 * @return  boolean|JException  Boolean true on success, boolean false or JException instance on error
	 *
	 * @since   1.0
	 */
	public function saveorder(array $pks = null, $order = null)
	{
		//$table = $this->getTable();
		//$tableClassName = get_class($table);
		//$contentType = new JUcmType;
		//$type = $contentType->getTypeByTable($tableClassName);
		//$tagsObserver = $table->getObserverOfClass('JTableObserverTags');
		$conditions = array();

		if (empty($pks))
		{
			return \JError::raiseWarning(500, \JText::_('ERROR_NO_ITEMS_SELECTED'));
		}

		// Update ordering values
		foreach ($pks as $i => $pk)
		{
			$this->load((int) $pk);

			// Access checks.
			if (!$this->canEditState())
			{
				// Prune items that you can't change.
				unset($pks[$i]);
				\JLog::add(\JText::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'), \JLog::WARNING, 'jerror');
			}
			elseif ($this->ordering != $order[$i])
			{
				$this->ordering = $order[$i];

//                if ($type)
//                {
//                    $this->createTagsHelper($tagsObserver, $type, $pk, $type->type_alias, $table);
//                }

				if (!$this->store())
				{
					$this->setError($this->getError());

					return false;
				}

				// Remember to reorder within position and client_id
				//$condition = $this->getReorderConditions($table);
				//$found = false;
//
//                foreach ($conditions as $cond)
//                {
//                    if ($cond[1] == $condition)
//                    {
//                        $found = true;
//                        break;
//                    }
//                }
//
//                if (!$found)
//                {
//                    $key = $table->getKeyName();
//                    $conditions[] = array($table->$key, $condition);
//                }
			}
		}

		// Execute reorder for each category.
//        foreach ($conditions as $cond)
//        {
//            $table->load($cond[0]);
//            $table->reorder($cond[1]);
//        }

		// Clear the component's cache
		//$this->cleanCache();

		return true;
	}

	/**
	 * @return bool If state is editable by current user
	 *
	 * @since 1.0
	 */
	public function canEditState()
	{
		return true;
	}

	/**
	 * @param array $cid Ids to delete
	 *
	 * @return bool|int Count of affected entries
	 *
	 * @since 1.0
	 */
	public function remove(array $cid)
	{
		$counter = 0;
		foreach ($cid as $id)
		{
			if ($this->delete($id))
			{
				$counter++;
			}
		}
		if ($counter)
		{
			\JFactory::getApplication()->enqueueMessage(\JText::sprintf('ITEMS_DELETED', $counter));

			return $counter;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Get public avaliable values
	 *
	 * @return array Field name as key and value as value
	 *
	 * @since 1.0
	 */
	public function reveal()
	{
		return array_filter(get_object_vars($this), function ($key)
		{
			if (strpos($key, '_') === 0)
			{
				return false;
			}
			else
			{
				return true;
			}
		}, ARRAY_FILTER_USE_KEY);
	}

}