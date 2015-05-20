<?php

class urbanScraper
{
	
	// uses DOMDocument and curl
	
	
    private $output;
    private $restaurants;

	
	    // $item can be an id or an url
	    
	    
	private function get_data($url) {
		$ch = curl_init();
		$timeout = 5;
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		$data = curl_exec($ch);
		curl_close($ch);
		
		return $data;
	}



	public function get_data_multi($url_array) {
				
		$process_count = count($url_array);
		
		$mh = curl_multi_init();
		$handles = array();
		
		$return=array();
		$i=0;
		
		while ($process_count--)
		{
		    $ch = curl_init();
		    curl_setopt($ch, CURLOPT_URL, $url_array[$i]);
		    curl_setopt($ch, CURLOPT_HEADER, 0);
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		    curl_multi_add_handle($mh, $ch);
		    $handles[] = $ch;
		    $i++;
		}
		
		$running=null;
		
		do 
		{
		    curl_multi_exec($mh, $running);
		} 
		while ($running > 0);
		
		for($i = 0; $i < count($handles); $i++) 
		{
		    $return[] = curl_multi_getcontent($handles[$i]);
		    curl_multi_remove_handle($mh, $handles[$i]);
		}
		
		curl_multi_close($mh);
	
		return $return;
		
	}



	private function do_xpath($url){
		$data = $this->get_data($url);
		$dom = new DOMDocument();
		$dom -> loadHTML($data);
		$xpath = new DomXPath($dom);
		return $xpath;
	}


	private function do_xpath_html($data){
		$dom = new DOMDocument();
		$dom -> loadHTML($data);
		$xpath = new DomXPath($dom);
		return $xpath;
	}


	private function remove_numbers($string){
		return  preg_replace('/[^0-9,.]+/i', '', $string);

	}


	public function doPages($url){
		global $list;
		$return = 15;
		$i=1;
		

		while ($return == "15") {
			$url_array=array();
			
			// MAX THREADS FOR GRABBING THE LIST OF X
			for ($x=1; $x <= 10; $x++){
				$url_array[]=$url."?page=$i";
				$i++;
			}		

 			$data = $this->get_data_multi($url_array);
 			
			foreach ($data as $value){
					$return = $this->getRestaurantsList($value);
					flush();
			}


//FOR DEBUGGING - CHECK ONLY FIRST 10 PAGES
//			if ($i>1){ $return = 0; }
			
		}
		
		return $list;
	}

    private function getRestaurantsList($url){

		global $list;
	
		$xpath = $this->do_xpath_html($url);
		
		$return = array(); $restaurantsFound=0;
		
		// delete duplicate zip code
		foreach($xpath->query('//*[@id="js-restaurant-results"]/div/ul/li/div/div/a') as $e ) {
			$list[]="http://www.urbanspoon.com".$e->getAttribute('href');
			$restaurantsFound++; $start++;

		}
				
		return $restaurantsFound;

    }
    
		
	private function fetch_og($xpath)
		{

		    $metas = $xpath->query('//*/meta[starts-with(@property, \'og:\')]');
		
		    $og = array();
			
			if($metas->length > 0) {
	
			    foreach($metas as $meta){
			        # get property name without og: prefix
			        $property = str_replace('og:', '', $meta->getAttribute('property'));
			        # get content
			        $content = $meta->getAttribute('content');
			        $og[$property] = $content;
			    }
			
			}
			
		    return $og;
		}
		

