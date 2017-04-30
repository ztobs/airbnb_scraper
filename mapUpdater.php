<?php

include('functions.php');

$host = "127.0.0.1";
$user = "root";
$pass = "";
$db = "airbnb";

$conn = new mysqli($host, $user, $pass, $db);

if (!$conn->connect_errno) {
    echo "Database Connected\n";
}

//$sql = "SELECT * FROM `toronto` WHERE `Google Map Address` = '' ";

$sql = "SELECT * FROM `toronto new` ";
$rs = $conn->query($sql);

while($row = $rs->fetch_assoc())
{
	$lurl = $row['Listing Url'];
	$lat = $row['Lat'];
	$long = $row['Long'];

	$url = "https://maps.googleapis.com/maps/api/geocode/json?latlng=".$lat.",".$long."&key=AIzaSyDf1zH207xn4tI_JE9wrAlydz0_TVBThtg";
	$dom = getDom($url, true);
	$dat = json_decode($dom, true);
	if($dat['status'] == "OK")
	{
		$gAddress = $dat['results'][0]['formatted_address'];

		$sql = "UPDATE toronto SET `Google Map Address` = '$gAddress' WHERE `Listing Url` = '$lurl' ";
		$conn->query($sql);
		if($conn->affected_rows > 0) echo "$lurl Address Set to $gAddress\n";
	}
}