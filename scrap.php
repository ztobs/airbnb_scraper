<?php
error_reporting(E_ALL & ~E_NOTICE);
//error_reporting(0);
// usage:
// php scrap.php http://www.mydestination.com/nigeria/services/3500448/car-limousine-hire--drivers image_on ztobscieng@gmail.com 1 50
// i.e php scrap.php

$email = "ztobscieng@gmail.com";

$output_filename = "listings.csv";
$proxy_file = "proxies.csv";
$urls2scrap = 'urls.csv';
$written_dom = 0;


$timeouts = 10;
$proxyauth = "73290:s6yk2BpCu";
//$userauth = "";
$removeBadProxy = true;
$solverCaptcha = false;
//$numRetry = 50;



include('lib/simple_html_dom.php');



$file = fopen($proxy_file, 'r');
while (($line = fgetcsv($file)) !== FALSE) {
  //$line is an array of the csv elements
  $proxies[] = $line[0];
}
fclose($file);

$file = fopen($urls2scrap, 'r');
while (($line = fgetcsv($file)) !== FALSE) {
  //$line is an array of the csv elements
  $urls[] = $line[0];
}
fclose($file);

/////////////////////////////////////////////////////////////////////////////

function getDom($link, $noProxy=false, $type="curl")
 {
 	global $proxies;
 	global $solverCaptcha;
 	global $removeBadProxy;
 	//global $numRetry;
 	$dom = null;
    
    	
    if($type=="curl")
    {
    	//while($dom[1]['content_type'] == null)
    	//$retry = 0;
    	while($dom[0] == null)
    	{
    		if($noProxy==false) $proxy = chooseProxy();
    		else $proxy = null;
   			$dom = getDomCurl($link, $proxy);
   			$edata = json_decode($dom[0], true);
   			//if($dom[0] == null  || $edata['results_json']['search_results'] == null)  
   			if($dom[0] == null  || strpos($dom[0],"temporarily unavailable")!=false || strpos($dom[0],"Temporarily Unavailable")!=false  )
   			{	
   				/*if($retry >= $numRetry)
   				{
   					echo "Skipping...\r\n";
   					break;
   				}
   				$retry++;*/
   				echo "retrying...\r\n";
   				if(strpos($dom[0],"Robot Check")!==false)
   				{
   					echo "The proxy has been block\r\n";
   					if($solverCaptcha == true) captchaSolver($dom[0], $proxy);
   				} 
   				$dom[0] = null;
   				if($removeBadProxy == true)$proxies = array_diff($proxies, array($proxy)); // removing proxy that didnt work
   			}
    	}
   		
   	}
   	elseif ($type=="file_get")
   	{
   		while($dom[0] == null)
   		{
    		$context = setContext(chooseProxy());
	   		$content = file_get_html($link, false, $context);
	   		$dom = array( $content, null ); // Making it look like curls output, make make it an array with null headers
	   		if($dom[0] == null  || strpos($dom[0],"temporarily unavailable")!=false)
	   		{
   				echo "retrying...\r\n";
   				if(strpos($dom[0],"Robot Check")!==false)
   				{
   					echo "The proxy has been block\r\n";
   					if($solverCaptcha == true) captchaSolver($dom[0], $proxy);
   				} 
   				$dom[0] = null;
   				if($removeBadProxy == true)$proxies = array_diff($proxies, array($proxy)); // removing proxy that didnt work
   			}
	   		
	   		
   		}
   	}
    return $dom[0];
 }


