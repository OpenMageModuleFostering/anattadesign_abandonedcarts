<?php

class AnattaDesign_AbandonedCarts_Helper_Data extends Mage_Core_Helper_Abstract {

	public function getStatisticsModel() {
		if ( $this->isAwesomeCheckoutActive() ) {
			return Mage::getModel( 'anattadesign_abandonedcarts/statistics' );
		} else {
			return Mage::getModel( 'anattadesign_abandonedcarts/opstatistics' );
		}
	}

	public function isAwesomeCheckoutActive() {
		return Mage::getConfig()->getModuleConfig( 'AnattaDesign_AwesomeCheckout' )->is( 'active', 'true' );
	}

	public function getMessage() {

		$cache = Mage::getSingleton( 'core/cache' );
		$payload = $cache->load( 'abandonedcart_payload' );

		if ( $payload === false ) {

			$payload = file_get_contents( 'http://api.anattadesign.com/abandonedcart/1alpha/fetch/payload' );
			$contents = json_decode( $payload, true );

			if ( $contents['status'] == 'success' ) {
				$message = $this->isAwesomeCheckoutActive() ? $contents['data']['ac'] : $contents['data']['non-ac'];
				// cache data for 2 days
				$cache->save( $payload, 'abandonedcart_payload', array( 'abandonedcart' ), 2 * 24 * 60 * 60 );
			} else {
				$message = false;
			}
		} else {
			$contents = json_decode( $payload, true );
			$message = $this->isAwesomeCheckoutActive() ? $contents['data']['ac'] : $contents['data']['non-ac'];
		}

		return $message;
	}

	public function getCurrencySymbol() {
		return Mage::app()->getLocale()->currency( Mage::app()->getStore()->getCurrentCurrencyCode() )->getSymbol();
	}

	public function getSalesVolume( $year, $month ) {

		$cache = Mage::getSingleton( 'core/cache' );
		$sales_volume = $cache->load( 'abandonedcart_sales_volume_' . $year . '_' . $month );

		if ( $sales_volume === false ) {
			// recalculate
			$orderTotals = Mage::getModel( 'sales/order' )->getCollection()
					->addAttributeToFilter( 'status', Mage_Sales_Model_Order::STATE_COMPLETE )
					->addAttributeToFilter( 'created_at', array( 'from' => date( 'Y-m-01' ) ) )
					->addAttributeToSelect( 'grand_total' )
					->getColumnValues( 'grand_total' )
			;

			$sales_volume = array_sum( $orderTotals );

			// cache it
			$cache->save( $sales_volume, 'abandonedcart_sales_volume_' . $year . '_' . $month, array( 'abandonedcart' ), 24 * 60 * 60 );
		}

		return $sales_volume;
	}

	public function trailingslashit( $string ) {
		return $this->untrailingslashit( $string ) . '/';
	}

	public function untrailingslashit( $string ) {
		return rtrim( $string, '/' );
	}

	public function ping() {

		// Get current version of the extension
		$connection = Mage::getSingleton( 'core/resource' )->getConnection( 'core_read' );
		$stmt = $connection->query( "SELECT version FROM core_resource WHERE code='anattadesign_abandonedcarts_setup';" );
		$data = $stmt->fetch();
		$version = $data['version'];

		$ping = array(
			'version' => $version,
			'site_name' => Mage::getStoreConfig( 'general/store_information/name' ),
			'url' => 'http://' . str_replace( array( 'http://', '/index.php/', '/index.php' ), '', Mage::getUrl() ) // making sure the url is in format - http://domain.com/
		);

		$ping['url'] = Mage::helper( 'anattadesign_abandonedcarts' )->trailingslashit( $ping['url'] );

		// make call
		$client = new Varien_Http_Client( 'http://api.anattadesign.com/abandonedcart/1alpha/collect/ping' );
		$client->setMethod( Varien_Http_Client::POST );
		$client->setParameterPost( 'ping', $ping );

		try {
			$response = $client->request();
			if ( $response->isSuccessful() ) {
				$json_response = json_decode( $response->getBody(), true );
				$ping_success = $json_response['status'] == 'success' ? true : false;
			}
		} catch ( Exception $e ) {
			$ping_success = false;
		}

		if ( $ping_success ) {
			// make sure ping is not rescheduled anymore
			Mage::getModel( 'core/config' )->deleteConfig( 'anattadesign_abandonedcarts_ping_rescheduled' );
		} else {
			// reschedule ping, increment counts if its already scheduled, so that we can see how many times it has failed
			// $ping_rescheduled = Mage::getStoreConfig( 'anattadesign_abandonedcarts_ping_rescheduled' );
			// Fetch directly from database to bypass Magento config cache.
			// Its better to bypass cache and make a sql query in favor of performance, sql query is not gonna run up on frontend side, except when all the cache is refreshed & extension is upgraded
			$stmt = $connection->query( "SELECT value FROM core_config_data WHERE path='anattadesign_abandonedcarts_ping_rescheduled' AND scope = 'default' AND scope_id = 0 LIMIT 1;" );
			$data = $stmt->fetch();
			if ( $data === false )
				$ping_rescheduled = 1;
			else
				$ping_rescheduled = intval( $data['value'] ) + 1;

			Mage::getModel( 'core/config' )->saveConfig( 'anattadesign_abandonedcarts_ping_rescheduled', $ping_rescheduled );
		}
	}

}