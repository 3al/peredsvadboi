


<div id = "tcompactmenu-pager">
	<?php
		
		$curpath = "/".request_path();
		//echo $curpath; exit; 
		
		foreach($urls as $num => $url):
	?>
		<span>
			<a class="<?php echo $url->url == $curpath ? 'active': ''; ?>" href="<?php echo $url->url; ?>"><?php echo $num+1; ?></a>
			<?php //echo (($num+1)%20 == 0)? "<br />" : ""; ?>
		</span>
	<?php
		endforeach;
	?>
</div>
