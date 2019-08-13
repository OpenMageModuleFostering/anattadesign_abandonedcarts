<?php

class AnattaDesign_AbandonedCarts_Model_Statistics extends Mage_Core_Model_Abstract {

	protected function _construct() {
		$this->_init( 'anattadesign_abandonedcarts/statistics' );
	}

	/**
	 * Save moved to step for quote.
	 *
	 * @param string $step
	 * @param int $quoteId
	 *
	 * @return AnattaDesign_AbandonedCarts_Model_Statistics
	 */
	public function saveStepMoved( $step, $quoteId ) {
		return $this->_saveStepData( array( 'step' => $step, 'quoteId' => $quoteId, 'moved' => 1 ) );
	}

	/**
	 * Save reached step for quote.
	 *
	 * @param string $step
	 * @param int $quoteId
	 *
	 * @return AnattaDesign_AbandonedCarts_Model_Statistics
	 */
	public function saveStepReached( $step, $quoteId ) {
		$data = array( 'step' => $step, 'quoteId' => $quoteId, 'reached' => 1, 'year' => date( 'Y' ), 'month' => date( 'n' ), 'date' => date( 'Y-m-d h:i:s' ) );
		return $this->_saveStepData( $data );
	}

	/**
	 * delete all records of a particular quote ID
	 *
	 * @param int $quoteId
	 *
	 * @throws Exception
	 * @return AnattaDesign_AbandonedCarts_Model_Statistics
	 */
	public function deleteByQuoteId( $quoteId ) {
		$quoteId = abs( intval( $quoteId ) );
		if ( !$quoteId )
			throw new Exception( 'Quote ID is required & should be greater than 0' );

		$collection = $this->getCollection();
		$collection->addFieldToFilter( 'sales_flat_quote_id', $quoteId );
		$collection->load();

		if ( count( $collection ) ) {
			foreach ( $collection as $model ) {
				$model->delete();
			}
		} else {
			return $this->delete();
		}

		return $this;
	}

	/**
	 * Save step data.
	 * Step and quote id are required.
	 *
	 * @param array $data
	 *
	 * @throws Exception
	 * @return AnattaDesign_AbandonedCarts_Model_Statistics
	 */
	protected function _saveStepData( array $data ) {
		if ( !Mage::helper( 'anattadesign_abandonedcarts' )->canTrackUser() ) {
			return false;
		}

		if ( !array_key_exists( 'step', $data ) ) {
			throw new Exception( 'Step key is required' );
		}

		// Prepare data
		$data = array_merge( $data, array( 'sales_flat_quote_id' => $data['quoteId'] ) );

		// Get existing statistics for this quote and step
		$collection = $this->getCollection();
		$collection->addFieldToFilter( 'sales_flat_quote_id', $data['quoteId'] );
		$collection->addFieldToFilter( 'step', $data['step'] );

		// TODO: Add date field to filter < 1 month

		$collection->addOrder( 'statistics_id' );
		$collection->load();

		if ( count( $collection ) ) {
			return $collection->fetchItem()->addData( $data )->save();
		} else {
			return $this->addData( $data )->save();
		}
	}

}
