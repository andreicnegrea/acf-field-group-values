<?php
/**
 * Field_Group_Values Class
 *
 * @author      Tim Jensen <tim@timjensen.us>
 * @license     GNU General Public License 2.0+
 * @link        https://www.timjensen.us
 * @since       1.3.0
 * @package     TimJensen\ACF\Field_Group_Values
 */

declare( strict_types = 1 );

namespace TimJensen\ACF;

if ( ! class_exists( 'TimJensen\ACF\Field_Group_Values' ) ) :

	/**
	 * Class Field_Group_Values
	 */
	class Field_Group_Values {

		/**
		 * Field group configuration array for the current level of recursion.
		 *
		 * @var array
		 */
		protected $config;

		/**
		 * Field group configuration array containing fields/groups to clone.
		 *
		 * @since 2.0.0
		 *
		 * @var array
		 */
		protected $clone_fields;

		/**
		 * Post ID or 'option'.
		 *
		 * @var int|string
		 */
		protected $post_id;

		/**
		 * Stores all the custom field values.
		 *
		 * @var array
		 */
		protected $results = [];

		/**
		 * Field_Group_Values constructor.
		 *
		 * @param int|string $post_id      Post ID, or 'options' when retrieving option values.
		 * @param array      $config       Field group configuration array.
		 * @param array      $clone_fields Field group configuration arrays for cloned fields/groups.
		 */
		public function __construct( $post_id, array $config, $clone_fields = [] ) {
			$this->post_id      = $post_id;
			$this->config       = $config['fields'];
			$this->clone_fields = array_merge( [ $config ], $clone_fields );
		}

		/**
		 * Builds the multidimensional array that contains all the custom field values.
		 *
		 * @return array
		 */
		public function get_all_field_group_values(): array {

			$this->reset_results();

			foreach ( $this->config as $field ) {

				if ( ! $this->has_valid_field_structure( $field ) ) {
					continue;
				}

				$field_key   = $this->get_field_key( $field );
				$field_value = $this->get_field_value( $field_key );

				if ( $this->is_flexible_content_field( $field ) ) {

					if ( empty( $field_value ) ) {
						continue;
					}

					$this->get_flexible_content_field_values( $field, $field_key, $field_value );

				} elseif ( $this->is_clone_field( $field ) ) {

					$this->get_clone_field_values( $field );

				} elseif ( $this->is_group_field( $field ) ) {

					$this->get_group_field_values( $field, $field_key, $field_value );

				} elseif ( $this->is_repeater_field( $field ) ) {

					if ( empty( $field_value ) ) {
						continue;
					}

					$this->get_repeater_field_values( $field, $field_key, $field_value );

				} else {

					$this->store_field_value( $field, $field_value );

				}
			}

			return $this->results;
		}

		/**
		 * Resets the results property so that the final array is formatted correctly.
		 *
		 * @return void
		 */
		protected function reset_results() {
			$this->results = [];
		}

		/**
		 * Determines whether the specified field has a valid structure.
		 *
		 * @param array $field ACF field configuration.
		 * @return bool
		 */
		protected function has_valid_field_structure( array $field ): bool {
			return ! empty( $field['name'] );
		}

		/**
		 * Builds the field key that is used for looking up the custom field value.
		 *
		 * @param array $field ACF field configuration.
		 * @return string
		 */
		protected function get_field_key( array $field ): string {
			$field_key = $field['name'];

			if ( isset( $field['field_key_prefix'] ) ) {
				return $field['field_key_prefix'] . $field_key;
			}

			return $field_key;
		}

		/**
		 * Retrieve the value for the specified field, either from the options table or post meta table.
		 *
		 * @param string $field_key Custom field key.
		 * @return mixed
		 */
		protected function get_field_value( string $field_key ) {
			if ( 'option' === $this->post_id ) {
				return get_option( "options_{$field_key}" );
			}

			return get_post_meta( $this->post_id, $field_key, true );
		}

		/**
		 * Returns true if $field represents a flexible content field.
		 *
		 * @param array $field ACF field configuration.
		 * @return bool
		 */
		protected function is_flexible_content_field( array $field ): bool {
			return isset( $field['type'] ) && 'flexible_content' === $field['type'];
		}

		/**
		 * Returns true if $field represents a clone field.
		 *
		 * @since 1.4.0
		 *
		 * @param array $field ACF field configuration.
		 * @return bool
		 */
		protected function is_clone_field( array $field ): bool {
			return isset( $field['type'] ) && 'clone' === $field['type'];
		}

		/**
		 * Returns true if $field represents a group field.
		 *
		 * @since 1.4.0
		 *
		 * @param array $field ACF field configuration.
		 * @return bool
		 */
		protected function is_group_field( array $field ): bool {
			return isset( $field['type'] ) && 'group' === $field['type'];
		}

		/**
		 * Returns true if $field represents a repeater field.
		 *
		 * @param array $field ACF field configuration.
		 * @return bool
		 */
		protected function is_repeater_field( array $field ): bool {
			return isset( $field['type'] ) && 'repeater' === $field['type'];
		}

		/**
		 * Returns true if $field represents a field group.
		 *
		 * @since 2.0.0
		 *
		 * @param array $field ACF field configuration.
		 * @return bool
		 */
		protected function is_field_group( array $field ): bool {
			return isset( $field['fields'] );
		}

		/**
		 * Returns an array of ACF flexible content layout types.
		 *
		 * @param array $field ACF field configuration.
		 * @return array
		 */
		protected function get_flexible_content_layout_types( array $field ): array {

			$layout_types = [];
			foreach ( $field['layouts'] as $layout ) {
				$layout_types[ $layout['name'] ] = $layout;
			}

			return $layout_types;
		}

		/**
		 * Returns the values for repeater fields.
		 *
		 * @param array  $field       ACF field configuration.
		 * @param string $field_key   Field key.
		 * @param array  $field_value Array of layout types for each flexible content row.
		 * @return void
		 */
		protected function get_flexible_content_field_values( array $field, string $field_key, array $field_value ) {

			$results = $this->results;

			$layout_types = $this->get_flexible_content_layout_types( $field );

			foreach ( $field_value as $index => $current_layout_type ) {

				// Check if the layout has been deleted from the ACF group.
				if ( empty( $layout_types[ $current_layout_type ]['sub_fields'] ) ) {
					continue;
				}

				$this->config = $layout_types[ $current_layout_type ]['sub_fields'];

				foreach ( $this->config as &$field_config ) {
					$field_config['field_key_prefix'] = $field_key . "_{$index}_";
				}

				$results[ $field['name'] ][] = array_merge(
					[
						'acf_fc_layout' => $current_layout_type,
					],
					$this->get_all_field_group_values()
				);
			}

			$this->results = $results;
		}

		/**
		 * Returns the custom field values for clone fields.
		 *
		 * @since 1.4.0
		 *
		 * @param array $field ACF field configuration.
		 * @return void
		 */
		protected function get_clone_field_values( array $field ) {

			$results = $this->results;

			$this->config = [];
			foreach ( $field['clone'] as $clone_field_key ) {

				$clone_field_config = $this->get_clone_field_config( $clone_field_key, $this->clone_fields );

				// A `false` value means the corresponding field was deleted from the field group.
				if ( false === $clone_field_config ) {
					continue;
				}

				$this->config = array_merge( $this->config, $clone_field_config );
			}

			foreach ( $this->config as &$field_config ) {

				// Build the field key prefix including ACF's option for prefixing, if set.
				$prefix = empty( $field['field_key_prefix'] ) ? '' : $field['field_key_prefix'];
				$prefix = empty( $field['prefix_name'] ) ? $prefix : "{$field['name']}_{$prefix}";

				$field_config['field_key_prefix'] = $prefix;
			}

			$results[ $field['name'] ] = $this->get_all_field_group_values();

			$this->results = $results;
		}

		/**
		 * Recursively search for the appropriate clone configuration array.
		 *
		 * @since 1.4.0
		 *
		 * @param string $clone_field_key Field key to search for.
		 * @param array  $clone_fields    ACF fields configuration.
		 * @return bool|array
		 */
		protected function get_clone_field_config( string $clone_field_key, array $clone_fields ) {

			foreach ( $clone_fields as $field ) {

				if ( $field['key'] === $clone_field_key ) {

					if ( $this->is_field_group( $field ) ) {
						return $field['fields'];
					}

					return [ $field ];

				} elseif ( $this->is_field_group( $field ) ) {

					$result = $this->get_clone_field_config( $clone_field_key, $field['fields'] );

					if ( $result ) {
						return $result;
					}
				} elseif ( $this->is_group_field( $field ) ) {

					$result = $this->get_clone_field_config( $clone_field_key, $field['sub_fields'] );

					if ( $result ) {
						return $result;
					}
				} elseif ( $this->is_flexible_content_field( $field ) ) {

					$result = $this->get_clone_field_config( $clone_field_key, $field['layouts'] );

					if ( $result ) {
						return $result;
					}
				}
			}

			return false;
		}

		/**
		 * Returns the custom field values for group fields.
		 *
		 * @since 1.4.0
		 *
		 * @param array  $field       ACF field configuration.
		 * @param string $field_key   Field key.
		 * @param string $field_value Field value.
		 * @return void
		 */
		protected function get_group_field_values( array $field, string $field_key, string $field_value ) {

			$results = $this->results;

			$this->config = $field['sub_fields'];

			foreach ( $this->config as &$field_config ) {
				$field_config['field_key_prefix'] = $field_key . '_';
			}

			$results[ $field['name'] ] = $this->get_all_field_group_values();

			$this->results = $results;
		}

		/**
		 * Returns the custom field values for repeater fields.
		 *
		 * @param array  $field       ACF field configuration.
		 * @param string $field_key   Field key.
		 * @param string $field_value Field value.
		 * @return void
		 */
		protected function get_repeater_field_values( array $field, string $field_key, string $field_value ) {

			$results = $this->results;

			for ( $i = 0; $i < $field_value; $i ++ ) {
				$this->config = $field['sub_fields'];

				foreach ( $this->config as &$field_config ) {
					$field_config['field_key_prefix'] = $field_key . "_{$i}_";
				}

				$results[ $field['name'] ][] = $this->get_all_field_group_values();
			}

			$this->results = $results;
		}

		/**
		 * Store the field value to the results property.
		 *
		 * @param array        $field       ACF field configuration.
		 * @param string|array $field_value Field value.
		 * @return void
		 */
		protected function store_field_value( array $field, $field_value ) {
			$this->results[ $field['name'] ] = $field_value;
		}

		/**
		 * Returns a multidimensional array containing all the custom field values.
		 *
		 * @return array
		 */
		public function get_results(): array {
			return $this->results;
		}
	}

endif;
