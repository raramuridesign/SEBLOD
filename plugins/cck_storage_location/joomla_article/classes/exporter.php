<?php
/**
* @version 			SEBLOD 3.x Core
* @package			SEBLOD (App Builder & CCK) // SEBLOD nano (Form Builder)
* @url				https://www.seblod.com
* @editor			Octopoos - www.octopoos.com
* @copyright		Copyright (C) 2009 - 2018 SEBLOD. All Rights Reserved.
* @license 			GNU General Public License version 2 or later; see _LICENSE.php
**/

defined( '_JEXEC' ) or die;

require_once JPATH_SITE.'/plugins/cck_storage_location/joomla_article/joomla_article.php';

// Class
class plgCCK_Storage_LocationJoomla_Article_Exporter extends plgCCK_Storage_LocationJoomla_Article
{
	protected static $columns_excluded	=	array( 'asset_id', 'title_alias', 'fulltext', 'sectionid', 'tagsHelper' );
	protected static $columns_ignored	=	array( 'id', 'asset_id', 'title_alias', 'fulltext', 'sectionid', 'checked_out', 'checked_out_time', 'tagsHelper' );

	// getColumnsToExport
	public static function getColumnsToExport()
	{
		$table		=	self::_getTable();
		$columns	=	$table->getProperties();

		foreach ( self::$columns_excluded as $column ) {
			if ( array_key_exists( $column, $columns ) ) {
				unset( $columns[$column] );
			}
		}

		$columns['tags']	=	null;
		
		return array_keys( $columns );
	}

