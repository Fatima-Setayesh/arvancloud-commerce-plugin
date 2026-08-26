<?php
/** Idempotent settlement aggregation. No undocumented payout API is invoked. @package Arvan_Reseller */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class Arvan_Reseller_Settlement {
	private $database;
	public function __construct( Arvan_Reseller_Database $database ) {
		$this->database = $database; }

	/** @return array|WP_Error */
	public function settle_period( $start, $end, $currency = 'IRR' ) {
		$start_ts = strtotime( (string) $start . ' UTC' );
		$end_ts   = strtotime( (string) $end . ' UTC' );
		if ( false === $start_ts || false === $end_ts || $start_ts >= $end_ts ) {
			return new WP_Error( 'arvan_reseller_invalid_settlement_period', __( 'Settlement period is invalid.', 'arvan-reseller' ) ); }
		$start     = gmdate( 'Y-m-d H:i:s', $start_ts );
		$end       = gmdate( 'Y-m-d H:i:s', $end_ts );
		$currency  = strtoupper( preg_replace( '/[^A-Z]/', '', (string) $currency ) );
		$currency  = 3 === strlen( $currency ) ? $currency : 'IRR';
		$invoices  = $this->issue_invoices_for_period( $start, $end, $currency );
		if ( is_wp_error( $invoices ) ) {
			return $invoices;
		}
		$reference = 'stl_' . hash( 'sha256', 'settlement-v1|' . $start . '|' . $end . '|' . $currency );
		$existing  = $this->database->get_settlement_by_reference( $reference );
		if ( null !== $existing && 'completed' === (string) $existing['status'] ) {
			$result                  = $this->serialize( $existing, true );
			$result['invoice_count'] = count( $invoices );
			return $result; }
		$totals = $this->database->aggregate_usage_period( $start, $end, $currency );
		$id     = null !== $existing ? (int) $existing['id'] : $this->database->create_settlement(
			array(
				'settlement_reference'  => $reference,
				'period_start'          => $start,
				'period_end'            => $end,
				'base_cost_minor'       => $totals['base_cost_minor'],
				'customer_charge_minor' => $totals['customer_charge_minor'],
				'reseller_share_minor'  => $totals['reseller_share_minor'],
				'currency'              => $currency,
				'status'                => 'pending',
				'adapter'               => 'mock',
				'metadata'              => wp_json_encode(
					array(
						'usage_count'       => $totals['usage_count'],
						'external_transfer' => false,
					)
				),
			)
		);
		if ( false === $id ) {
			$existing = $this->database->get_settlement_by_reference( $reference );
			return null !== $existing ? $this->serialize( $existing, true ) : new WP_Error( 'arvan_reseller_settlement_failed', __( 'Unable to persist settlement.', 'arvan-reseller' ) ); }
		if ( null === $existing ) {
			$this->database->create_audit_log( 'settlement_pending', 'settlement', (string) $id, array( 'reference' => $reference ), 0, 0 );
		}
		if ( false === $this->database->update(
			'settlements',
			array(
				'status'     => 'processing',
				'adapter'    => 'mock',
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id )
		) ) {
			$this->database->update(
				'settlements',
				array(
					'status'     => 'failed',
					'updated_at' => current_time( 'mysql', true ),
				),
				array( 'id' => (int) $id )
			);
			$this->database->create_audit_log(
				'settlement_failed',
				'settlement',
				(string) $id,
				array(
					'reference'  => $reference,
					'error_code' => 'processing_transition_failed',
				),
				0,
				0
			);
			return new WP_Error( 'arvan_reseller_settlement_failed', __( 'Settlement processing could not start.', 'arvan-reseller' ) );
		}
		$this->database->create_audit_log( 'settlement_processing', 'settlement', (string) $id, array( 'reference' => $reference ), 0, 0 );
		if ( false === $this->database->update(
			'settlements',
			array(
				'status'     => 'completed',
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id )
		) ) {
			$this->database->update(
				'settlements',
				array(
					'status'     => 'failed',
					'updated_at' => current_time( 'mysql', true ),
				),
				array( 'id' => (int) $id )
			);
			$this->database->create_audit_log(
				'settlement_failed',
				'settlement',
				(string) $id,
				array(
					'reference'  => $reference,
					'error_code' => 'completion_transition_failed',
				),
				0,
				0
			);
			return new WP_Error( 'arvan_reseller_settlement_failed', __( 'Settlement could not be completed.', 'arvan-reseller' ) );
		}
		$this->database->create_audit_log(
			'settlement_completed',
			'settlement',
			(string) $id,
			array(
				'reference'   => $reference,
				'usage_count' => $totals['usage_count'],
			),
			0,
			0
		);
		return array_merge(
			array(
				'id'         => (int) $id,
				'reference'  => $reference,
				'status'     => 'completed',
				'currency'   => $currency,
				'idempotent' => false,
				'invoice_count' => count( $invoices ),
			),
			$totals
		);
	}

	/** Issue one immutable daily invoice per customer/currency usage group. @return array|WP_Error */
	public function issue_invoices_for_period( $start, $end, $currency ) {
		$results = array();
		foreach ( $this->database->aggregate_customer_usage_period( $start, $end, $currency ) as $totals ) {
			$customer_id = absint( $totals['customer_id'] ?? 0 );
			if ( $customer_id <= 0 ) {
				continue;
			}
			$reference = 'inv_' . hash( 'sha256', 'invoice-v1|' . $customer_id . '|' . $start . '|' . $end . '|' . $currency );
			$existing  = $this->database->get_invoice_by_reference( $reference );
			if ( null !== $existing ) {
				$results[] = array( 'id' => (int) $existing['id'], 'reference' => $reference, 'idempotent' => true );
				continue;
			}
			$uncovered = max( 0, (int) ( $totals['uncovered_minor'] ?? 0 ) );
			$created   = true;
			$id        = $this->database->create_invoice(
				array(
					'customer_id'          => $customer_id,
					'invoice_reference'    => $reference,
					'period_start'         => $start,
					'period_end'           => $end,
					'base_cost_minor'      => max( 0, (int) ( $totals['base_cost_minor'] ?? 0 ) ),
					'reseller_share_minor' => max( 0, (int) ( $totals['reseller_share_minor'] ?? 0 ) ),
					'total_minor'          => max( 0, (int) ( $totals['total_minor'] ?? 0 ) ),
					'currency'             => $currency,
					'status'               => 0 === $uncovered ? 'paid' : 'issued',
					'metadata'             => wp_json_encode(
						array(
							'usage_count'    => max( 0, (int) ( $totals['usage_count'] ?? 0 ) ),
							'charged_minor'  => max( 0, (int) ( $totals['charged_minor'] ?? 0 ) ),
							'uncovered_minor' => $uncovered,
						)
					),
				)
			);
			if ( false === $id ) {
				$existing = $this->database->get_invoice_by_reference( $reference );
				if ( null === $existing ) {
					return new WP_Error( 'arvan_reseller_invoice_failed', __( 'Unable to persist the customer invoice.', 'arvan-reseller' ) );
				}
				$id      = (int) $existing['id'];
				$created = false;
			}
			if ( $created ) {
				$this->database->create_audit_log( 'invoice_issued', 'invoice', (string) $id, array( 'reference' => $reference, 'status' => 0 === $uncovered ? 'paid' : 'issued' ), $customer_id, 0 );
			}
			$results[] = array( 'id' => (int) $id, 'reference' => $reference, 'idempotent' => ! $created );
		}
		return $results;
	}

	/** Settle every currency represented by the previous UTC day's usage. @return array|WP_Error */
	public function settle_previous_day() {
		$end        = gmdate( 'Y-m-d 00:00:00' );
		$start      = gmdate( 'Y-m-d 00:00:00', strtotime( $end . ' UTC' ) - DAY_IN_SECONDS );
		$currencies = $this->database->get_usage_currencies_period( $start, $end );

		// Preserve the established empty-day settlement record for operational visibility.
		if ( empty( $currencies ) ) {
			$settings   = get_option( 'arvan_reseller_settings', array() );
			$currencies = array( (string) ( $settings['currency'] ?? 'IRR' ) );
		}

		$results = array();
		foreach ( $currencies as $currency ) {
			$result = $this->settle_period( $start, $end, $currency );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$results[] = $result;
		}

		return array(
			'period_start' => $start,
			'period_end'   => $end,
			'settlements'  => $results,
		);
	}
	private function serialize( array $row, $idempotent ) {
		return array(
			'id'                    => (int) $row['id'],
			'reference'             => (string) $row['settlement_reference'],
			'status'                => (string) $row['status'],
			'period_start'          => (string) $row['period_start'],
			'period_end'            => (string) $row['period_end'],
			'base_cost_minor'       => (int) $row['base_cost_minor'],
			'customer_charge_minor' => (int) $row['customer_charge_minor'],
			'reseller_share_minor'  => (int) $row['reseller_share_minor'],
			'currency'              => (string) $row['currency'],
			'idempotent'            => (bool) $idempotent,
		); }
}
