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
		$reference = 'stl_' . hash( 'sha256', 'settlement-v1|' . $start . '|' . $end . '|' . $currency );
		$existing  = $this->database->get_settlement_by_reference( $reference );
		if ( null !== $existing && 'completed' === (string) $existing['status'] ) {
			return $this->serialize( $existing, true ); }
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
				'idempotent' => false,
			),
			$totals
		);
	}

	public function settle_previous_day() {
		$end = gmdate( 'Y-m-d 00:00:00' );
		return $this->settle_period( gmdate( 'Y-m-d 00:00:00', strtotime( $end . ' UTC' ) - DAY_IN_SECONDS ), $end, (string) ( get_option( 'arvan_reseller_settings', array() )['currency'] ?? 'IRR' ) ); }
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