    public function displayRestaurantDetailsMulti($url){

		$url_array=array(); $detailed_restaurants = array(); $return = array();

		$counter=0;

		foreach ($url as $key => $value){
		
			$url_array[]=$value;
		
			$counter++;
		
			if ($counter == 15) {

					$data = $this->get_data_multi($url_array);
					
					foreach ($data as $html){
				
						$xpath = $this->do_xpath_html($html);

						// delete duplicate zip code
						foreach($xpath->query('//*[@id="address"]/div/span[2]/span[2]') as $e ) {
							$e->parentNode->removeChild($e);
						}
						
						$og=$this->fetch_og($xpath);
						$return['restaurant_url']=$og['url'];
						$return['restaurant_image']=$og['image'];
												
						$restaurant_name = $xpath->query('//*[@id="header-base"]/span/h1');
						if($restaurant_name->length > 0) {
						  	$return['restaurant_name'] = trim($restaurant_name->item(0)->nodeValue);
						}
						 
						 
						$telephone = $xpath->query('//*[@id="phone-base"]/div/a');
						if($telephone->length > 0) {
						  	$return['telephone'] = trim($telephone->item(0)->nodeValue);
						}
						
						
						$city = $xpath->query('//*[@id="breadcrumbs"]/li[1]/a/span');
						if($city->length > 0) {
						  	$return['city'] = trim($city->item(0)->nodeValue);
						}
						
						
						$district = $xpath->query('//*[@id="neighborhood-base"]/span/a');
						if($district->length > 0) {
						  	$return['district'] = trim($district->item(0)->nodeValue);
						}
						

						$street_address = $xpath->query('//*[@id="address"]/div/span[1]');
						if($street_address->length > 0) {
						  	$return['street_address'] = trim($street_address->item(0)->nodeValue);
						}


						$locality = $xpath->query('//*[@id="address"]/div/span[2]');
						if($locality->length > 0) {
							$item=$locality->item(0)->nodeValue;
							$item=str_replace("\n\n","\n",trim($item));
							$item=str_replace("\n\n","\n",trim($item));
							$item=str_replace("\n","|",$item);
						  	$item=explode("|",$item);
						  	$return['locality_1'] = trim($item[0],",");
						  	$return['locality_2'] = $item[1];
						  	$return['locality_3'] = $item[2];
						  	
						}


						$website = $xpath->query('//*[@id="weblinks-base"]/div/div[1]/a');
						if($website->length > 0) {
						  	$return['website'] = trim($website->item(0)->getAttribute('href'));
						} else { $return['website']=""; }


						$facebook = $xpath->query('//*[@id="weblinks-base"]/div/div[2]/a');
						if($facebook->length > 0) {
						  	$return['facebook'] = trim($facebook->item(0)->getAttribute('href'));
						} else { $return['facebook']=""; }


						$twitter = $xpath->query('//*[@id="twitter-feed-base"]/div/div/h3[2]/a');
						if($twitter->length > 0) {
						  	$return['twitter'] = trim($twitter->item(0)->getAttribute('href'));
						} else { $return['twitter']=""; }
						


						$price = $xpath->query('//*[@id="cuisines-base"]/div/span');
						if($price->length > 0) {
						  	$return['price'] = trim($price->item(0)->nodeValue);
						}

						$rating = $xpath->query('//*[@id="vote-base"]/div/div/div[1]/div[1]/div[1]');
						if($rating->length > 0) {
						  	$return['rating'] = trim($rating->item(0)->nodeValue);
						}

						$votes = $xpath->query('//*[@id="vote-base"]/div/div/div[3]/div[1]');
						if($votes->length > 0) {
						  	$return['votes'] = trim($this->remove_numbers($votes->item(0)->nodeValue));
						}

						$reviews = $xpath->query('//*[@id="vote-base"]/div/div/div[3]/div[2]/a');
						if($reviews->length > 0) {
						  	$return['reviews'] = trim($this->remove_numbers($reviews->item(0)->nodeValue));
						}
						
						$gps = $xpath->query('//*[@id="geo-base"]/div/div[1]');
						if($gps->length > 0) {
						  	$return['gps_map_url'] = trim($gps->item(0)->getAttribute('data-map-img'));
						}
					
							$gps=urldecode($return['gps_map_url']);
							$gps=explode("|",$gps);
							$gps=explode("&",$gps[1]);
							$gps=explode(",",$gps[0]);							
							$return['gps_latitude']=$gps[0];
							$return['gps_longitude']=$gps[1];
							
					$detailed_restaurants[]=$return;
						
					}


				$url_array=array();
				$counter=0;
			}
			
			
		}


		return $detailed_restaurants;
		
    }
        
        

	public function print_csv($array){
		$out = fopen('php://output', 'w');
		foreach ($array as $fields) {
		    fputcsv($out, $fields);
		}
		fclose($out);
	
	}
    
}


set_time_limit(0);

$scraper = new urbanScraper();

$list = $scraper->doPages("http://www.urbanspoon.com/lb/3/best-restaurants-New-York"); 

$restaurants = $scraper->displayRestaurantDetailsMulti($list);

$scraper->print_csv($restaurants);

// THIS WILL BUILD A CSV OF ALL THE RESTAURANTS IN NEW YORK

?>