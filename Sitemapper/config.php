<?php 
/*
Plugin Name: Sitemapper
Plugin URI: http://www.caw.ac.uk
Description: A custom sitemap generator
Author: Sami Cooper
Version: 0.1
Author URI: http://www.caw.ac.uk
*/
?>
<?php #SM_post_types
function SM_post_types($output = 'names'){
	$args = array(
		'public' => true,
	);
	$return = get_post_types($args, $output);
	return $return;
}
?>
<?php #Post Meta
//register
function SM_register_post_meta() {
    $screens = array( 'post', 'page');
    foreach ( $screens as $screen ) {
        add_meta_box('SM_post_meta', 'Sitemapper', 'SM_post_meta', $screen, 'side', 'default');
    }
}
add_action( 'add_meta_boxes', 'SM_register_post_meta' );
//meta
function SM_post_meta($post){
	echo '<style>
		#SM_post_meta label{
			display: block;
		}
		#SM_post_meta select{
			margin-bottom: 10px;
		}
	</style>';
	echo '<div id="SM_post_meta">';
		//CHECK
		echo '<input type="hidden" name="_SM_nonce" id="_SM_nonce" value="' . wp_create_nonce( plugin_basename(__FILE__) ) . '" />';
		//modified
		$this_label = '_SM_modified';
		echo '<label for="' . $this_label . '">' . 'Last updated: ' . '</label>';
		echo '<p>' .  get_the_modified_date() . '</p>';
		echo '<input type="hidden" name="' . $this_label . '" id="' . $this_label . '" value="' . get_the_modified_date() . '" />';
		//changefreq
		$this_label = '_SM_changefreq';
		$this_value = get_post_meta($post->ID, $this_label, true);
		if($this_value == ''){
			$this_value = 'monthly';
		}
		echo '<label for="' . $this_label . '">' . 'Change Frequency: '  . '</label>';
		$this_option = array('always','hourly','daily','weekly','monthly','yearly','never');
		echo '<select name="' . $this_label . '" id="' . $this_label . '">';
			foreach($this_option as $v){
				echo '<option value="' . $v . '"' . ($v == $this_value ? 'selected' : '') . '>' . $v . '</option>';
			}
		echo '</select>';
		//priority
		$this_label = '_SM_priority';
		$this_value = get_post_meta($post->ID, $this_label, true);
		if($this_value == ''){
			$this_value = '0.5';
		}
		echo '<label for="' . $this_label . '">' . 'Priority: '  . '</label>';
		echo '<select name="' . $this_label . '" id="' . $this_label . '">';
			for($i=0.1;$i<=1;$i = $i + 0.1){
				echo '<option value="' . $i . '"' . ($i == $this_value ? 'selected' : '') . '>' . $i . '</option>';
			}
		echo '</select>';
		//include
		$this_label = '_SM_include';
		$this_value = get_post_meta($post->ID, $this_label, true);
		if($this_value == ''){
			$this_value = 'true';
		}
		echo '<label for="' . $this_label . '">' . 'Include: '  . '</label>';
		$this_option = array('true','false');
		echo '<select name="' . $this_label . '" id="' . $this_label . '">';
			foreach($this_option as $v){
				echo '<option value="' . $v . '"' . ($v == $this_value ? 'selected' : '') . '>' . $v . '</option>';
			}
		echo '</select>';
	echo '</div>';
}
//Save
$save_meta="";
function SM_post_meta_save($post_id, $post) {
	//CHECK
	if ( isset ($_POST['_SM_nonce']) ) {
		if ( !wp_verify_nonce( $_POST['_SM_nonce'], plugin_basename(__FILE__) )) { return $post->ID; }
	}
	if ( !current_user_can( 'edit_post', $post->ID )) {	return $post->ID; }
	//END CHECK	
	$save_item = array(
		'_SM_modified', '_SM_changefreq', '_SM_priority', '_SM_include', 
	);
	foreach($save_item as $saved){
		if ( isset ($_POST[$saved]) ) { 
			$save_meta[$saved] = $_POST[$saved];
		}
	}
	if (!empty($save_meta)){
		foreach ($save_meta as $key => $value) { // Cycle through the $save_meta array!
			if( $post->post_type == 'revision' ) return; // Don't store custom data twice
			//$value = implode(',', (array)$value); // If $value is an array, make it a CSV (unlikely)
			if(get_post_meta($post->ID, $key, FALSE)) { // If the custom field already has a value
				update_post_meta($post->ID, $key, $value);
			} else { // If the custom field doesn't have a value
				add_post_meta($post->ID, $key, $value);
			}
			if(!$value) delete_post_meta($post->ID, $key); // Delete if blank
		}
	}
}
add_action('save_post', 'SM_post_meta_save', 1, 2);
?>
<?php #Builder
function SM_builder( $return = '', $show_output = true){
	$post_list = array();

	$SM_options = get_option('SM_options');
	if( isset($SM_options['post']) && !empty($SM_options['post']) ){
		foreach($SM_options['post'] as $v){
			$post_types[] = $v;
		}
	}else{
		$post_types[] = 'page';
		$post_types[] = 'post';
	}
	//print_r($SM_options);
	//print_r($post_types);
	foreach($post_types as $post_type){
		$args = array(
			'posts_per_page' => -1,
			'nopaging' => true,
			'post_type' => $post_type, 
		);
		$post_query = new WP_Query($args);
		
		while ( $post_query->have_posts() ) {
			$post_query->the_post();
			//https://www.sitemaps.org/protocol.html
			
			$include_sitemap = get_post_meta( get_the_ID(), '_SM_include', true );
            $changefreq = get_post_meta( get_the_ID(), '_SM_changefreq', true );
            $priority = get_post_meta( get_the_ID(), '_SM_priority', true );
			$status = get_post_status( get_the_ID() );
			
			$include = true;
						
			if($include_sitemap == 'false' || $status != 'publish'){
				$include = false;
			}
			
			if($include === false){
				
			}else{
				//if empty changefreq
				if($changefreq == ''){
					if($post_type == 'post'){
						$changefreq = 'yearly';
					}else{
						$changefreq = 'monthly';
					}
				}
				//if empty priority
				if($priority){
					$priority = (int)$priority;
				}else{
					$priority = 0.5;
				}

				$parent = wp_get_post_parent_id( get_the_ID() );

				$this_post['loc'] = get_the_permalink();
				$this_post['lastmod'] = get_the_modified_date('Y-m-d') . get_the_modified_time('\Th:m:sP');
				$this_post['changefreq'] = $changefreq; //always, hourly, daily, weekly, monthly, yearly, never (archived)
				$this_post['priority'] = $priority; //0.0 to 1.0 default=0.5
				$this_post['ID'] = get_the_ID();
				$this_post['title'] = get_the_title();
				//$this_post['status'] = $status;
				//$this_post['parent'] = $parent;

				//categorise
				if($post_type == 'page'){
					if($parent == 0){
						$post_list[$post_type][ get_the_ID() ][0] = $this_post;
					}else{
						$post_list[$post_type][ $parent ][ get_the_ID() ] = $this_post;
					}
				}else{
					$post_list[$post_type][ get_the_ID() ] = $this_post;
				}
			}//end if excluded
		}//end while post query
	}//end foreach post type
	$post_list_raw = $post_list;
	//homepage
	$post_list['page']['home'][0]['loc'] = home_url();
	//$post_list['page']['home'][0]['lastmod'] = '';
	$post_list['page']['home'][0]['changefreq'] = 'hourly';
	$post_list['page']['home'][0]['priority'] = 1;
	$post_list['page']['home'][0]['title'] = 'Home';
    //sort
    foreach($post_list as $k1 => $v1){
        ksort($post_list[$k1]);
        if(is_array($post_list[$k1]) ){
            foreach($post_list[$k1] as $k2 => $v2){
                ksort($post_list[$k1][$k2]);
            }
        }
    }
    //un-categorise
    $uncategory = array();
    foreach($post_list['page'] as $k1 => $v1){
        foreach($post_list['page'][$k1] as $k2 => $v2){
			if( isset($v2['ID']) ){
            	$uncategory[ $v2['ID'] ] = $v2;
			}
        }
    }
    $post_list['page'] = NULL;
    $post_list['page'] = $uncategory;
	//external
	/*include plugin_dir_path( __FILE__ ) . 'accessplanit.php';
	$post_list = array_merge($post_list, $course_array);*/
	//extra
	if(isset($SM_options['extra']) && !empty($SM_options['extra']) ){
		foreach($SM_options['extra'] as $k => $v){
			$SM_options['extra'][$k]['loc'] = site_url() . $SM_options['extra'][$k]['loc'];
		}
		$add_extra['extra'] = $SM_options['extra'];
		$post_list = array_merge($post_list, $add_extra);
	}
	//output
	if($return == 'array'){
		return $post_list;
	}else if($return == 'raw'){
		return $post_list_raw;
	}else if($return == 'urls'){
		if(is_multisite() === false){
			$multisite = '';
		}else{
			$multisite = '-' . get_blog_details()->blog_id;
		}
		$sitemap_url[] = home_url() . '/sitemap' . $multisite . '.xml';
		foreach($post_list as $post_type => $posts){
			$sitemap_url[] = home_url() . '/sitemap' . $multisite . '-' . $post_type . '.xml';
		}
		return $sitemap_url;
	}else if($return == 'generate'){
		//sitemap index
		if(is_multisite() === false){
			$multisite = '';
		}else{
			$multisite = '-' . get_blog_details()->blog_id;
		}
		$xml = new XMLWriter();
        $xml->openURI('file://' . ABSPATH . 'sitemap' . $multisite . '.xml');
        $xml->startDocument('1.0', 'UTF-8');
            $xml->startElement('sitemapindex');
            $xml->writeAttribute('xmlns','http://www.sitemaps.org/schemas/sitemap/0.9');
                foreach($post_list as $post_type => $posts){
					$sitemap_url[] = home_url() . '/sitemap' . $multisite . '-' . $post_type . '.xml';
					$xml->startElement('sitemap');
						$xml->writeElement('loc',home_url() . '/sitemap' . $multisite . '-' . $post_type . '.xml');
						$xml->writeElement('lastmod',date('Y-m-d\Th:m:sP'));
					$xml->endElement();
				}
            $xml->endElement();
        $xml->endDocument();
        $xml->flush();
        unset($xml); //important!
		//sitemaps
		foreach($post_list as $post_type => $posts){
			$xml = new XMLWriter();
			$xml->openURI('file://' . ABSPATH . 'sitemap' . $multisite . '-' . $post_type . '.xml');
			$xml->startDocument('1.0', 'UTF-8');
				$xml->startElement('urlset');
				$xml->writeAttribute('xmlns','http://www.sitemaps.org/schemas/sitemap/0.9');
					foreach($post_list[$post_type] as $k1 => $v1){
						$xml->startElement('url');
							foreach($post_list[$post_type][$k1] as $k2 => $v2){
								$xml->writeElement($k2, $v2);
							}
						$xml->endElement();
					}
				$xml->endElement();
			$xml->endDocument();
			$xml->flush();
			unset($xml); //important!
		}
		//submit to google
		$submit_url_to_google = 'http://www.google.com/webmasters/sitemaps/ping?sitemap='.htmlentities($sitemap_url[0]);
 		$response = file_get_contents($submit_url_to_google);
		if($show_output === true){
			if($response){
				echo '<details style="margin-bottom: 10px;"><summary>Sitemap submitted to Google</summary>';
					echo '<div style="padding: 1px 20px 20px 20px; font-size: 0.8em; background: #ccc;">';
						echo $response;
					echo '<div>';
				echo '</details>';
			}else{
				echo '<p>Failed to submit sitemap to Google</p>';
			}
		}
		//submit to bing
		$submit_url_to_bing = "http://www.bing.com/webmaster/ping.aspx?siteMap=" . urlencode($sitemap_url[0]);
		$response = file_get_contents($submit_url_to_bing);
		if($show_output === true){
			if(strpos('Warning',$response) === false){
				echo '<details style="margin-bottom: 10px;"><summary>Sitemap submitted to Bing</summary>';
					echo '<div style="padding: 20px; font-size: 0.8em; background: #ccc;">';
						echo $response;
					echo '<div>';
				echo '</details>';
			}else{
				echo '<p>Failed to submit sitemap to Bing</p>';
			}
		}
	}
}
?>
<?php #build on save post
function SM_builder_on_save(){
	SM_builder('generate',false);
}
add_action( 'save_post', 'SM_builder_on_save' );
?>
<?php #head/?sitemap
function SM_head(){
	if(isset($_GET['sitemap']) ){
		SM_builder('generate',false);
		echo '<script>alert(\'Sitemap generated\')</script>';
	}
}
add_action('wp_head','SM_head');
?>
<?php #Dashboard
add_action( 'admin_menu', 'SM_create_dash' );
function SM_create_dash() {
	//add_submenu_page($parent_slug,$page_title,$menu_title,$capability,$menu_slug, callable $function = '' );
    add_submenu_page(
        'tools.php',
        'Sitemapper',
        'Sitemapper',
        'manage_options',
        '/site-mapper.php',
		'SM_dash'
    );
}
function SM_dash(){
	//include plugin_dir_path( __FILE__ ) . '.php';
	echo '<div class="wrap">';
		echo '<h1>Sitemapper</h1>';
		echo '<p>Sitemap generated.</p>';
		echo '<p><i>The sitemap is also generated when a post is saved and by visiting; anypage/?sitemap</i></p>';
		SM_builder('generate');
		
		//form register/save/load
		add_action( 'admin_init', 'SM_options' );
		function SM_options() {
			register_setting( 'SM_dash', 'SM_options' );
		}
		if( isset($_POST['SM_options'] ) ){
			foreach($_POST['SM_options']['extra'] as $k => $v){
				if( !$v['loc'] || !$v['changefreq'] || !$v['priority'] ){
					unset($_POST['SM_options']['extra'][$k]);
				}
			}
			update_option('SM_options',$_POST['SM_options']);
		}
		$SM_options = get_option('SM_options');
		
		//form
		echo '<div style="border: 1px solid #555; padding: 10px; margin-bottom: 10px;">';
			echo '<form action="" method="post">';
				//post types
				echo '<p>Include additional post types other than \'post\' and \'page\' (ignored if post type is empty):</p>';
				$get_post_types = SM_post_types();
				foreach($get_post_types as $v){
					if($v == 'post' || $v == 'page'){
						echo '<input type="hidden" name="SM_options[post][' . $v . ']" value="' . $v . '">';
					}else{
						echo '<label>';
						echo '<input type="checkbox" name="SM_options[post][' . $v . ']" value="' . $v . '"' . (isset($SM_options['post'][$v]) ? ' checked' : '') . '>';
						echo  $v . '&nbsp;&nbsp;&nbsp;</label>';
					}
				}
				//additional pages
				echo '<p>Include additional pages:</p>';
				if(!isset($SM_options['extra']) || empty($SM_options['extra']) ){
					//if empty create the first one
					$SM_options_count = 0;
					$SM_options['extra'] = array();
					$SM_options['extra'][0]['loc'] = '';
					$SM_options['extra'][0]['changefreq'] = '';
					$SM_options['extra'][0]['priority'] = '';
					$SM_options['extra'][0]['title'] = '';
					$SM_options['extra'][0]['ID'] = '';
				}else{
					//otherwise add an extra empty one
					$SM_options_count = count($SM_options['extra']);
					$SM_options['extra'][$SM_options_count]['loc'] = '';
					$SM_options['extra'][$SM_options_count]['changefreq'] = '';
					$SM_options['extra'][$SM_options_count]['priority'] = '';
					$SM_options['extra'][0]['title'] = '';
					$SM_options['extra'][0]['ID'] = '';
				}
				for($i=0;$i<=$SM_options_count;$i++){
					echo '<div style="width: 100%; margin-bottom: 10px">';
						echo '<input type="text" name="SM_options[extra][' . $i . '][loc]" value="' . $SM_options['extra'][$i]['loc'] . '" style="vertical-align: middle; width: 400px; font-size: 0.8em;" placeholder="URL eg: \'/page/page\'">';
						
						//changefreq
						//echo '<input type="text" name="SM_options[extra][' . $i . '][changefreq]" value="' . $SM_options['extra'][$i]['changefreq'] . '" placeholder="Change frequency">';
						$this_value = $SM_options['extra'][$i]['changefreq'];
						if($this_value == ''){
							$this_value = 'monthly';
						}		
						$this_option = array('always','hourly','daily','weekly','monthly','yearly','never');
						echo '<select name="SM_options[extra][' . $i . '][changefreq]">';
							foreach($this_option as $v){
								echo '<option value="' . $v . '"' . ($v == $this_value ? 'selected' : '') . '>' . $v . '</option>';
							}
						echo '</select>';
						//priority
						//echo '<input type="text" name="SM_options[extra][' . $i . '][priority]" value="' . $SM_options['extra'][$i]['priority'] . '" placeholder="Priority">';
						$this_value = $SM_options['extra'][$i]['priority'];
						if($this_value == ''){
							$this_value = '0.5';
						}
						echo '<select name="SM_options[extra][' . $i . '][priority]">';
							for($p=0.1;$p<=1;$p = $p + 0.1){
								echo '<option value="' . $p . '"' . ($p == $this_value ? 'selected' : '') . '>' . $p . '</option>';
							}
						echo '</select>';
					
						echo '<input type="hidden" name="SM_options[extra][' . $i . '][title]" value="">';
						echo '<input type="hidden" name="SM_options[extra][' . $i . '][ID]" value="">';
					echo '</div>';
				}
				//submit
				echo '<p><input type="submit" name="submit" value="submit"></p>';
			echo '</form>';
		echo '</div>';

		//XML links
		echo '<div style="border: 1px solid #555; padding: 10px; margin-bottom: 10px;">';
			echo '<p>Sitemap links:</p>';
			if(is_multisite() === true){
				echo '<p>This is a WordPress Multisite, therefore multiple sitemaps will be created denoted by the blog_id e.g. sitemap-<i>blog_id</i>.xml</p>';
			}
			$sitemap_urls = SM_builder('urls');
			//foreach($sitemap_urls as $v){
			for($i=0;$i<count($sitemap_urls);$i++){
				if($i == 0){
					echo '<p><a href="' . $sitemap_urls[$i] . '" target="_blank">' . $sitemap_urls[$i] . '</a> (Submit this sitemap index URL to the search engines)</p>';
				}else{
					echo '<p><a href="' . $sitemap_urls[$i] . '" target="_blank">' . $sitemap_urls[$i] . '</a></p>';
				}
			}
		echo '</div>';

		//Summary
		echo '<div style="border: 1px solid #555; padding: 10px; margin-bottom: 10px;">';
			echo '<p>Summary:</p>';
			$build = SM_builder('array');
			foreach($build as $post_type => $posts){
				echo '<p>' . ucfirst($post_type) . ' (' . count($build[$post_type]) . ')' . '</p>';
				echo '<style>
					.SM_table{
						border-collapse: collapse;
					}
					.SM_table tbody tr{
						line-height: 15px;
						border-top: 1px solid black;
					}
					.SM_table tbody tr:hover td{
						font-size: 11px;
						background: #fff;
					}
					.SM_table th{
						text-align: left;
					}
					.SM_table td{
						font-size: 10px;
					}
				</style>';
				echo '<table class="SM_table" width="100%">';
				echo '<thead>';
					echo '<tr>' . '<th width="40%">Title</th>' . '<th width="20%">Change frequency</th>' . '<th width="20%">Priority</th>' . '<th width="10%">Update</th>' . '<th width="10%">Link</th>' . '</tr>';
				echo '</thead>';
				echo '<tbody>';
				foreach($posts as $pid => $post){
					echo '<tr>' . 
						'<td>' . ($post['title'] ? $post['title'] : 'N/A') . '</td>' . 
						'<td>' . $post['changefreq'] . '</td>' . 
						'<td>' . $post['priority'] . '</td>' . 
						'<td>' . ($post['ID'] ? '<a href="/wp-admin/post.php?post=' . $post['ID'] . '&action=edit" target="blank">Edit</a>' : 'N/A') . '</td>' . 
						'<td>' . '<a href="' . $post['loc'] . '" target="blank">View</a>'  . '</td>' . 
					'</tr>';
				}
				echo '</tbody>';
				echo '</table>';
			}
		echo '</div>';

		//TEST
		echo '<pre>';
			//include plugin_dir_path( __FILE__ ) . 'accessplanit.php';
			//print_r( SM_post_types() );
			//print_r( SM_post_types('objects') );
			//$SM_options = get_option('SM_options');
			//print_r($SM_options);
			//$output = SM_builder('array');
			//print_r($output);
			//print_r($_POST);
			//$raw = SM_builder('raw');
			//print_r($raw);
			/*if(is_multisite() === true){
				echo 'This is a WordPress multisite';
			}
			var_dump(get_blog_details());*/
		echo '</pre>';
	echo '</div><!--close wrap-->';
}
?>