	// onCCK_Storage_LocationExport
	public static function onCCK_Storage_LocationExport( $items, &$config = array() )
	{
		// Init
		$app		=	JFactory::getApplication();
		$excluded2	=	array( 'cck'=>'' );
		$tables		=	array();
		$user		=	JFactory::getUser();
		
		// Prepare
		$table		=	self::_getTable();
		$fields		=	$table->getProperties();
		$hasCustom	=	false;
		$hasIntro	=	false;

		if ( isset( $config['fields'] ) && $config['fields'] === false ) {
			$fields	=	array();
		} elseif ( isset( $config['fields'] ) && count( $config['fields'] ) ) {
			$fields	=	$config['fields'];
		} else {
			$fields['tags']	=	null;

			if ( count( self::$columns_ignored ) ) {
				foreach ( self::$columns_ignored as $exclude ) {
					unset( $fields[$exclude] );
				}
			}
		}

		if ( count( $config['fields2'] ) ) {
			foreach ( $config['fields2'] as $k=>$field ) {
				if ( $field->storage != 'none' ) {
					if ( $field->storage_table == '' ) {
						continue;
					}
					if ( !isset( $tables[$field->storage_table] ) ) {
						$tables[$field->storage_table]	=	JCckDatabase::loadObjectList( 'SELECT * FROM '.$field->storage_table.' WHERE id IN ('.$config['pks'].')', 'id' );
					}
					if ( self::$custom && isset( $field->storage_field ) && $field->storage_field == self::$custom ) {
						if ( isset( $field->storage_field2 ) && $field->storage_field2 == self::$custom ) {
							$hasIntro	=	true;
						}

						$hasCustom	=	true;
					}
				}				
				if ( $config['component'] == 'com_cck_exporter' ) {
					$key		=	$field->name;
				} else {
					$key		=	( $field->label2 ) ? $field->label2 : ( ( $field->label ) ? $field->label : $field->name );
				}
				$fields[$key]	=	'';
			}
		}
		$fields	=	array_keys( $fields );

		if ( $config['isNew'] ) {
			if ( $config['ftp'] == '1' ) {
				$config['buffer']	.=	str_putcsv( $fields, $config['separator'] )."\n";
			} else {
				fputcsv( $config['handle'], $fields, $config['separator'] );
			}
		}
		
		// Set
		if ( $config['prepare_output'] ) {
			JPluginHelper::importPlugin( 'cck_field' );
		}
		if ( count( $items ) ) {
			foreach ( $items as $item ) {
				$config['n']	=	1;
				$config['pk']	=	0;

				// Check Permissions?
				if ( $config['authorise'] == 0  ) {
					continue;
				} elseif ( $config['authorise'] == 2 ) {
					if ( !isset( $config['types'][$item->cck] ) ) {
						$config['types'][$item->cck]	=	JCckDatabase::loadResult( 'SELECT id FROM #__cck_core_types WHERE name = "'.$item->cck.'"' );
					}
					if ( !$user->authorise( 'core.export', 'com_cck.form.'.$config['types'][$item->cck] ) ) {
						continue;
					}
				}

				// Core
				$config['pk']	=	$item->pk;
				$table			=	self::_getTable( $item->pk );

				if ( isset( $config['fields'] ) && $config['fields'] === false ) {
					$fields	=	array();
				} elseif ( isset( $config['fields'] ) && count( $config['fields'] ) ) {
					$fields	=	array();
					if ( $config['prepare_output'] ) {
						foreach ( $config['fields'] as $name=>$field ) {
							// DISPATCH --> EXPORT
							$val			=	@$table->$name;
							$app->triggerEvent( 'onCCK_FieldPrepareExport', array( &$field, $val, &$config ) );
							$fields[$name]	=	$field->output;
						}
					} else {
						$vars 	=	get_object_vars( $table );
						
						foreach ( $vars as $key=>$val ) {
							if ( isset( $config['fields'][$key] ) ) {
								$fields[$key]	=	$val;
							}
						}

						// Tags
						if ( isset( $fields['tags'] ) ) {
							if ( is_object( $fields['tags'] ) ) {
								$fields['tags']	=	$fields['tags']->tags;
							} else {
								$fields['tags']	=	'';
							}
						}
					}
				} else {
					$fields	=	$table->getProperties();

					if ( count( self::$columns_ignored ) ) {
						foreach ( self::$columns_ignored as $exclude ) {
							unset( $fields[$exclude] );
						}
					}

					// Tags
					if ( isset( $fields['tags'] ) && is_object( $fields['tags'] ) ) {
						$fields['tags']	=	$fields['tags']->tags;
					} else {
						$fields['tags']	=	'';
					}
				}

				// Core > Custom
				if ( self::$custom ) {
					$custom_value	=	'';
					$hasCustomValue	=	false;

					if ( isset( $fields[self::$custom] ) ) {
						$custom_value	=	$fields[self::$custom];

						$hasCustomField	=	true;
						$hasCustomValue	=	true;
					} else {
						$hasCustomField	=	false;

						if ( $hasCustom ) {
							$custom_value	=	$tables[self::$table][$item->pk]->{self::$custom};
							$hasCustomValue	=	true;
						}
					}

					if ( $hasCustomValue ) {
						preg_match_all( '#::(.*?)::(.*?)::/(.*?)::#s', $custom_value, $values );
						
						$tables[self::$table][$item->pk]->{self::$custom}	=	array();
						$fields[self::$custom]								=	'';
						
						if ( count( $values[1] ) ) {
							foreach ( $values[1] as $k=>$v ) {
								if ( $v == self::$custom && !$hasIntro ) {
									// DISPATCH --> EXPORT
									$fields[self::$custom]	=	$values[2][$k];
								} elseif ( !isset( $excluded2[$v] ) ) {
									$tables[self::$table][$item->pk]->{self::$custom}[$v]	=	$values[2][$k];
								}	
							}
						}
						if ( !$hasCustomField ) {
							unset( $fields[self::$custom] );
						}

						if ( $hasIntro ) {
							if ( is_array( $tables[self::$table][$item->pk]->{self::$custom} ) && !isset( $tables[self::$table][$item->pk]->{self::$custom}['introtext'] ) ) {
								$tables[self::$table][$item->pk]->{self::$custom}['introtext']	=	'';
							}
						}
						if ( is_array( $tables[self::$table][$item->pk]->{self::$custom} ) && !isset( $tables[self::$table][$item->pk]->{self::$custom}['fulltext'] ) ) {
							$tables[self::$table][$item->pk]->{self::$custom}['fulltext']	=	'';
						}
					}
				}

				// More
				if ( count( $config['fields2'] ) ) {
					foreach ( $config['fields2'] as $name=>$field ) {
						if ( $field->storage != 'none' ) {
							if ( $field->storage_table == '' ) {
								continue;
							}
						}
						if ( $config['component'] == 'com_cck_exporter' ) {
							$key		=	$field->name;
						} else {
							$key		=	( $field->label2 ) ? $field->label2 : ( ( $field->label ) ? $field->label : $field->name );
						}
						if ( $field->storage == 'standard' ) {
							// DISPATCH --> EXPORT
							if ( $config['prepare_output'] ) {
								$val			=	@$tables[$field->storage_table][$item->pk]->{$field->storage_field};
								$app->triggerEvent( 'onCCK_FieldPrepareExport', array( &$field, $val, &$config ) );
								$fields[$key]	=	$field->output;
							} else {
								$val			=	@$tables[$field->storage_table][$item->pk]->{$field->storage_field};
								$fields[$key]	=	$val;
							}
						} elseif ( $field->storage != 'none' ) {
							$name			=	$field->storage_field2 ? $field->storage_field2 : $name;
							if ( !isset( $tables[$field->storage_table][$item->pk]->{$field->storage_field} ) ) {
								$tables[$field->storage_table][$item->pk]->{$field->storage_field}	=	array(); /* TODO#SEBLOD: */
							}

							// DISPATCH --> EXPORT
							if ( $config['prepare_output'] ) {
								$val			=	( is_array( $tables[$field->storage_table][$item->pk]->{$field->storage_field} ) && isset( $tables[$field->storage_table][$item->pk]->{$field->storage_field}[$name] ) ) ? $tables[$field->storage_table][$item->pk]->{$field->storage_field}[$name] : $tables[$field->storage_table][$item->pk]->{$field->storage_field};
								$app->triggerEvent( 'onCCK_FieldPrepareExport', array( &$field, $val, &$config ) );
								$fields[$key]	=	$field->output;
							} else {
								$val			=	( is_array( $tables[$field->storage_table][$item->pk]->{$field->storage_field} ) && isset( $tables[$field->storage_table][$item->pk]->{$field->storage_field}[$name] ) ) ? $tables[$field->storage_table][$item->pk]->{$field->storage_field}[$name] : $tables[$field->storage_table][$item->pk]->{$field->storage_field};
								$fields[$key]	=	$val;
							}
						} else {
							$fields[$key]		=	'';
						}
					}
				}

				// BeforeExport
				$event	=	'onCckPreBeforeExport';
				if ( isset( $config['processing'][$event] ) ) {
					foreach ( $config['processing'][$event] as $p ) {
						if ( is_file( JPATH_SITE.$p->scriptfile ) ) {
							$options	=	new JRegistry( $p->options );

							include JPATH_SITE.$p->scriptfile; /* Variables: $fields, $config */
						}
					}
				}

				/* TODO#SEBLOD: beforeExport */

				$event	=	'onCckPostBeforeExport';
				if ( isset( $config['processing'][$event] ) ) {
					foreach ( $config['processing'][$event] as $p ) {
						if ( is_file( JPATH_SITE.$p->scriptfile ) ) {
							$options	=	new JRegistry( $p->options );

							include JPATH_SITE.$p->scriptfile; /* Variables: $fields, $config */
						}
					}
				}

				// Export
				if ( $config['ftp'] == '1' ) {
					for ( $i = 0; $i < $config['n']; $i++ ) {
						$config['buffer']	.=	str_putcsv( $fields, $config['separator'] )."\n";
					}
				} else {
					if ( $config['n'] > 1 ) {
						for ( $i = 0; $i < $config['n']; $i++ ) {
							fputcsv( $config['handle'], $fields, $config['separator'] );
						}
					} else {
						fputcsv( $config['handle'], $fields, $config['separator'] );
					}
				}
				$config['count']++;
			}
		}
	}
}
?>