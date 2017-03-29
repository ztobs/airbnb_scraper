<?php
//error_reporting(E_ALL & ~E_NOTICE);
error_reporting(0);
// usage:
// php scrap.php http://www.mydestination.com/nigeria/services/3500448/car-limousine-hire--drivers image_on ztobscieng@gmail.com 1 50
// i.e php scrap.php


$output_filename = "listings.csv";
$proxy_file = "proxies.csv";
$urls2scrap = 'urls.csv';
$written_dom = 0;


$timeouts = 10;
$proxyauth = "73290:s6yk2BpCu";
//$userauth = "";
$removeBadProxy = false;
$solverCaptcha = false;



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
 	$dom = null;
    
    	
    if($type=="curl")
    {
    	//while($dom[1]['content_type'] == null)
    	while($dom[0] == null)
    	{
    		if($noProxy==false) $proxy = chooseProxy();
    		else $proxy = null;
   			$dom = getDomCurl($link, $proxy);
   			if($dom[0] == null  || strpos($dom[0],"temporarily unavailable")!==false)
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
   	elseif ($type=="file_get")
   	{
   		while($dom[0] == null)
   		{
    		$context = setContext(chooseProxy());
	   		$content = file_get_html($link, false, $context);
	   		$dom = array( $content, null ); // Making it look like curls output, make make it an array with null headers
	   		if($dom[0] == null  || strpos($dom[0],"Robot Check")!==false)
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
 		var_dump($proxies);
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



function getListDet($dom, $listurl)
{
	global $header;
	
	$lat = getLat($dom);
	$long = getLong($dom);
	$type = getListType($dom);
	$address = getAddress($dom);
	$city = getCity($dom);
	



	$data = ["Listing Url"=>$listurl, "Lat"=>$lat, "Long"=>$long, "Property Type"=>$type, "Address"=>$address, "City"=>$city];
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




function getTotalPages($dom)
{
	foreach($dom->find('.pagination li a') as $e)
	{
			$li[] = $e->plaintext;
	}

	$count = count($li)-2;

	return $li[$count];
}


function getListPage($dom)
{
	foreach($dom->find('.search-results .listing-card-wrapper a') as $e)
	{
		$pageLists[] = $e->href;
	}

	return $pageLists;
}



function getLat($dom)
{
	$dat = $dom->find('meta[property=airbedandbreakfast:location:latitude]');
	if($dat[0]) return $dat[0]->getAttribute('content');
	else echo "An error occured while getting Latitude\r\n";
	
}



function getLong($dom)
{
	$dat = $dom->find('meta[property=airbedandbreakfast:location:longitude]');
	if($dat[0]) return $dat[0]->getAttribute('content');
	else echo "An error occured while getting Longitude\r\n";
}


function getCity($dom)
{
	$dat = $dom->find('meta[property=airbedandbreakfast:city]');
	if($dat[0]) return $dat[0]->getAttribute('content');
	else echo "An error occured while getting City\r\n";
}


function getAddress($dom)
{
		$dat = $dom->find('meta[property=airbedandbreakfast:locality]');
		if($dat[0]) $locality = $dat[0]->getAttribute('content');

		$dat = $dom->find('meta[property=airbedandbreakfast:region]');
		if($dat[0]) $region = $dat[0]->getAttribute('content');

		$dat = $dom->find('meta[property=airbedandbreakfast:country]');
		if($dat[0]) $country = $dat[0]->getAttribute('content');

		return "$locality, $region, $country";
}


function getListType($dom)
{
	
	foreach($dom->find('.js-details-column a') as $e)
	{
		if(strpos($e->plaintext, "Property type:") !== FALSE)
		{
			return trim(str_replace("Property type:", "", $e->plaintext));
		}
		
	}
}
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////



//setContext(chooseProxy($proxies))

//temporarily setting just one url
$urls= ["https://www.airbnb.com/s/Miami--FL?page=1"];


$counter = 0;
foreach ($urls as $page) {

	$dom = getDom($page, true);
	$totalPages = getTotalPages($dom);

	for ($i=1; $i <= $totalPages; $i++) {

		$listurl = rtrim($page, "1").$i;
		$dom2 = getDom($listurl, true);
		$part_urls = getListPage($dom2);


		foreach ($part_urls as $part_url) {
			$counter++;
			echo "============================= ($counter) =============================\r\n";
			$listurl = "https://www.airbnb.com".$part_url;
			
			$dom_listing = getDom($listurl, true);

			$listing = getListDet($dom_listing, $listurl);

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

	
}

echo "============================= (END) =============================\r\n";
echo "$counter Listings were exported to $output_filename\r\n";
if($email) mail($email,"Listings Export", "$counter Listings successfully generated into $output_filename on ".date("Y-m-d H:i:s"));