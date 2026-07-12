<?php
/**
 * Footer Template
 * 
 * Main footer template for dashboard
 *
 * @package WebinoCRM
 * @since 2.1.0
 * @var array $component_data Data from component
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- Start::main-footer -->
<footer class="footer mt-auto py-3">
	<div class="container-fluid px-3 px-md-4">
		<span class="text-muted small">
			<?php $this->load_template( 'parts/copyright.php' ); ?>
		</span>
	</div>
</footer>
<!-- End::main-footer -->

