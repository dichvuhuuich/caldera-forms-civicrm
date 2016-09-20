<?php

/**
 * CiviCRM Caldera Forms Phone Processor Class.
 *
 * @since 0.2
 */
class CiviCRM_Caldera_Forms_Phone_Processor {

	/**
	 * The processor key.
	 *
	 * @since 0.2
	 * @access public
	 * @var str $key_name The processor key
	 */
	public $key_name = 'civicrm_phone';

	/**
	 * Initialises this object.
	 *
	 * @since 0.2
	 */
	public function __construct() {

		// register this processor
		add_filter( 'caldera_forms_get_form_processors', array( $this, 'register_processor' ) );
		// filter form before rendering
		add_filter( 'caldera_forms_render_get_form', array( $this, 'pre_render') );

	}

	/**
	 * Adds this processor to Caldera Forms.
	 *
	 * @since 0.2
	 *
	 * @uses 'caldera_forms_get_form_processors' filter
	 *
	 * @param array $processors The existing processors
	 * @return array $processors The modified processors
	 */
	public function register_processor( $processors ) {

		$processors[$this->key_name] = array(
			'name' => __( 'CiviCRM Phone', 'caldera-forms-civicrm' ),
			'description' => __( 'Add CiviCRM phone to contacts', 'caldera-forms-civicrm' ),
			'author' => 'Andrei Mondoc',
			'template' => CF_CIVICRM_INTEGRATION_PATH . 'processors/phone/phone_config.php',
			'processor' => array( $this, 'processor' ),
		);

		return $processors;

	}

	/**
	 * Form processor callback.
	 *
	 * @since 0.2
	 *
	 * @param array $config Processor configuration
	 * @param array $form Form configuration
	 */
	public function processor( $config, $form ) {

		// globalised transient object
		global $transdata;

		if ( ! empty( $transdata['civicrm']['contact_id_' . $config['contact_link']] ) ) {

			try {

				$phone = civicrm_api3( 'Phone', 'getsingle', array(
					'sequential' => 1,
					'contact_id' => $transdata['civicrm']['contact_id_' . $config['contact_link']],
					'location_type_id' => $config['location_type_id'],
				));

			} catch ( Exception $e ) {
				// Ignore if none found
			}

			// Get form values for each processor field
			// $value is the field id
			$form_values = array();
			foreach ( $config as $key => $field_id ) {
				$form_values[$key] = Caldera_Forms::get_field_data( $field_id, $form );
			}

			$form_values['contact_id'] = $transdata['civicrm']['contact_id_' . $config['contact_link']]; // Contact ID set in Contact Processor

			// Pass Email ID if we got one
			if ( $phone ) {
				$form_values['id'] = $phone['id']; // Email ID
			}

			$create_phone = civicrm_api3( 'Phone', 'create', $form_values );

		}

	}

	/**
	 * Autopopulates Form with Civi data
	 *
	 * @uses 'caldera_forms_render_get_form' filter
	 *
	 * @since 0.2
	 *
	 * @param array $form The form
	 * @return array $form The modified form
	 */
	public function pre_render( $form ){

		// globalised transient object
		global $transdata;

		foreach ( $form['processors'] as $processor => $pr_id ) {
			if( $pr_id['type'] == $this->key_name ){

				if ( isset( $transdata['civicrm']['contact_id_' . $pr_id['config']['contact_link']] ) ) {
					try {

						$civi_contact_phone = civicrm_api3( 'Phone', 'getsingle', array(
							'sequential' => 1,
							'contact_id' => $transdata['civicrm']['contact_id_' . $pr_id['config']['contact_link']],
							'location_type_id' => $pr_id['config']['location_type_id'],
						));

					} catch ( Exception $e ) {
						// Ignore if we have more than one phone with same location type or none
					}
				}

				unset( $pr_id['config']['contact_link'], $pr_id['config']['location_type_id'] );

				if ( isset( $civi_contact_phone ) && ! isset( $civi_contact_phone['count'] ) ) {
					foreach ( $pr_id['config'] as $field => $value ) {
						if ( ! empty( $value ) ) {
							$form['fields'][$value]['config']['default'] = $civi_contact_phone[$field];
						}
					}
				}

				// Clear Phone data
				unset( $civi_contact_phone );
			}

		}

		return $form;
	}

}