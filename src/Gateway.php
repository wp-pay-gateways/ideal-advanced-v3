<?php
/**
 * Gateway.
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2021 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay
 */

namespace Pronamic\WordPress\Pay\Gateways\IDealAdvancedV3;

use Pronamic\WordPress\Pay\Banks\BankAccountDetails;
use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: iDEAL Advanced v3+ gateway
 * Description:
 * Copyright: 2005-2021 Pronamic
 * Company: Pronamic
 *
 * @author  Remco Tolsma
 * @version 2.0.5
 * @since   1.0.0
 */
class Gateway extends Core_Gateway {
	/**
	 * Client.
	 *
	 * @var Client
	 */
	protected $client;

	/**
	 * Constructs and initializes an iDEAL Advanced v3 gateway
	 *
	 * @param Config $config Config.
	 */
	public function __construct( Config $config ) {
		parent::__construct( $config );

		$this->set_method( self::METHOD_HTTP_REDIRECT );

		// Supported features.
		$this->supports = array(
			'payment_status_request',
		);

		// Client.
		$client = new Client();

		$client->set_acquirer_url( (string) $config->get_payment_server_url() );

		$client->merchant_id          = (string) $config->get_merchant_id();
		$client->sub_id               = (string) $config->get_sub_id();
		$client->private_key          = (string) $config->get_private_key();
		$client->private_key_password = (string) $config->get_private_key_password();
		$client->private_certificate  = (string) $config->get_private_certificate();

		$this->client = $client;
	}

	/**
	 * Get issuers
	 *
	 * @see Core_Gateway::get_issuers()
	 * @return array<int, array<string, array<string, string>|string>>
	 */
	public function get_issuers() {
		$groups = array();

		try {
			$directory = $this->client->get_directory();
		} catch ( \Exception $e ) {
			$this->error = new \WP_Error( 'ideal_advanced_v3_error', $e->getMessage() );

			return $groups;
		}

		if ( null === $directory ) {
			return $groups;
		}

		foreach ( $directory->get_countries() as $country ) {
			$issuers = array();

			foreach ( $country->get_issuers() as $issuer ) {
				$id   = $issuer->get_id();
				$name = $issuer->get_name();

				if ( null === $id || null === $name ) {
					continue;
				}

				$issuers[ $id ] = $name;
			}

			$groups[] = array(
				'name'    => $country->get_name(),
				'options' => $issuers,
			);
		}

		return $groups;
	}

	/**
	 * Get supported payment methods
	 *
	 * @see Core_Gateway::get_supported_payment_methods()
	 * @return array<int, string>
	 */
	public function get_supported_payment_methods() {
		return array(
			PaymentMethods::IDEAL,
		);
	}

	/**
	 * Is payment method required to start transaction?
	 *
	 * @see   Core_Gateway::payment_method_is_required()
	 * @since 1.1.5
	 */
	public function payment_method_is_required() {
		return true;
	}

	/**
	 * Start
	 *
	 * @see Pronamic_WP_Pay_Gateway::start()
	 *
	 * @param Payment $payment Payment.
	 */
	public function start( Payment $payment ) {
		// Purchase ID.
		$purchase_id = $payment->format_string( $this->config->get_purchase_id() );

		$payment->set_meta( 'purchase_id', $purchase_id );

		// Transaction.
		$transaction = new Transaction();
		$transaction->set_purchase_id( $purchase_id );
		$transaction->set_amount( $payment->get_total_amount()->get_value() );
		$transaction->set_currency( $payment->get_total_amount()->get_currency()->get_alphabetic_code() );
		$transaction->set_expiration_period( 'PT30M' );
		$transaction->set_description( $payment->get_description() );
		$transaction->set_entrance_code( $payment->get_entrance_code() );

		$customer = $payment->get_customer();

		if ( null !== $customer ) {
			$transaction->set_language( $customer->get_language() );
		}

		// Create transaction.
		$result = $this->client->create_transaction( $transaction, $payment->get_return_url(), (string) $payment->get_issuer() );

		if ( null !== $result->issuer ) {
			$authentication_url = $result->issuer->get_authentication_url();

			if ( null !== $authentication_url ) {
				$payment->set_action_url( $authentication_url );
			}
		}

		if ( null !== $result->transaction ) {
			$payment->set_transaction_id( $result->transaction->get_id() );
		}
	}

	/**
	 * Update status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public function update_status( Payment $payment ) {
		$transaction_id = (string) $payment->get_transaction_id();

		// Try to retrieve payment status.
		try {
			$result = $this->client->get_status( $transaction_id );
		} catch ( \Exception $e ) {
			$note = sprintf(
				/* translators: %s: exception message */
				__( 'Error getting payment status: %s', 'pronamic_ideal' ),
				$e->getMessage()
			);

			$payment->add_note( $note );

			return;
		}

		// Check transaction result.
		if ( null === $result->transaction ) {
			return;
		}

		// Update payment with transaction data.
		$transaction = $result->transaction;

		$payment->set_status( $transaction->get_status() );

		$consumer_bank_details = $payment->get_consumer_bank_details();

		if ( null === $consumer_bank_details ) {
			$consumer_bank_details = new BankAccountDetails();

			$payment->set_consumer_bank_details( $consumer_bank_details );
		}

		$consumer_bank_details->set_name( $transaction->get_consumer_name() );
		$consumer_bank_details->set_iban( $transaction->get_consumer_iban() );
		$consumer_bank_details->set_bic( $transaction->get_consumer_bic() );
	}
}
