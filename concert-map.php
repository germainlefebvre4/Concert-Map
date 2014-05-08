<?php
/*
Plugin Name: Concert Map
Plugin URI: http://www.arcticdesign.fr
Description: Map des lieux de concert.
Version: 1.0
Author: Germain
Author URI: http://www.arcticdesign.fr
License: GPL2
*/

class concert_map
{
	public function __construct()
	{
	}
	
	public function wp_loaded()
	{
	}

	public function uninstall()
	{
	}
}

//SETUP
function concert_map_install(){
    //Do some installation work
}
register_activation_hook(__FILE__,'concert_map_install');

//CSS
function concert_map_plugin_styles() {
	wp_enqueue_style( 'concert-map', plugin_dir_url( __FILE__ ).'css/styles.css', array(), '1.0.0', "all" );
}
add_action( 'wp_enqueue_scripts', 'concert_map_plugin_styles' );

//SCRIPTS
function concert_map_plugin_scripts(){
	wp_register_script('concert_map_plugin_scripts', plugin_dir_url( __FILE__ ).'js/concert-map.js');
	wp_register_script('concert_map_plugin_scripts', plugin_dir_url( __FILE__ ).'js/jquery-1.11.0.min.js');
	wp_register_script('concert_map_plugin_scripts', 'https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false');
	wp_enqueue_script('concert_map_plugin_scripts');
}
add_action('wp_enqueue_scripts', 'concert_map_plugin_scripts');



//ADMIN
// function concert_map_admin() {
    // include('concert_map.php');
// }
 
// function concert_map_admin_actions() {
    // add_options_page("Concert Map", "Concert Map", 1, "Concert Map", "concert_map_admin");
// }
// add_action('admin_menu', 'concert_map_admin_actions');



//HOOKS
add_action('init','concert_map_init');
/********************************************************/
/* FUNCTIONS
********************************************************/
function concert_map_init(){
    //do work
    run_sub_process();
}

function run_sub_process(){
    //more work
}


/********************************************************/
/******** WIDGET *********/
/********************************************************/

class ConcertMap extends WP_Widget {

	function ConcertMap() {
		// Instantiate the parent object
		parent::__construct( false, 'Concert Map' );
		
	}

