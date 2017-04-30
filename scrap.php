<?php

include('functions.php');

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
					//var_dump($dat['listing']['property_type']); die();
					if (IsAllowedType($dat['listing']['property_type']) && IsUnique($dat['listing']['id']))
					{
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