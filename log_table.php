<?php

/**
 * defines a log table form_element type
 * 
 * this creates a simple table element with named columns
 * the columns are defined in the "values" area of the field definition.
 * For writing, the element only allows data in one new row to be saved, old rows 
 * can't be edited: that makes it a "log" type field as opposed to a "spreadsheet" 
 * type field where all cells are editable
 *
 * @package    WordPress
 * @subpackage Participants Database Plugin
 * @author     Roland Barker <webdesign@xnau.com>
 * @copyright  2016  xnau webdesign
 * @license    GPL3
 * @version    0.1
 * @link       http://xnau.com/wordpress-plugins/
 * @depends    
 */

namespace pdbmps\fields;

class log_table extends pdb_custom_field {

  /**
   * @var array of column names in $name => $title format
   */
  private $columns;
  
  /**
   * @var array of stored values
   */
  private $values;

  /**
   * intantiates the field
   * 
   * @param string $name name of the field element
   * @param string $title title of the field element
   */
  public function __construct( $title = '' )
  {
    parent::__construct( 'log-table', empty( $title ) ? 'Log Table' : $title );
    add_filter('pdb-before_submit_signup', array( $this, 'save_data' ) );
    add_filter('pdb-before_submit_update', array( $this, 'save_data' ) );
  }

  /**
   * display the field value in a read context
   * 
   * @return string
   */
  public function display_value()
  {
    $table = sprintf( apply_filters( 'pdb-member_payments_table_html_wrap', '<table class="%1$s-%2$s"><thead>%3%s</thead><tbody>%4$s</tbody></table>' ), $this->field->name, $this->name, $this->table_header(), $this->table_body() );
    return sprintf( '<div class="table-form-element">%s</div>', $table );
  }
  
  /**
   * prepares the data for saving to the database
   * 
   * gets the previous rows from the record and adds the incoming row
   * 
   * @param array  the incoming post data
   * 
   * @return array the modified data array
   */
  public function save_data( $post )
  {
    $saved_data = $this->participant_values( $post['id'] );
    foreach( $this->table_fields() as $fieldname ) {
      if ( array_key_exists( $fieldname, $post ) ) {
        $table_value = isset( $saved_data[$fieldname] ) && ! empty( $saved_data[$fieldname] ) ? maybe_unserialize( $saved_data[$fieldname] ) : array();
        if ( $this->array_has_values( $post[$fieldname] ) ) {
          (array) $table_value[] = filter_var_array( $post[$fieldname], FILTER_SANITIZE_STRING );
        }
        $post[$fieldname] = serialize($table_value);
      }
    }
    return $post;
  }
  
  
  
  /**
   * provides the form element HTML
   * 
   * @return null
   */
  protected function form_element_html()
  {
    $html = '';
    $this->field->output = $this->editable_table();
  }
  
  /**
   * supplies the editable table element
   * 
   * @return string HTML
   */
  private function editable_table()
  {
    return sprintf( apply_filters( 'pdb-member_payments_table_html_wrap', '<table class="%1$s-%2$s"><thead>%3$s</thead><tbody>%4$s</tbody></table>' ), $this->field->name, $this->name, $this->table_header(), $this->table_body() . $this->editable_row() );
  }
  
  /**
   * supplies the editable row
   * 
   * @return string HTML
   */
  private function editable_row()
  {
    if ( isset( $this->field->attributes['readonly'] ) && $this->field->attributes['readonly'] === 'readonly' && ! \Participants_Db::current_user_has_plugin_role('editor', __METHOD__) ) return '';
    $row = '';
    $index = $this->value_row_count() + 1;
    $values = $this->post_data_array();
    
    $row_template = apply_filters( 'pdb-member_payments_table_body_row', '<tr data-index="%1$s">%2$s</tr>' );
    $cell_template = apply_filters( 'pdb-member_payments_table_body_cell', '<th class="%1$s-column">%2$s</th>' );
    foreach ( array_keys( $this->columns ) as $name ) {
      $input = \PDb_FormElement::get_element(array(
          'type' => 'text-line',
          //'name' => $this->field->name . '[' . $index . '][' . $name . ']',
          'name' => $this->field->name . '[' . $name . ']',
          'value' => isset( $values[$name] ) ? $values[$name] : '',
      ));
      $row .= sprintf( $cell_template, $name, $input  );
    }
    return sprintf( $row_template, $index, $row );
  }

