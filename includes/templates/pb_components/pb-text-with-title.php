<?php

// Set up sensibly named variables for module
$title            = $data['title'];
$content          = $data['text'];
?>

<div id="meta_<?php echo $data['zone_id'] ?>" class="section row test">

	<div class="col-xs-12">

		<h2><?php echo $title ?></h2>

		<?php echo wpautop( $content ) ?>

  </div>

</div>