function writeDom2File($dom, $output_filename)
{
	global $written_dom;
	$written_dom++;
	$myfile = fopen($output_filename."_".$written_dom.".html", "a") or die("Unable to open file!");
	fwrite($myfile, $dom."\r\n");
	fclose($myfile);
}



 function getDomCurl($link, $proxy)
 {
 	global $proxyauth;
 	global $userauth;
 	global $timeouts;
 	$pr = explode(":", $proxy);
 	$ch = curl_init();
 	$headers = array();
 	
	$headers[] = 'Accept:text/html';

	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
 	curl_setopt( $ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36" );
	if($proxy) curl_setopt($ch, CURLOPT_PROXY, $proxy);
	curl_setopt($ch, CURLOPT_URL,$link);
	//curl_setopt( $ch, CURLOPT_COOKIEJAR, "cookies.txt" );
	//curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
	curl_setopt($ch, CURLOPT_USERPWD, $userauth);
	curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyauth);
	curl_setopt($ch, CURLOPT_ENCODING,  ''); //gzip
	curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeouts );
    curl_setopt( $ch, CURLOPT_TIMEOUT, $timeouts );
    curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
	curl_setopt($ch, CURLOPT_HEADER, 0);
	$content = curl_exec( $ch );
	$content = str_get_html($content);
    $response = curl_getinfo( $ch );
	curl_close($ch);

	if ($response['http_code'] == 301 || $response['http_code'] == 302) {
        ini_set("user_agent", "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1");

        if ( $headers = get_headers($response['url']) ) {
            foreach( $headers as $value ) {
                if ( substr( strtolower($value), 0, 9 ) == "location:" )
                    return get_url( trim( substr( $value, 9, strlen($value) ) ) );
            }
        }
    }

    if (    ( preg_match("/>[[:space:]]+window\.location\.replace\('(.*)'\)/i", $content, $value) || preg_match("/>[[:space:]]+window\.location\=\"(.*)\"/i", $content, $value) ) && $javascript_loop < 5) {
        return get_url( $value[1], $javascript_loop+1 );
    } else {
        return array( $content, $response );
        //return $response;
    }
 }





 function chooseProxy()
 {
 	global $proxy_file;
 	global $proxies;
 	if(count($proxies) < 1)
 	{
 		echo "All the proxies are dead, fetching new ones\r\n";
 		$proxies = fetchProxies();
 		//var_dump($proxies);
 		$to_proxy_file = implode("\r\n", $proxies);
 		// writing new proxies to file
 		$myfile = fopen($proxy_file, "w") or die("Unable to open file! $proxy_file");
		fwrite($myfile, "\r\n".$to_proxy_file);
		fclose($myfile);
 	}
	$proxy = $proxies[array_rand($proxies)];
	echo "Using: $proxy\r\n";
	return $proxy;
 }


 function setContext($proxy=null, $username=null, $pass=null)
 {
 	$auth = base64_encode('$username:$pass');

	$aContext = array(
	    'http' => array(
	        'proxy' => $proxy,
	        'request_fulluri' => true,
	        'header' => "Proxy-Authorization: Basic $auth\r\n".
	        			"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8\r\n".
	        			"User-Agent:Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36"
	    )
	);
	$cxContext = stream_context_create($aContext);
	return $cxContext;
 }



function captchaSolver($dom, $proxy)
{
	while(strpos($dom,"Robot Check")!==false)
	{
		
		$dat = $dom->find('form img');
		
		save_image($dat[0]->src, "captcher.jpg");

		$dat = $dom->find('form input[name=amzn]');
		$amzn = $dat[0]->getAttribute('value');

		$dat = $dom->find('form input[name=amzn-r]');
		$amzn_r = $dat[0]->getAttribute('value');

		
		//$dat = $dom->find('form input[name=amzn-pt]');
		//$amzn_pt = $dat[0]->getAttribute('value');

		echo "Attempting to solve puzzle, Open the captcher image and type in the correct response followed by enter key\r\n";
		$handle = fopen ("php://stdin","r");
		$field_keywords = fgets($handle);

		$captcher_url = "https://www.amazon.com/errors/validateCaptcha?amzn=".urlencode($amzn)."&field-keywords=".trim($field_keywords);
		//echo $captcher_url; die();
		$dd = getDomCurl($captcher_url, $proxy);
		$dom = $dd[0];

		if(strpos($dom,"Robot Check")!==false)
		{
			echo "invalid captcher\r\n";
		}
		else
		{
			echo "captcher passed\r\n";
			//writeDom2File($dom, "after.html");
		}
	}
		
	

	
	

}


function save_image($inPath,$outPath)
{ //Download images from remote server


    $in=    fopen($inPath, "rb");
    $out=   fopen($outPath, "wb");
    while ($chunk = fread($in,8192))
    {
        fwrite($out, $chunk, 8192);
    }
    fclose($in);
    fclose($out);
    return true;
}



