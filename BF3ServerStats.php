<?php
/*
Plugin Name: Battlefield 3 Server Stats
Plugin URI: http://jasonhazel.com
Description: Display the stats of your BF3 server.
Author: Jason Hazel
Version: 1
Author URI: http://jasonhazel.com
*/


class BF3ServerStats extends WP_Widget
{
	private $config 	= array(
								'api_url' 	=> 'http://api.bf3stats.com',
								'platform' 	=> null,
								'datagroup' => 'server',
								'trailing'	=> null					// we want a trailing slash.
							);

	private $options 	= array('output' 	=> 'json');
	private $platforms 	= array('default'	=> '360','pc','ps3');
	private $response 	= array();

	public function BF3ServerStats($server = null, $platform = null)
	{
		$widget_ops = array('classname' => 'BF3ServerStats', 'description' => 'Display general information about a BF3 Server' );
    	$this->WP_Widget('BF3ServerStats', 'Battlefield 3 Server Stats', $widget_ops);
	}

  	public function form($instance)
  	{
    	$instance = wp_parse_args( (array) $instance, array('title' => 'My BF3 Server', 'server_id' => '', 'platform' => '' ) );
    	$server_id = $instance['server_id'];
    	$title = $instance['title'];
    	$platform = $instance['platform'];

    	$form = "<p><label for='" 
    			. $this->get_field_id('title') 
    			. "'> Title: <input class='widefat' id='" 
    			. $this->get_field_id('title') 
    			. "' name='" 
    			. $this->get_field_name('title')
    			. "' type='text' value='"
    			. attribute_escape($title)
    			. "' /></label></p>";

    	$form .= "<p><label for='" 
    			. $this->get_field_id('server_id') 
    			. "'> Server ID: <input class='widefat' id='" 
    			. $this->get_field_id('server_id') 
    			. "' name='" 
    			. $this->get_field_name('server_id')
    			. "' type='text' value='"
    			. attribute_escape($server_id)
    			. "' /></label></p>";

    	$form .= "<p><label for='"  . $this->get_field_id('platform') . "'> Platform (360, pc, ps3): "
    			. "<select id='" . $this->get_field_id('platform') . "' name='" . $this->get_field_name('platform') . "'>";

    	foreach($this->platforms as $plat)
    	{
			$selected = '';
    		if($plat == $platform)
    			$selected = ' selected ';


    		$form .= "<option value='" . $plat . "' $selected>" . $plat . "</option>";
    	}

    	$form .= "</select></label></p>";		

    	echo $form;
  	}

  	public function update($new_instance, $old_instance)
  	{
    	$instance = $old_instance;
    	$instance['title'] = $new_instance['title'];
    	$instance['server_id'] = $new_instance['server_id'];
    	$instance['platform'] = $new_instance['platform'];
    	return $instance;
  	}


  	public function widget($args, $instance)
  	{
    	extract($args, EXTR_SKIP);
 
    	echo $before_widget;
    	$title = $instance['title'];
    	$server_id = $instance['server_id'];
    	$platform = $instance['platform'];

    	if (!empty($title))
      		echo $before_title . $title . $after_title;;
 
      	if(!empty($server_id))
      	{
      		$this->setServer($server_id);
      		$this->setPlatform($platform);
      		$stats = $this->Statistics()->data->srv;
			$stats = (object) array(
						'name'		=> $stats->name,
						'platform'	=> strtoupper($stats->plat),
						'players' 	=> (object) array(
											'live' 	=> $stats->players, 
											'slots'	=> $stats->slots,
											'avail'	=> $stats->slots - $stats->players
											),
						'map'		=> (object) array(
											'name' 	=> ucwords(strtolower($stats->map_name)),
											'image' => $stats->map_image,
											'mode'	=> ucwords(strtolower($stats->mode_name))
											)

					); 
			$output = "<div class='buffer'>";
			$output .= "<table width='100%'>";

			$output .= "<tr><td><b>Server Name</b></td><td align='right'><a target= '_blank' href='http://bf3stats.com/server/" . $platform . '_' . $server_id . "'>" . $stats->name . "</a></td></tr>";
			$output .= "<tr><td><b>Platform</b></td><td align='right'>" . $stats->platform . "</td></tr>";
			$output .= "<tr><td><b>Game Mode</b></td><td align='right'>" . $stats->map->mode . "</td></tr>";
			$output .= "<tr><td><b>Current Map</b></td><td align='right'>" . $stats->map->name . "</td></tr>";
			$output .= "<tr><td><b>Players</b></td><td align='right'>" . $stats->players->live . " / " . $stats->players->slots . "</td></tr>";
			$output .= "</table>";

			$output .= "<h6 style='margin-top:10px; font-weight:normal;text-align:right;font-size:9px;'><a  target= '_blank' href='http://bf3stats.com'>Powered by BF3Stats.com</a></h6>";

			// print_r($stats);     
			
			$output .= "</div>";

			echo $output;		
      	}


 
    	echo $after_widget;
  	}

	public function setPlatform($platform = null)
	{
		if(in_array($platform, $this->platforms))
			$this->config['platform'] = $platform;
		else
			$this->config['platform'] = $this->platforms['default'];

		return $this;
	}

	public function setServer($server = null)
	{
		$this->options['id'] = $server;
		return $this;
	}

	private function URL()
	{
		return implode('/', $this->config);
	}

	public function Pull()
	{
		$c=curl_init($this->URL());
		curl_setopt($c,CURLOPT_HEADER,false);
		curl_setopt($c,CURLOPT_POST,true);
		curl_setopt($c,CURLOPT_USERAGENT,'BF3StatsAPI/0.1');
		curl_setopt($c,CURLOPT_HTTPHEADER,array('Expect:'));
		curl_setopt($c,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($c,CURLOPT_POSTFIELDS,$this->options);
		$this->response['data']=json_decode(curl_exec($c));
		$this->response['status']=curl_getinfo($c,CURLINFO_HTTP_CODE);
		curl_close($c);
		return $this;
	}

	public function Statistics()
	{
		if(empty($this->response)) $this->Pull();

		if($this->response['status'] != 200)
			return (object) array('status' => $this->response['status']);
		else
			return (object) $this->response;
	}

}


add_action( 'widgets_init', create_function('', 'return register_widget("BF3ServerStats");') );


?>