	function widget( $args, $instance ) {
		// Widget output
		if ( !is_admin() ) {
				
		global $wpdb;
		
		if($_SERVER['SCRIPT_URI'] == "http://concert.arcticdesign.fr/")
			$height = "600px";
		else
			$height = "355px";
		
		// $_SERVER['SCRIPT_URI'] = "http://concert.arcticdesign.fr/locations/blue-devils/";
		$slug = explode("/", $_SERVER['SCRIPT_URI']);
		
		if($slug[3] == "locations") {
			$sql11 = "
			SELECT loc.location_id, loc.post_id, loc.location_slug, loc.location_name, loc.location_address, loc.location_town, loc.location_state, loc.location_postcode, loc.location_region, loc.location_country, loc.location_latitude, loc.location_longitude
			FROM concert_em_locations loc
			WHERE loc.location_slug='".$slug[4]."'
			";
			$lieu1 = $wpdb->get_results($sql11,ARRAY_A);
		} elseif($slug[3] == "events") {		
			$sql12 = "
			SELECT evt.event_id, evt.event_slug, loc.location_id, loc.post_id, loc.location_slug, loc.location_name, loc.location_address, loc.location_town, loc.location_state, loc.location_postcode, loc.location_region, loc.location_country, loc.location_latitude, loc.location_longitude
			FROM concert_em_events evt
			JOIN concert_em_locations loc ON loc.location_id=evt.location_id
			WHERE evt.event_slug='".$slug[4]."'
			";
			$lieu2 = $wpdb->get_results($sql12,ARRAY_A);
		}
		
		if(sizeof($lieu1) > 0) {
			$zoom = 15;
			// $lieu=$lieu1
			$latitude = $lieu1[0]["location_latitude"]+0.0020;
			$longitude = $lieu1[0]["location_longitude"];
		} elseif(sizeof($lieu2) > 0) {
			$zoom = 15;
			// $lieu=$lieu2
			$latitude = $lieu2[0]["location_latitude"]+0.0020;
			$longitude = $lieu2[0]["location_longitude"];
		} else {
			$zoom = 11;
			$latitude = 50.4026345;
			$longitude = 2.6484983;
		}
		
		// Req SQL2 => Lister les lieux ayant au moins un concert dans les 30 prochains jours
		$sql2 = "
		SELECT COUNT( * ) nb_events, loc.location_id, loc.post_id, loc.location_slug, loc.location_name, loc.location_address, loc.location_town, loc.location_postcode, loc.location_latitude, loc.location_longitude
		FROM concert_em_locations loc
		JOIN concert_em_events evt ON evt.location_id = loc.location_id
		WHERE TO_DAYS( event_start_date ) > TO_DAYS( NOW( ) ) 
			AND TO_DAYS( evt.event_start_date ) - ( TO_DAYS( NOW( ) ) ) <30
		GROUP BY loc.location_id
		";
		$results2 = $wpdb->get_results($sql2,ARRAY_A);
		
		// Req SQL3 => Lister tous les lieux
		$sql3 = "
		SELECT 0 as nb_events, location_name, location_slug, location_address, location_town, location_postcode, location_latitude, location_longitude
		FROM concert_em_locations
		";
		$results3 = $wpdb->get_results($sql3,ARRAY_A);
		
		// Détecter et concaténer les lieux sans prochain événement
		// Créer des tableaus intermédiaires simples
		for($i=0;$i<sizeof($results2);$i++) {
			$tab2[$i] = $results2[$i]["location_name"];
		}
		for($i=0;$i<sizeof($results3);$i++) {
			$tab3[$i] = $results3[$i]["location_name"];
		}
		// Détecter la non présence d'événement dans la Req SQL2
		for($i=0;$i<sizeof($tab3);$i++) {
			if(!in_array($tab3[$i],$tab2)){
				$results2[sizeof($results2)] = $results3[$i];
			}
		}
		
		// Envoyer le résultats en JSON
		$json = json_encode($results2);
		
		?>
			<script src="https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false"></script>
			<script>
			var i;
			var map;
			var icon;
			var lieux = <?php echo $json; ?>;
			var infowindow;
			var infowindow_content;
			var marker = new Array();
			
			function initialize() {
				var mapOptions = {
					zoom: <?php echo $zoom; ?>,
					center: new google.maps.LatLng(<?php echo $latitude; ?>, <?php echo $longitude; ?>),
					mapTypeId: google.maps.MapTypeId.ROADMAP,
					scrollwheel : false,
					disableDoubleClickZoom : true,
					panControl: true,
					panControlOptions: {
						position: google.maps.ControlPosition.RIGHT_BOTTOM
					},
					streetViewControl: true,
					mapTypeControl: false,
					zoomControl: true,
					zoomControlOptions: {
						style: google.maps.ZoomControlStyle.SMALL,
						position: google.maps.ControlPosition.LEFT_BOTTOM
					}
				};
				map = new google.maps.Map(document.getElementById("map-canvas"), mapOptions);
				
				for(i=0;i<lieux.length;i++){
					// Infowindow
					infowindow_content = '<div class="map-infobulle">' +
						'<div style="font-weight:bold;">'+lieux[i]["location_name"]+'</div>' +
						'<div>'+lieux[i]["location_address"]+'</div>' +
						'<div>'+lieux[i]["location_town"]+', '+lieux[i]["location_postcode"]+'</div>' +
						'<div id="more"><a href="http://concert.arcticdesign.fr/locations/'+lieux[i]["location_slug"]+'/">En savoir plus</a></div>';
					infowindow_content = infowindow_content + '</div>';
					
					infowindow = new google.maps.InfoWindow({
						content: infowindow_content
					});
					
					// Icon
					if(lieux[i]["nb_events"] <= 5)
						icon = 'http://concert.arcticdesign.fr/wp-content/plugins/concert-map/img/marker'+lieux[i]["nb_events"]+'.png';
					else if(lieux[i]["nb_events"] > 5)
							icon = 'http://concert.arcticdesign.fr/wp-content/plugins/concert-map/img/marker5p.png';
						else
							icon = 'http://concert.arcticdesign.fr/wp-content/plugins/concert-map/img/marker0.png';
					
					marker[i] = new google.maps.Marker({
						map:map,
						animation: google.maps.Animation.DROP,
						position: new google.maps.LatLng(lieux[i]["location_latitude"], lieux[i]["location_longitude"]),
						icon: icon
					});
					var tab;
					google.maps.event.addListener(marker[i], 'click', (tab = function(map,infowindow,marker,tab) {
						return function() {
							infowindow.open(map,marker);
							marker.setAnimation(google.maps.Animation.DROP);
						}
					})(map,infowindow,marker[i],tab));
				}
			}
		
		
			google.maps.event.addDomListener(window, "load", initialize);
			</script>
			
			<div id="map-canvas" style="height:<?php echo $height; ?>;"></div>
		<?php
		}
	}

	function update( $new_instance, $old_instance ) {
	}

	function form( $instance ) {
	}
}

function concert_map_register_widgets() {
	register_widget( 'ConcertMap' );
}
add_action( 'widgets_init', 'concert_map_register_widgets' );

?>