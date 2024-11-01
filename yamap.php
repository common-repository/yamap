<?php
/**
 * Plugin Name: YaMap
 * Plugin URI: https://zavolsky.ru/wp-plugin/ya-map/
 * Description: Shotcode yandex map
 * Version: 2.0.0
 * Author: Valery Zavolsky
 * Author URI: https://zavolsky.ru
 */

defined( 'ABSPATH' ) or die( '^_^' );

$yamap = new YaMap();

class YaMap {
    
    private $shortcodename = 'map';
    private $height = '400px';
    private $zoom = '16';
    private $check_short_code = false;
    private $atts;
    private $content;
    
    function __construct() {
        add_shortcode('map', array( $this, 'run_shortcode' ));
        add_action('wp_footer', array( $this, 'wp_footer' ));
        add_action('admin_menu', array( $this, 'yamapvz_settings_page' ));
        
	}

	public function wp_footer() {
        if ($this->check_short_code) {
            $content = $this->content;
            $atts = $this->atts;
            $return_string = '';
            
            if ($this->check_chars_only_digits($content)) {
                $return_string = mb_split(",",$content);
            } else {
                 //$map_coordinates = get_post_meta( $post_id , 'map_coordinates', true );
                $map_point = $this->get_yamap_point($content);
                $return_string = $this->return_js_map(array_reverse($map_point));
               //$return_string = $content.' - '.$atts['height'].'<p>Карта будет тут</p>';
            }
           
           echo $return_string;
        }

	
	}
    
    private function check_chars_only_digits($content) { //проверяем наличие символов в контенте шоткода отличных от цифр;
        $digits = "1234567890.,";
        for ($i = 0; $i < mb_strlen($content); $i++) {
            $pos = mb_strpos($digits, $content[$i]);
            if ($pos === false) {
                return false;
            }
        }
        return true;
    }
    
    private function return_js_map($map_point) { // готовим к выводу js для карты;
        return "<script src=\"https://api-maps.yandex.ru/2.1/?lang=ru_RU\" type=\"text/javascript\"></script>
                <script type=\"text/javascript\">
                    var myMap;
                    // Дождёмся загрузки API и готовности DOM.
                    ymaps.ready(init);
                    function init () {
                        // Создание экземпляра карты и его привязка к контейнеру с
                        // заданным id (\"map\").
                        myMap = new ymaps.Map('map', {
                            // При инициализации карты обязательно нужно указать
                            // её центр и коэффициент масштабирования.
                            center: [$map_point[0],$map_point[1]], // Москва
                            zoom: 16
                        }, {
                            searchControlProvider: 'yandex#search'
                        });

                        myMap.behaviors.disable('scrollZoom');
                        myMap.geoObjects

                            .add(new ymaps.Placemark([$map_point[0],$map_point[1]], {
                                balloonContent: 'цвет <strong>красный</strong>'
                            }, {
                                preset: 'islands#redStarIcon'
                            }));


                    }
                </script>
                <style>
                    #map {
                        width: 100%;
                        height: 450px;
                    }
                </style>
                ";
    }
    
    public function run_shortcode($atts, $content = null) {
        $this->check_short_code = true;
        $this->content = $content;
        $this->atts = $atts;
        
        return "<div id=\"map\"></div>";
    }
    
    public function yamapvz_settings_page() {
        add_submenu_page(
            'options-general.php', // top level menu page
            'YaMap Settings', // title of the settings page
            'YaMap Settings', // title of the submenu
            'manage_options', // capability of the user to see this page
            'yamap', // slug of the settings page
            array ( $this, 'yamapvz_settings_page_html' ) // callback function when rendering the page
        );
        add_action('admin_init', array ( $this, 'yamap_settings_init' ));
        register_setting( 'yamapvz_options_group', 'yamapvz_option_apikey', array ($this, 'yamapvz_options_data'));
    }

    public function yamapvz_settings_page_html() {
//         include ('assets/tpl/page_settings_tpl.php');
        ?>
            <div>
                <?php screen_icon(); ?>
                <h2>YaMap Settings</h2>
                <form method="post" action="options.php">
                <?php settings_fields( 'yamapvz_options_group' ); ?>
                <p>Если карта не отображается, то вам необходимо получить ключ API в <a href="https://developer.tech.yandex.ru/services/3/" target="_blank">панели разработчика Yandex</a> (предварительно не забудьте: авторизоваться; зарегистрировать учетную запись в Яндекс).</p>
                <table>
                <tr valign="top">
                <th scope="row"><label for="yamapvz_option_apikey">API Key</label></th>
                <td><input style="min-width: 300px;" type="text" id="yamapvz_option_apikey" name="yamapvz_option_apikey" value="<?php echo get_option('yamapvz_option_apikey'); ?>" /></td>
                </tr>
                </table>
                <?php  submit_button(); ?>
                </form>
            </div>
        <?
        
//         var_dump(get_option('yamapvz_option_apikey'));
    }
    
    public function yamapvz_options_data($input) {
        return trim($input);
    }
    
    private function get_yamap_point($content) {
        $post_id = get_the_ID();
        $map_coordinates = get_post_meta( $post_id , 'map_coordinates', true );
        $map_address = get_post_meta( $post_id, 'map_address', true );
        
        //update_post_meta( $post_id, 'map_address', $content);
        
        if ($map_address == $content && $map_coordinates) {
            $map_point_str =  $map_coordinates;
        } else {
            $apikey = get_option('yamapvz_option_apikey');
            $url = "https://geocode-maps.yandex.ru/1.x/?apikey=$apikey&format=json&lang=ru_Ru&results=1&geocode=".urlencode($content);
            //var_dump($url);
            $ch = curl_init(); 
                curl_setopt($ch, CURLOPT_URL, $url); 
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
                $map = curl_exec($ch);
            curl_close($ch); 
            $map_array_data = json_decode($map,true);
    
            $map_point_str = $map_array_data['response']['GeoObjectCollection']['featureMember'][0]['GeoObject']['Point']['pos'];
            
            update_post_meta( $post_id, 'map_coordinates', $map_point_str);
            update_post_meta( $post_id, 'map_address', $content);
        }
        return mb_split(" ",$map_point_str);
    }
    
}

/*




*/