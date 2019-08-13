<?php

class AnattaDesign_AbandonedCarts_Model_Observer {

	public function uponAdminLogin() {
		$this->ping();
		$this->checkLatestVersion();
	}

	public function ping() {
		// Instead of using getStoreConfig make a direct sql query to bypass magento cache
		// $is_ping_rescheduled = Mage::getStoreConfig( 'anattadesign_abandonedcarts_ping_rescheduled' );
		$connection = Mage::getSingleton( 'core/resource' )->getConnection( 'core_read' );
		$stmt = $connection->query( "SELECT value FROM core_config_data WHERE path='anattadesign_abandonedcarts_ping_rescheduled' AND scope = 'default' AND scope_id = 0 LIMIT 1;" );
		$data = $stmt->fetch();
		// If $data is false, then that means there is no row in the table, and no ping has been rescheduled
		if ( $data !== false )
			Mage::helper( 'anattadesign_abandonedcarts' )->ping();
	}

	public function checkLatestVersion() {
		$contents = file_get_contents( 'http://api.anattadesign.com/abandonedcart/1alpha/status/latestVersion' );
		$latest = json_decode( $contents );

		if ( $latest->status == "success" ) {

			if ( $latest->latestVersion == Mage::getStoreConfig( 'anattadesign_abandonedcart_latest_checked_version' ) )
				return;

			$connection = Mage::getSingleton( 'core/resource' )->getConnection( 'core_read' );
			$stmt = $connection->query( "SELECT version FROM core_resource WHERE code='anattadesign_abandonedcarts_setup'" );
			$data = $stmt->fetch();
			$version = $data['version'];

			if ( $latest->latestVersion != $version ) {
				Mage::getModel( 'adminnotification/inbox' )
						->setSeverity( Mage_AdminNotification_Model_Inbox::SEVERITY_NOTICE )
						->setTitle( "My Abandoned Cart {$latest->latestVersion} is now available" )
						->setDateAdded( gmdate( 'Y-m-d H:i:s' ) )
						->setUrl( 'http://www.myabandonedcarts.com/' )
						->setDescription( 'Your version of My Abandoned Cart is currently not up-to-date. Please <a href="http://www.myabandonedcarts.com/">click here</a> to get the latest version.' )
						->save();
				Mage::getModel( 'core/config' )->saveConfig( 'anattadesign_abandonedcart_latest_checked_version', $latest->latestVersion );
			}
		}
	}

	public function addJavascriptBlock( $observer ) {

		$controller = $observer->getAction();

		if ( !$controller instanceof Mage_Adminhtml_DashboardController )
			return;

		$layout = $controller->getLayout();
		$block = $layout->createBlock( 'core/text' );
		$block->setText(
			'<script type="text/javascript">
				var anattadesign_abandonedcarts = {
					url: "' . Mage::helper( 'adminhtml' )->getUrl( 'abandonedcarts/widget/render/' ) . '"
				};
			</script>'
		);

		$layout->getBlock( 'js' )->append( $block );
	}

}