  /**
   * provides the table body HTML
   * 
   * @return  string  HTML
   */
  private function table_body()
  {
    if ( $this->value_row_count() === 0 ) {
      // dont show if there are no rows in the data
      return '';
    }
    $rows = array();
    $row_template = apply_filters( 'pdb-member_payments_table_body_row', '<tr data-index="%1$s">%2$s</tr>' );
    $cell_template = apply_filters( 'pdb-member_payments_table_body_cell', '<td class="%1$s-column">%2$s</td>' );
    foreach ( $this->values as $i => $row ) {
      $cells = '';
      foreach ( array_keys( $this->columns ) as $name ) {
        $cells .= sprintf( $cell_template, $name, isset( $row[$name] ) ? $row[$name] : ''  );
      }
      $rows[] = sprintf( $row_template, $i, $cells );
    }
    return implode( "\n", $rows );
  }

  /**
   * provides the table header row
   * 
   * @return string HTML
   */
  private function table_header()
  {
    $header = '';
    $row_template = apply_filters( 'pdb-member_payments_table_header_row', '<tr>%s</tr>' );
    $cell_template = apply_filters( 'pdb-member_payments_table_header_cell', '<th class="%1$s-column">%2$s</th>' );
    foreach ( $this->columns as $name => $title ) {
      $header .= sprintf( $cell_template, $name, $title );
    }
    return sprintf( $row_template, $header );
  }

  /**
   * sets up the columns
   * 
   */
  protected function setup_columns()
  {
    foreach ( $this->field->options as $name => $title ) {
      /**
       * @filter pdb-member_payments_non_column_options 
       * @param array
       * 
       * provides a way to add field definition options that are not table columns
       */
      if ( !in_array( $name, apply_filters( 'pdb-member_payments_non_column_options', array() ) ) ) {
        $this->columns[$name] = $title;
      }
    }
  }

  /**
   * sets up the field object and columns
   * 
   * @param object $field the incoming object
   */
  protected function setup_field( $field )
  {
    $this->field = $field;
    $this->setup_columns();
    $this->setup_value();
  }
  
  /**
   * sets the field value property
   * 
   * this gets the values from the database if there is a new value in the $_POST 
   * array because the Shortcode class overrides the stored value with the POST 
   * value if it exists. We need to add the stored value to the incoming value in 
   * the POST to get the complete value for the field.
   * 
   */
  private function setup_value()
  {
    $values = maybe_unserialize( $this->field->value );
    if ( array_key_exists( $this->field->name, $_POST ) ) {
      $values = $this->stored_value( filter_input( INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT ) );
      if ( end( $values ) === filter_var_array( $_POST[$this->field->name], FILTER_SANITIZE_STRING ) ) {
        
        
        unset( $_POST[$this->field->name]);
      }
    }
    $this->values = !is_array( $values ) ? array() : $values;
  }

  /**
   * provides the form element's mysql datatype
   * 
   * @return string
   */
  protected function element_datatype()
  {
    return 'TEXT'; // ought to be big enough
  }
  
  /**
   * supplies the post values for a row
   * 
   * @return array the data array
   */
  private function post_data_array()
  {
    if ( array_key_exists( $this->field->name, $_POST ) ) {
      return filter_var_array( $_POST[$this->field->name], FILTER_SANITIZE_STRING );
    }
    return array();
  }
  
  /**
   * supplies a list of field names that are using this form element
   * 
   * @return array
   */
  private function table_fields()
  {
   $table_fields = wp_cache_get('table_fields_list', self::label );
   if ( $table_fields === false ) {
    $table_fields = array();
    foreach( \Participants_Db::$fields as $field ) {
      if ( $field->form_element === $this->name ) {
        $table_fields[] = $field->name;
      }
    }
    wp_cache_add('table_fields_list', self::label );
   }
   return $table_fields;
  }
  
  /**
   * provides a count of the number of rows in the data
   * 
   * ignores an empty row at the end of the array
   * 
   * @return int
   */
  private function value_row_count()
  {
    $count = count( $this->values );
    if ( $count > 0 && empty( end( $this->values ) ) ) {
      return $count - 1;
    }
    return $count;
  }
  
  /**
   * tells if an array has non-empty values
   * 
   * @param array $array the array to test
   * @return bool true if the array has values
   */
  private function array_has_values( $array )
  {
    $test = implode( '', (array) $array );
    return strlen( $test ) > 0;
  }
  
  /**
   * provides the field value from the database
   * 
   * @param int $id the record id
   * @return array the stored value
   */
  private function participant_values( $id )
  {
    return \Participants_Db::get_participant( filter_var($id, FILTER_SANITIZE_NUMBER_INT ) );
  }
  
  /**
   * supplies the stored value for the field
   * 
   * @param int $id current record id
   * @return array
   */
  private function stored_value( $id )
  {
    $record_values = $this->participant_values($id);
    return array_key_exists($this->field->name, $record_values) ? maybe_unserialize( $record_values[$this->field->name] ) : array();
  }

}