function getListDet($dom)
{
	global $header;
	
	$id = getId($dom);
	$lat = getLat($dom);
	$long = getLong($dom);
	$address = getAddress($dom);
	$city = getCity($dom);
	$url = getUrl($dom);

	$type = getListType($id);
	$GmapAddr = getGmapAddr($lat, $long);
	


	$data = ["Listing Url"=>$url, "Lat"=>$lat, "Long"=>$long, "Property Type"=>$type, "Address"=>$address, "City"=>$city, "Google Map Address"=>$GmapAddr];
	if($header == null) $header = '"'.implode('","', array_keys($data)).'"'."\n";
	return $data;
}








function base64_to_jpeg($base64_string, $output_file) {
    $ifp = fopen($output_file, "wb"); 

    $data = explode(',', $base64_string);

    fwrite($ifp, base64_decode($data[1])); 
    fclose($ifp); 

    return $output_file; 
}



function fetchProxies()
{
	$dom = getDom("https://free-proxy-list.net/", true);
	
	foreach($dom->find('#proxylisttable > tbody > tr') as $e)
	{
			$ips[] = $e->childNodes(0)->innertext;
	}
	foreach($dom->find('#proxylisttable > tbody > tr') as $e)
	{
			$ports[] = $e->childNodes(1)->innertext;
	}

	for ($k=0; $k<count($ips); $k++)
	{
		$prox[] = $ips[$k].":".$ports[$k];
	}

	return array_slice($prox, 1, -1);;
}




function getUrl($dat)
{
	return "https://www.airbnb.com/rooms/".$dat['listing']['id'];
}


function getLat($dat)
{
	return $dat['listing']['lat'];
}


function getLong($dat)
{
	return $dat['listing']['lng'];
}


function getListType($id)
{
	$dom = getDom("https://api.airbnb.com/v1/listings/".$id."?client_id=3092nxybyb0otqw18e8nh5nty", true);
	
	$dat = json_decode($dom, true);
	$tt = $dat['listing']['property_type'];

	return $tt;
}

function getId($dat)
{
	return $dat['listing']['id'];
}


function getAddress($dat)
{
	return $dat['listing']['public_address'];
}


function getCity($dat)
{
	return $dat['listing']['localized_city'];
}

function getDistribution($dat)
{
	return $dat['results_json']['metadata']['price_histogram']['histogram'];
}

function getGmapAddr($lat, $long)
{
	$url = "http://maps.googleapis.com/maps/api/geocode/json?latlng=".$lat.",".$long;
	$dom = getDom($url, true);
	$dat = json_decode($dom, true);
	if($dat['status'] == "OK")
	{
		$gAddress = $dat['results'][0]['formatted_address'];
	}
	return $gAddress;
}
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////



//setContext(chooseProxy($proxies))

//temporarily setting just one url



//&price_max=100000&price_min=0&page=

$counter = 0;
foreach ($urls as $url) {

	$dom = getDom($url, true);
	$data = json_decode($dom, true);
	$priceBars = getDistribution($data);

	//var_dump($priceBars); die();
	$price = 0;
	while (true) {
		
		$price++;
		$max_price = $price+1;
		for ($page=1; $page <= 17; $page++) { 
			$url_ = $url."&price_min=".$price."&price_max=".$price."&page=".$page; // using zero price interval because listings per price is very high than 300 (17pages) that we can capture
			echo $url_."\r\n";

			$dom_ = getDom($url_, true);
			$data_ = json_decode($dom_, true);
			$tt = $data_['results_json']['search_results'];


			if($tt != null)
			{
				foreach ($tt as $dat) {
					$counter++;
					echo "============================= ($counter) =============================\r\n";
					
					$listing = getListDet($dat);
					

					// writing to file
					$data = "";
					foreach ($listing as $value) {
										
						$value = str_replace( '"' , ',' , $value ); 
						$value = '"'.$value.'"'.',' ;

						$data .= $value;
					}
					$data = rtrim($data, ",");

					if(file_exists($output_filename))
					{
						$header = null;
					}
							 		
									
					$myfile = fopen($output_filename, "a") or die("Unable to write file!");
					fwrite($myfile, $header.$data."\r\n");
					fclose($myfile);
				}
			}
			else
			{
				//writeDom2File($dom_, "error");
				break;
			}
		}
			if($price >= 15000) break;
	}
	

}

echo "============================= (END) =============================\r\n";
echo "$counter Listings were exported to $output_filename\r\n";
if($email) mail($email,"Listings Export", "$counter Listings successfully generated into $output_filename on ".date("Y-m-d H:i:s"));